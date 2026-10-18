<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DishModel;
use App\Services\TripoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DishController extends Controller
{
    protected TripoService $tripoService;

    public function __construct(TripoService $tripoService)
    {
        $this->tripoService = $tripoService;
    }

    /**
     * Query account balance and available models in Tripo V3.
     */
    public function balance(): JsonResponse
    {
        if (!$this->tripoService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'TRIPO_API_KEY no está configurada.',
            ], 500);
        }

        try {
            $balanceData = $this->tripoService->getBalance();
            $models = $this->tripoService->getAvailableModels();

            return response()->json([
                'success' => true,
                'balance' => $balanceData['balance'],
                'frozen' => $balanceData['frozen'],
                'models' => $models,
                'default_model' => config('services.tripo.default_model', 'v3.1-20260211'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all 3D dish generation jobs.
     */
    public function index(): JsonResponse
    {
        $dishes = DishModel::orderBy('created_at', 'desc')->take(50)->get();

        return response()->json([
            'success' => true,
            'data' => $dishes,
        ]);
    }

    /**
     * Upload dish photograph and create pending 3D job.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'title' => ['nullable', 'string', 'max:150'],
            'prompt' => ['nullable', 'string', 'max:500'],
            'model_version' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $request->file('image');
        $originalFilename = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $sizeBytes = $file->getSize();
        $extension = $file->getClientOriginalExtension();

        $storageFilename = Str::uuid() . '.' . $extension;
        $storedPath = $file->storeAs('uploads', $storageFilename, 'public');

        $imageUrl = asset('storage/' . $storedPath);

        $modelVersion = $validated['model_version'] ?? config('services.tripo.default_model', 'v3.1-20260211');

        $dish = DishModel::create([
            'title' => $validated['title'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'prompt' => $validated['prompt'] ?? null,
            'original_filename' => $originalFilename,
            'image_path' => $storedPath,
            'image_url' => $imageUrl,
            'mime_type' => $mimeType,
            'file_size_bytes' => $sizeBytes,
            'status' => 'pending',
            'progress' => 0,
            'model_version' => $modelVersion,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fotografía subida correctamente. Trabajo registrado en estado pendiente sin consumo de créditos.',
            'data' => $dish,
        ], 201);
    }

    /**
     * Get details of a single dish.
     */
    public function show(string $id): JsonResponse
    {
        $dish = DishModel::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dish,
        ]);
    }

    /**
     * Trigger 3D generation with Tripo AI V3 with strict balance verification.
     */
    public function generate(Request $request, string $id): JsonResponse
    {
        $dish = DishModel::findOrFail($id);

        if (in_array($dish->status, ['queued', 'running', 'success'])) {
            return response()->json([
                'success' => false,
                'message' => "El plato ya está en proceso o fue generado exitosamente (Estado actual: {$dish->status}).",
                'data' => $dish,
            ], 400);
        }

        if (!$this->tripoService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'TRIPO_API_KEY no está configurada en el servidor backend.',
            ], 500);
        }

        // Allow overriding model_version from request
        $modelVersion = $request->input('model_version', $dish->model_version ?: config('services.tripo.default_model', 'v3.1-20260211'));
        $requiredCredits = $this->tripoService->getRequiredCredits($modelVersion);

        // 1. Balance verification & guard
        try {
            $balanceData = $this->tripoService->getBalance();
            $currentBalance = (float) $balanceData['balance'];

            if ($currentBalance < $requiredCredits) {
                return response()->json([
                    'success' => false,
                    'insufficient_balance' => true,
                    'current_balance' => $currentBalance,
                    'required_credits' => $requiredCredits,
                    'model_version' => $modelVersion,
                    'message' => "Saldo insuficiente en tu cuenta de Tripo AI. Tu saldo disponible es de {$currentBalance} créditos y el modelo {$modelVersion} requiere {$requiredCredits} créditos. Por favor recarga créditos en tu consola de desarrollador antes de continuar.",
                    'data' => $dish,
                ], 402);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo verificar el saldo con Tripo AI V3: ' . $e->getMessage(),
                'data' => $dish,
            ], 500);
        }

        $absoluteImagePath = Storage::disk('public')->path($dish->image_path);
        if (!file_exists($absoluteImagePath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de imagen no se encuentra en el almacenamiento local.',
            ], 404);
        }

        try {
            $dish->update([
                'status' => 'uploading',
                'progress' => 5,
                'model_version' => $modelVersion,
                'error_message' => null,
            ]);

            // 2. Upload to Tripo V3 (/v3/files)
            $fileToken = $this->tripoService->uploadImage($absoluteImagePath);
            $dish->update([
                'tripo_file_token' => $fileToken,
                'progress' => 15,
            ]);

            // 3. Create task in Tripo V3 (/v3/generation/image-to-model)
            $ext = pathinfo($dish->original_filename, PATHINFO_EXTENSION) ?: 'png';
            $taskResult = $this->tripoService->createImageToModelTask($fileToken, [
                'file_type' => strtolower($ext) === 'jpg' ? 'jpeg' : strtolower($ext),
                'model_version' => $modelVersion,
            ]);

            $taskId = $taskResult['task_id'];

            $dish->update([
                'tripo_task_id' => $taskId,
                'status' => 'queued',
                'progress' => 20,
                'meta' => array_merge($dish->meta ?? [], ['v3_create_task_response' => $taskResult['raw']]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Generación 3D iniciada exitosamente en Tripo AI V3.',
                'data' => $dish->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Tripo AI V3 generation failed', [
                'dish_id' => $dish->id,
                'error' => $e->getMessage(),
            ]);

            $dish->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al comunicarse con Tripo AI V3: ' . $e->getMessage(),
                'data' => $dish->fresh(),
            ], 500);
        }
    }

    /**
     * Poll status of 3D task from Tripo AI V3 and download GLB when ready.
     */
    public function checkStatus(string $id): JsonResponse
    {
        $dish = DishModel::findOrFail($id);

        if (!$dish->tripo_task_id || in_array($dish->status, ['pending', 'failed', 'cancelled'])) {
            return response()->json([
                'success' => true,
                'data' => $dish,
            ]);
        }

        if ($dish->status === 'success' && $dish->glb_path && Storage::disk('public')->exists($dish->glb_path)) {
            return response()->json([
                'success' => true,
                'data' => $dish,
            ]);
        }

        try {
            $taskData = $this->tripoService->getTaskStatus($dish->tripo_task_id);
            $remoteStatus = $taskData['status'] ?? 'unknown';
            $remoteProgress = (int) ($taskData['progress'] ?? $dish->progress);

            $dish->progress = max($dish->progress, $remoteProgress);
            $dish->meta = array_merge($dish->meta ?? [], [
                'latest_v3_task_poll' => $taskData,
                'credits_consumed' => $taskData['credits_consumed'] ?? null,
            ]);

            if ($remoteStatus === 'success') {
                $output = $taskData['output'] ?? [];
                // V3 unified model key is 'model_url'
                $remoteModelUrl = $output['model_url'] ?? $output['pbr_model'] ?? $output['model'] ?? null;
                $renderedImage = $output['rendered_image_url'] ?? $output['rendered_image'] ?? null;

                if ($remoteModelUrl) {
                    $glbFilename = "models/{$dish->id}.glb";
                    $absoluteGlbPath = Storage::disk('public')->path($glbFilename);

                    // Download and persist GLB locally
                    $this->tripoService->downloadGlb($remoteModelUrl, $absoluteGlbPath);

                    $dish->glb_path = $glbFilename;
                    $dish->glb_url = asset('storage/' . $glbFilename);
                }

                $dish->thumbnail_url = $renderedImage ?? $dish->thumbnail_url;
                $dish->status = 'success';
                $dish->progress = 100;
                $dish->error_message = null;
            } elseif (in_array($remoteStatus, ['failed', 'cancelled'])) {
                $dish->status = 'failed';
                $dish->error_message = $taskData['error_message'] ?? $taskData['message'] ?? "El procesamiento de Tripo AI V3 finalizó con estado: {$remoteStatus}";
            } else {
                $dish->status = $remoteStatus;
            }

            $dish->save();

            return response()->json([
                'success' => true,
                'data' => $dish,
            ]);
        } catch (Exception $e) {
            Log::error('Error checking Tripo V3 task status', [
                'dish_id' => $dish->id,
                'tripo_task_id' => $dish->tripo_task_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar estado en Tripo AI V3: ' . $e->getMessage(),
                'data' => $dish,
            ], 500);
        }
    }

    /**
     * Direct endpoint to serve the GLB model with proper MIME type.
     */
    public function serveModel(string $id): BinaryFileResponse|JsonResponse
    {
        $dish = DishModel::findOrFail($id);

        if (!$dish->glb_path || !Storage::disk('public')->exists($dish->glb_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo 3D GLB aún no está disponible para este plato.',
            ], 404);
        }

        $path = Storage::disk('public')->path($dish->glb_path);

        return response()->file($path, [
            'Content-Type' => 'model/gltf-binary',
            'Content-Disposition' => 'inline; filename="' . $dish->id . '.glb"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        ]);
    }
}
