<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DishGeneration;
use App\Models\DishModel;
use App\Services\TripoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
                'available_credits' => $balanceData['available_credits'],
                'frozen_credits' => $balanceData['frozen_credits'],
                'total_credits' => $balanceData['total_credits'],
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
     * Calculate credit cost estimate and verify user balance.
     */
    public function costEstimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:standard,multiview'],
            'model_version' => ['nullable', 'string', 'max:50'],
            'texture_quality' => ['nullable', 'string', 'in:standard,detailed'],
        ]);

        $mode = $validated['mode'] ?? 'multiview';
        $modelVersion = $validated['model_version'] ?? config('services.tripo.default_model', 'v3.1-20260211');
        $textureQuality = $validated['texture_quality'] ?? 'detailed';

        $requiredCredits = $this->tripoService->calculateCost($mode, $modelVersion, [
            'texture_quality' => $textureQuality,
        ]);

        $balanceData = ['balance' => 0.0, 'frozen' => 0.0];
        $canAfford = false;

        if ($this->tripoService->isConfigured()) {
            try {
                $balanceData = $this->tripoService->getBalance();
                $canAfford = ($balanceData['balance'] >= $requiredCredits);
            } catch (Exception $e) {
                // Return estimated cost even if external balance check fails
            }
        }

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'model_version' => $modelVersion,
            'texture_quality' => $textureQuality,
            'required_credits' => $requiredCredits,
            'available_credits' => $balanceData['balance'] ?? 0.0,
            'frozen_credits' => $balanceData['frozen'] ?? 0.0,
            'can_afford' => $canAfford,
        ]);
    }

    /**
     * List all 3D dish generation jobs.
     */
    public function index(): JsonResponse
    {
        $dishes = DishModel::with('activeGeneration')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dishes,
        ]);
    }

    /**
     * Upload single dish photograph and create pending 3D job (Version 1).
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

        $result = DB::transaction(function () use ($validated, $originalFilename, $storedPath, $imageUrl, $mimeType, $sizeBytes, $modelVersion) {
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

            $dish->generations()->create([
                'version_number' => 1,
                'mode' => 'standard',
                'model_version' => $modelVersion,
                'texture_quality' => 'standard',
                'texture_alignment' => 'original_image',
                'pbr' => true,
                'views' => [
                    [
                        'angle' => 'front',
                        'image_path' => $storedPath,
                        'image_url' => $imageUrl,
                        'original_filename' => $originalFilename,
                        'mime_type' => $mimeType,
                        'file_size_bytes' => $sizeBytes,
                        'file_token' => null,
                    ]
                ],
                'status' => 'pending',
                'progress' => 0,
                'is_active' => true,
            ]);

            return $dish;
        });

        return response()->json([
            'success' => true,
            'message' => 'Fotografía subida correctamente. Trabajo registrado en estado pendiente sin consumo de créditos.',
            'data' => $result->load('activeGeneration'),
        ], 201);
    }

    /**
     * Upload multiview photographs (2-4 photos) and create pending multiview dish (Version 1).
     */
    public function uploadMultiview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'prompt' => ['nullable', 'string', 'max:500'],
            'model_version' => ['nullable', 'string', 'max:50'],
            'texture_quality' => ['nullable', 'string', 'in:standard,detailed'],
            'texture_alignment' => ['nullable', 'string', 'in:original_image,geometry'],
        ]);

        $allowedAngles = ['front', 'left', 'back', 'right'];
        $uploadedViews = [];

        // 1. Inspect images[angle] format
        $allFiles = $request->allFiles();
        if (isset($allFiles['images']) && is_array($allFiles['images'])) {
            foreach ($allFiles['images'] as $angle => $file) {
                if (in_array(strtolower($angle), $allowedAngles) && $file->isValid()) {
                    $uploadedViews[strtolower($angle)] = $file;
                }
            }
        }

        // 2. Inspect fallback flat fields (images_front, images_left, etc.)
        foreach ($allowedAngles as $angle) {
            if (!isset($uploadedViews[$angle]) && $request->hasFile("images_{$angle}")) {
                $file = $request->file("images_{$angle}");
                if ($file->isValid()) {
                    $uploadedViews[$angle] = $file;
                }
            }
        }

        // 3. Front view is strictly required
        if (!isset($uploadedViews['front'])) {
            return response()->json([
                'success' => false,
                'message' => "La vista frontal ('front') es obligatoria para la reconstrucción 3D multivista en Tripo V3.",
            ], 422);
        }

        // 4. Require between 2 and 4 views total
        $count = count($uploadedViews);
        if ($count < 2 || $count > 4) {
            return response()->json([
                'success' => false,
                'message' => "La generación multivista requiere entre 2 y 4 fotografías distintas (se recibieron {$count}). Debe incluir 'front' y al menos una vista adicional ('left', 'back' o 'right').",
            ], 422);
        }

        // 5. Validate file formats (jpeg, jpg, png, webp) and max size (10MB)
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($uploadedViews as $angle => $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = $file->getClientMimeType();
            $size = $file->getSize();

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'message' => "Formato no válido en la vista '{$angle}'. Solo se permiten imágenes JPEG, PNG o WebP.",
                ], 422);
            }

            if ($size > 10 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => "La imagen de la vista '{$angle}' excede el tamaño máximo permitido de 10 MB.",
                ], 422);
            }
        }

        // 6. Save images persistently
        $dishUuid = (string) Str::uuid();
        $viewsData = [];
        $frontStoredPath = null;
        $frontOriginalName = null;
        $frontMime = null;
        $frontSize = null;

        foreach ($uploadedViews as $angle => $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = "{$dishUuid}_{$angle}.{$ext}";
            $storedPath = $file->storeAs('uploads/multiview', $filename, 'public');
            $imageUrl = asset('storage/' . $storedPath);

            $viewsData[] = [
                'angle' => $angle,
                'image_path' => $storedPath,
                'image_url' => $imageUrl,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'file_token' => null,
            ];

            if ($angle === 'front') {
                $frontStoredPath = $storedPath;
                $frontOriginalName = $file->getClientOriginalName();
                $frontMime = $file->getClientMimeType();
                $frontSize = $file->getSize();
            }
        }

        $modelVersion = $validated['model_version'] ?? config('services.tripo.default_model', 'v3.1-20260211');
        $textureQuality = $validated['texture_quality'] ?? 'detailed';
        $textureAlignment = $validated['texture_alignment'] ?? 'original_image';

        $result = DB::transaction(function () use ($dishUuid, $validated, $frontOriginalName, $frontStoredPath, $frontMime, $frontSize, $modelVersion, $textureQuality, $textureAlignment, $viewsData) {
            $dish = DishModel::create([
                'id' => $dishUuid,
                'title' => $validated['title'] ?? pathinfo($frontOriginalName, PATHINFO_FILENAME),
                'prompt' => $validated['prompt'] ?? null,
                'original_filename' => $frontOriginalName,
                'image_path' => $frontStoredPath,
                'image_url' => asset('storage/' . $frontStoredPath),
                'mime_type' => $frontMime,
                'file_size_bytes' => $frontSize,
                'status' => 'pending',
                'progress' => 0,
                'model_version' => $modelVersion,
            ]);

            $generation = $dish->generations()->create([
                'version_number' => 1,
                'mode' => 'multiview',
                'model_version' => $modelVersion,
                'texture_quality' => $textureQuality,
                'texture_alignment' => $textureAlignment,
                'pbr' => true,
                'views' => $viewsData,
                'status' => 'pending',
                'progress' => 0,
                'is_active' => true,
            ]);

            return ['dish' => $dish, 'generation' => $generation];
        });

        return response()->json([
            'success' => true,
            'message' => 'Fotografías multivista subidas correctamente. Plato registrado en estado pendiente sin consumo de créditos.',
            'data' => $result['dish']->load('activeGeneration'),
        ], 201);
    }

    /**
     * Get details of a single dish with its active generation.
     */
    public function show(string $id): JsonResponse
    {
        $dish = DishModel::with('activeGeneration')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dish,
        ]);
    }

    /**
     * List all generations / versions for a dish.
     */
    public function listVersions(string $dishId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $versions = $dish->generations()->orderBy('version_number', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $versions,
        ]);
    }

    /**
     * Create a new generation version for an existing dish without overwriting previous versions.
     */
    public function createVersion(Request $request, string $dishId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);

        $validated = $request->validate([
            'model_version' => ['nullable', 'string', 'max:50'],
            'texture_quality' => ['nullable', 'string', 'in:standard,detailed'],
            'texture_alignment' => ['nullable', 'string', 'in:original_image,geometry'],
            'make_active' => ['nullable', 'boolean'],
        ]);

        $makeActive = $request->boolean('make_active', false);
        $modelVersion = $validated['model_version'] ?? $dish->model_version ?? config('services.tripo.default_model', 'v3.1-20260211');
        $textureQuality = $validated['texture_quality'] ?? 'detailed';
        $textureAlignment = $validated['texture_alignment'] ?? 'original_image';

        $allFiles = $request->allFiles();
        $isMultiview = isset($allFiles['images']) || $request->hasFile('images_front');

        if ($isMultiview) {
            $allowedAngles = ['front', 'left', 'back', 'right'];
            $uploadedViews = [];

            if (isset($allFiles['images']) && is_array($allFiles['images'])) {
                foreach ($allFiles['images'] as $angle => $file) {
                    if (in_array(strtolower($angle), $allowedAngles) && $file->isValid()) {
                        $uploadedViews[strtolower($angle)] = $file;
                    }
                }
            }

            foreach ($allowedAngles as $angle) {
                if (!isset($uploadedViews[$angle]) && $request->hasFile("images_{$angle}")) {
                    $file = $request->file("images_{$angle}");
                    if ($file->isValid()) {
                        $uploadedViews[$angle] = $file;
                    }
                }
            }

            if (!isset($uploadedViews['front'])) {
                return response()->json([
                    'success' => false,
                    'message' => "La vista frontal ('front') es obligatoria para la versión multivista.",
                ], 422);
            }

            $count = count($uploadedViews);
            if ($count < 2 || $count > 4) {
                return response()->json([
                    'success' => false,
                    'message' => "La generación multivista requiere entre 2 y 4 fotografías distintas (se recibieron {$count}).",
                ], 422);
            }

            $viewsData = [];
            $vUuid = (string) Str::uuid();
            foreach ($uploadedViews as $angle => $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $filename = "{$vUuid}_{$angle}.{$ext}";
                $storedPath = $file->storeAs('uploads/multiview', $filename, 'public');

                $viewsData[] = [
                    'angle' => $angle,
                    'image_path' => $storedPath,
                    'image_url' => asset('storage/' . $storedPath),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'file_token' => null,
                ];
            }

            $generation = $dish->createNewVersion([
                'mode' => 'multiview',
                'model_version' => $modelVersion,
                'texture_quality' => $textureQuality,
                'texture_alignment' => $textureAlignment,
                'pbr' => true,
                'views' => $viewsData,
                'status' => 'pending',
                'progress' => 0,
                'is_active' => $makeActive,
            ]);

        } else {
            $request->validate([
                'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            ]);

            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = (string) Str::uuid() . ".{$ext}";
            $storedPath = $file->storeAs('uploads', $filename, 'public');

            $generation = $dish->createNewVersion([
                'mode' => 'standard',
                'model_version' => $modelVersion,
                'texture_quality' => $textureQuality,
                'texture_alignment' => $textureAlignment,
                'pbr' => true,
                'views' => [
                    [
                        'angle' => 'front',
                        'image_path' => $storedPath,
                        'image_url' => asset('storage/' . $storedPath),
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'file_size_bytes' => $file->getSize(),
                        'file_token' => null,
                    ]
                ],
                'status' => 'pending',
                'progress' => 0,
                'is_active' => $makeActive,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Versión #{$generation->version_number} creada exitosamente sin sobrescribir modelos anteriores.",
            'data' => $generation,
        ], 201);
    }

    /**
     * Resolve a generation safely in PostgreSQL by UUID or numeric version number.
     */
    protected function resolveGeneration(DishModel $dish, string $versionId): DishGeneration
    {
        if (is_numeric($versionId)) {
            return $dish->generations()
                ->where('version_number', (int) $versionId)
                ->firstOrFail();
        }

        return $dish->generations()
            ->where('id', $versionId)
            ->firstOrFail();
    }

    /**
     * Show a specific generation version.
     */
    public function showVersion(string $dishId, string $versionId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $generation = $this->resolveGeneration($dish, $versionId);

        return response()->json([
            'success' => true,
            'data' => $generation,
        ]);
    }

    /**
     * Activate a specific generation version with atomic transaction and concurrency locking.
     */
    public function activateVersion(string $dishId, string $versionId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $generation = $this->resolveGeneration($dish, $versionId);

        $generation->activate();

        return response()->json([
            'success' => true,
            'message' => "Versión #{$generation->version_number} activada correctamente para el plato.",
            'data' => $generation->fresh(),
            'dish' => $dish->fresh(),
        ]);
    }

    /**
     * Dispatch 3D generation task for a specific version with balance check and anti-concurrency guards.
     */
    public function generateVersion(Request $request, string $dishId, string $versionId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $generation = $this->resolveGeneration($dish, $versionId);

        // 1. Guard against duplicate or running tasks on this version
        if (in_array($generation->status, ['uploading', 'queued', 'running', 'success'])) {
            $msg = ($generation->status === 'success')
                ? 'Esta versión ya fue generada exitosamente.'
                : "Esta versión ya se encuentra en proceso (Estado actual: {$generation->status}).";

            return response()->json([
                'success' => false,
                'message' => $msg,
                'data' => $generation,
            ], 409);
        }

        // 2. Guard against multiple simultaneous generations for the same dish
        $hasActiveRunning = $dish->generations()
            ->whereIn('status', ['uploading', 'queued', 'running'])
            ->where('id', '!=', $generation->id)
            ->exists();

        if ($hasActiveRunning) {
            return response()->json([
                'success' => false,
                'message' => 'El plato ya tiene otra versión ejecutándose en este momento. Espere a que finalice.',
                'data' => $generation,
            ], 409);
        }

        // 3. Acquire atomic mutex lock on this dish
        $lock = Cache::lock("generate_dish_{$dish->id}", 25);
        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Existe una solicitud simultánea en curso para este plato. Intente de nuevo en unos segundos.',
            ], 429);
        }

        $modelVersion = $request->input('model_version', $generation->model_version ?: 'v3.1-20260211');
        $textureQuality = $request->input('texture_quality', $generation->texture_quality ?: 'detailed');
        $textureAlignment = $request->input('texture_alignment', $generation->texture_alignment ?: 'original_image');

        $requiredCredits = $this->tripoService->calculateCost($generation->mode, $modelVersion, [
            'texture_quality' => $textureQuality,
        ]);

        if (!$this->tripoService->isConfigured()) {
            $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'TRIPO_API_KEY no está configurada en el servidor backend.',
            ], 500);
        }

        // 4. Verify balance before triggering any generation
        try {
            $balanceData = $this->tripoService->getBalance();
            $currentBalance = (float) $balanceData['balance'];

            if ($currentBalance < $requiredCredits) {
                $lock->release();
                return response()->json([
                    'success' => false,
                    'insufficient_balance' => true,
                    'current_balance' => $currentBalance,
                    'available_credits' => $currentBalance,
                    'frozen_credits' => (float) $balanceData['frozen'],
                    'required_credits' => $requiredCredits,
                    'mode' => $generation->mode,
                    'model_version' => $modelVersion,
                    'message' => "Saldo insuficiente en tu API Wallet de Tripo AI. Tu saldo disponible es de {$currentBalance} créditos y la configuración seleccionada ({$generation->mode}, {$textureQuality}) requiere {$requiredCredits} créditos.",
                    'data' => $generation,
                ], 402);
            }
        } catch (Exception $e) {
            $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'No se pudo verificar el saldo con Tripo AI V3: ' . $e->getMessage(),
                'data' => $generation,
            ], 500);
        }

        try {
            $generation->update([
                'status' => 'uploading',
                'progress' => 5,
                'model_version' => $modelVersion,
                'texture_quality' => $textureQuality,
                'texture_alignment' => $textureAlignment,
                'error_message' => null,
            ]);

            if ($generation->is_active) {
                $dish->update([
                    'status' => 'uploading',
                    'progress' => 5,
                    'model_version' => $modelVersion,
                    'error_message' => null,
                ]);
            }

            if ($generation->mode === 'multiview') {
                $views = $generation->views ?? [];
                $tripoViews = [];

                foreach ($views as &$view) {
                    $angle = $view['angle'];
                    $token = $view['file_token'] ?? null;

                    if (!$token) {
                        $absPath = Storage::disk('public')->path($view['image_path']);
                        if (!file_exists($absPath)) {
                            throw new Exception("El archivo de imagen para la vista '{$angle}' no se encuentra en el almacenamiento local.");
                        }
                        $token = $this->tripoService->uploadImage($absPath);
                        $view['file_token'] = $token;
                    }

                    $tripoViews[] = [
                        'angle' => $angle,
                        'file_token' => $token,
                    ];
                }

                $generation->views = $views;
                $generation->progress = 15;
                $generation->save();

                $taskResult = $this->tripoService->createMultiviewToModelTask($tripoViews, [
                    'model_version' => $modelVersion,
                    'texture_quality' => $textureQuality,
                    'texture_alignment' => $textureAlignment,
                    'pbr' => $generation->pbr,
                    'auto_size' => true,
                ]);

            } else {
                $absPath = Storage::disk('public')->path($dish->image_path);
                $token = $dish->tripo_file_token;
                if (!$token) {
                    $token = $this->tripoService->uploadImage($absPath);
                    $dish->update(['tripo_file_token' => $token]);
                }

                $ext = pathinfo($dish->original_filename, PATHINFO_EXTENSION) ?: 'png';
                $taskResult = $this->tripoService->createImageToModelTask($token, [
                    'file_type' => strtolower($ext) === 'jpg' ? 'jpeg' : strtolower($ext),
                    'model_version' => $modelVersion,
                ]);
            }

            $generation->recordTask($taskResult['task_id'], $requiredCredits, $taskResult['raw']);

            return response()->json([
                'success' => true,
                'message' => 'Generación 3D iniciada exitosamente en Tripo AI V3.',
                'data' => $generation->fresh(),
            ]);

        } catch (Exception $e) {
            Log::error('Tripo generation task creation failed', [
                'dish_id' => $dish->id,
                'version_id' => $generation->id,
                'error' => $e->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($generation->is_active) {
                $dish->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al comunicarse con Tripo AI V3: ' . $e->getMessage(),
                'data' => $generation->fresh(),
            ], 500);

        } finally {
            $lock->release();
        }
    }

    /**
     * Backward-compatible generation endpoint (generates active or latest pending version).
     */
    public function generate(Request $request, string $id): JsonResponse
    {
        $dish = DishModel::findOrFail($id);

        $targetGen = $dish->activeGeneration;
        if (!$targetGen || in_array($targetGen->status, ['success', 'failed'])) {
            $targetGen = $dish->generations()->where('status', 'pending')->latest('created_at')->first();
        }

        if (!$targetGen) {
            // Seed Version 1 if legacy dish
            $targetGen = $dish->createNewVersion([
                'mode' => 'standard',
                'status' => $dish->status ?? 'pending',
                'progress' => (int) ($dish->progress ?? 0),
                'tripo_task_id' => $dish->tripo_task_id,
                'is_active' => true,
            ]);
        }

        $result = $this->generateVersion($request, $dish->id, $targetGen->id);
        if (!$result->isSuccessful()) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'message' => 'Generación 3D iniciada exitosamente en Tripo AI V3.',
            'data' => $dish->fresh()->load('activeGeneration'),
            'generation' => $targetGen->fresh(),
        ]);
    }

    /**
     * Poll status of a specific generation version with debounce, polling concurrency lock, and idempotent GLB download.
     */
    public function checkVersionStatus(string $dishId, string $versionId): JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $generation = $this->resolveGeneration($dish, $versionId);

        // 1. If already completed with GLB file on disk, return immediately
        if ($generation->status === 'success' && $generation->glb_path && Storage::disk('public')->exists($generation->glb_path)) {
            return response()->json([
                'success' => true,
                'data' => $generation,
            ]);
        }

        // 2. If no task ID or permanently finished, return current state
        if (!$generation->tripo_task_id || in_array($generation->status, ['pending', 'failed', 'cancelled'])) {
            return response()->json([
                'success' => true,
                'data' => $generation,
            ]);
        }

        // 3. Debounce rapid polling (prevent hammering external Tripo API)
        $pollCacheKey = "tripo_poll_debounce_{$generation->tripo_task_id}";
        if (Cache::has($pollCacheKey)) {
            return response()->json([
                'success' => true,
                'data' => $generation,
                'debounced' => true,
            ]);
        }

        // 4. Concurrency lock to prevent overlapping status polling requests
        $pollLock = Cache::lock("tripo_poll_lock_{$generation->tripo_task_id}", 10);
        if (!$pollLock->get()) {
            return response()->json([
                'success' => true,
                'data' => $generation,
                'in_flight' => true,
            ]);
        }

        try {
            Cache::put($pollCacheKey, true, 2); // 2-second debounce window

            $taskData = $this->tripoService->getTaskStatus($generation->tripo_task_id);
            $remoteStatus = $taskData['status'] ?? 'unknown';
            $remoteProgress = (int) ($taskData['progress'] ?? $generation->progress);

            $generation->progress = max($generation->progress, $remoteProgress);
            $generation->meta = array_merge($generation->meta ?? [], [
                'latest_v3_task_poll' => $taskData,
                'credits_consumed' => $taskData['credits_consumed'] ?? null,
            ]);

            if ($remoteStatus === 'success') {
                $output = $taskData['output'] ?? [];
                $remoteModelUrl = $output['model_url'] ?? $output['pbr_model'] ?? $output['model'] ?? null;
                $renderedImage = $output['rendered_image_url'] ?? $output['rendered_image'] ?? null;

                if ($remoteModelUrl) {
                    $glbFilename = ($generation->version_number === 1 && $dish->glb_path)
                        ? $dish->glb_path
                        : "models/{$dish->id}_v{$generation->version_number}.glb";
                    $absoluteGlbPath = Storage::disk('public')->path($glbFilename);

                    // Idempotent download with lock
                    $dlLock = Cache::lock("download_glb_{$generation->id}", 60);
                    if ($dlLock->get()) {
                        try {
                            if (!file_exists($absoluteGlbPath) || filesize($absoluteGlbPath) < 1000) {
                                $this->tripoService->downloadGlb($remoteModelUrl, $absoluteGlbPath);
                            }
                        } finally {
                            $dlLock->release();
                        }
                    }

                    $generation->glb_path = $glbFilename;
                    $generation->glb_url = asset('storage/' . $glbFilename);
                }

                if ($renderedImage) {
                    $thumbFilename = "models/{$dish->id}_v{$generation->version_number}_thumb.webp";
                    $absoluteThumbPath = Storage::disk('public')->path($thumbFilename);
                    if (!file_exists($absoluteThumbPath)) {
                        try {
                            $thumbResponse = Http::timeout(15)->get($renderedImage);
                            if ($thumbResponse->successful()) {
                                Storage::disk('public')->put($thumbFilename, $thumbResponse->body());
                                $generation->thumbnail_url = asset('storage/' . $thumbFilename);
                            } else {
                                $generation->thumbnail_url = $renderedImage;
                            }
                        } catch (Exception $e) {
                            $generation->thumbnail_url = $renderedImage;
                        }
                    } else {
                        $generation->thumbnail_url = asset('storage/' . $thumbFilename);
                    }
                }

                $generation->status = 'success';
                $generation->progress = 100;
                $generation->error_message = null;

            } elseif (in_array($remoteStatus, ['failed', 'cancelled'])) {
                $generation->status = 'failed';
                $generation->error_message = $taskData['error_message'] ?? $taskData['message'] ?? "El procesamiento de Tripo AI V3 finalizó con estado: {$remoteStatus}";
            } else {
                $generation->status = $remoteStatus;
            }

            $generation->save();

            // Sync parent dish if active
            if ($generation->is_active) {
                $dish->update([
                    'status' => $generation->status,
                    'progress' => $generation->progress,
                    'glb_path' => $generation->glb_path,
                    'glb_url' => $generation->glb_url,
                    'thumbnail_url' => $generation->thumbnail_url,
                    'tripo_task_id' => $generation->tripo_task_id,
                    'error_message' => $generation->error_message,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $generation,
            ]);

        } catch (Exception $e) {
            Log::error('Error checking Tripo V3 version status', [
                'dish_id' => $dish->id,
                'version_id' => $generation->id,
                'tripo_task_id' => $generation->tripo_task_id,
                'error' => $e->getMessage(),
            ]);

            // Notice: transient network polling errors do NOT mark task as failed
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar estado en Tripo AI V3: ' . $e->getMessage(),
                'data' => $generation,
            ], 500);

        } finally {
            $pollLock->release();
        }
    }

    /**
     * Backward-compatible checkStatus endpoint (delegates to active generation).
     */
    public function checkStatus(string $id): JsonResponse
    {
        $dish = DishModel::findOrFail($id);
        $activeGen = $dish->activeGeneration;

        if ($activeGen) {
            return $this->checkVersionStatus($id, $activeGen->id);
        }

        // Fallback for legacy dish
        return response()->json([
            'success' => true,
            'data' => $dish,
        ]);
    }

    /**
     * Direct endpoint to serve a specific version's GLB model with proper MIME type.
     */
    public function serveVersionModel(string $dishId, string $versionId): BinaryFileResponse|JsonResponse
    {
        $dish = DishModel::findOrFail($dishId);
        $generation = $this->resolveGeneration($dish, $versionId);

        if (!$generation->glb_path || !Storage::disk('public')->exists($generation->glb_path)) {
            return response()->json([
                'success' => false,
                'message' => "El archivo 3D GLB aún no está disponible para la versión #{$generation->version_number}.",
            ], 404);
        }

        $path = Storage::disk('public')->path($generation->glb_path);

        return response()->file($path, [
            'Content-Type' => 'model/gltf-binary',
            'Content-Disposition' => 'inline; filename="' . $dish->id . '_v' . $generation->version_number . '.glb"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        ]);
    }

    /**
     * Direct endpoint to serve the active GLB model (backward compatible).
     */
    public function serveModel(string $id): BinaryFileResponse|JsonResponse
    {
        $dish = DishModel::findOrFail($id);
        $activeGen = $dish->activeGeneration;

        if ($activeGen && $activeGen->glb_path && Storage::disk('public')->exists($activeGen->glb_path)) {
            return $this->serveVersionModel($id, $activeGen->id);
        }

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
