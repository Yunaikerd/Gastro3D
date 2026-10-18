<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripoService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;

    public function __construct()
    {
        $this->apiKey = (string) config('services.tripo.api_key', env('TRIPO_API_KEY', ''));
        $this->baseUrl = rtrim((string) config('services.tripo.base_url', 'https://openapi.tripo3d.ai/v3'), '/');
        $this->defaultModel = (string) config('services.tripo.default_model', 'v3.1-20260211');
    }

    /**
     * Check if Tripo API credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get user balance and credit information using Tripo V3 API.
     * Endpoint: GET /v3/account/balance
     * Does NOT consume credits.
     *
     * @return array ['balance' => float, 'frozen' => float]
     * @throws Exception
     */
    public function getBalance(): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->apiKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/account/balance");

        if (!$response->successful()) {
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new Exception("Error al consultar saldo en Tripo AI V3: {$msg}");
        }

        $json = $response->json();
        if (($json['code'] ?? -1) !== 0) {
            $msg = $json['message'] ?? 'Código de error inesperado';
            throw new Exception("Tripo AI V3 error de saldo: {$msg}");
        }

        $data = $json['data'] ?? [];

        return [
            'balance' => isset($data['balance']) ? (float) $data['balance'] : 0.0,
            'frozen' => isset($data['frozen']) ? (float) $data['frozen'] : 0.0,
        ];
    }

    /**
     * Upload an image to Tripo AI V3 and obtain a file_token.
     * Endpoint: POST /v3/files (multipart form-data with field 'file')
     *
     * @param string $absolutePath
     * @return string file_token (e.g. file_abc123)
     * @throws Exception
     */
    public function uploadImage(string $absolutePath): string
    {
        $this->ensureConfigured();

        if (!file_exists($absolutePath) || !is_readable($absolutePath)) {
            throw new Exception("El archivo de imagen no existe o no se puede leer en la ruta: {$absolutePath}");
        }

        $filename = basename($absolutePath);
        $fileContents = file_get_contents($absolutePath);

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->attach('file', $fileContents, $filename)
            ->post("{$this->baseUrl}/files");

        if (!$response->successful()) {
            Log::error('Tripo AI V3 Upload Failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new Exception("Error al subir imagen a Tripo AI V3: {$msg}");
        }

        $json = $response->json();
        if (($json['code'] ?? -1) !== 0) {
            $msg = $json['message'] ?? 'Error desconocido';
            throw new Exception("Tripo AI V3 devolvió código de error al subir archivo: {$msg}");
        }

        // V3 uses 'file_token' (format: file_<uuid>) with fallback to 'image_token'
        $token = $json['data']['file_token'] ?? $json['data']['image_token'] ?? null;
        if (!$token) {
            throw new Exception("La respuesta de Tripo AI V3 no incluyó el 'file_token'.");
        }

        return $token;
    }

    /**
     * Create an image-to-model 3D generation task using Tripo V3 API.
     * Endpoint: POST /v3/generation/image-to-model
     * Notice: In V3, the 'type' field is omitted; 'model_version' is REQUIRED.
     *
     * @param string $fileToken
     * @param array $options
     * @return array ['task_id' => string, 'raw' => array]
     * @throws Exception
     */
    public function createImageToModelTask(string $fileToken, array $options = []): array
    {
        $this->ensureConfigured();

        $modelVersion = $options['model_version'] ?? $this->defaultModel;
        $fileType = $options['file_type'] ?? 'png';

        $payload = [
            'model_version' => $modelVersion,
            'file' => [
                'type' => $fileType,
                'file_token' => $fileToken,
            ],
            'texture' => true,
            'pbr' => true,
            'enable_image_autofix' => true,
        ];

        // Model P1 requires omitting certain fields
        if (str_starts_with($modelVersion, 'P1')) {
            unset($payload['enable_image_autofix']);
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/generation/image-to-model", $payload);

        if (!$response->successful()) {
            Log::error('Tripo AI V3 Create Task Failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new Exception("Error al crear tarea en Tripo AI V3: {$msg}");
        }

        $json = $response->json();
        if (($json['code'] ?? -1) !== 0) {
            $msg = $json['message'] ?? 'Error desconocido';
            throw new Exception("Tripo AI V3 devolvió error al despachar tarea: {$msg}");
        }

        $taskId = $json['data']['task_id'] ?? null;
        if (!$taskId) {
            throw new Exception("La respuesta de Tripo AI V3 no incluyó el 'task_id'.");
        }

        return [
            'task_id' => $taskId,
            'raw' => $json,
        ];
    }

    /**
     * Query task status from Tripo V3 API.
     * Endpoint: GET /v3/tasks/{taskId}
     *
     * In V3:
     * - Status is one of: queued, running, success, failed, cancelled
     * - Output model link is unified under 'output.model_url'
     * - Output preview is 'output.rendered_image_url'
     * - Credits consumed is 'credits_consumed'
     *
     * @param string $taskId
     * @return array
     * @throws Exception
     */
    public function getTaskStatus(string $taskId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/tasks/{$taskId}");

        if (!$response->successful()) {
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new Exception("Error al consultar tarea en Tripo AI V3: {$msg}");
        }

        $json = $response->json();
        if (($json['code'] ?? -1) !== 0) {
            $msg = $json['message'] ?? 'Error desconocido';
            throw new Exception("Error devuelto por Tripo AI V3: {$msg}");
        }

        return $json['data'] ?? [];
    }

    /**
     * Download a generated 3D model (GLB) from signed URL and store locally.
     *
     * @param string $remoteUrl
     * @param string $destinationAbsolutePath
     * @return bool
     * @throws Exception
     */
    public function downloadGlb(string $remoteUrl, string $destinationAbsolutePath): bool
    {
        $dir = dirname($destinationAbsolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $response = Http::timeout(120)->sink($destinationAbsolutePath)->get($remoteUrl);

        if (!$response->successful()) {
            if (file_exists($destinationAbsolutePath)) {
                @unlink($destinationAbsolutePath);
            }
            throw new Exception("No se pudo descargar el archivo GLB desde {$remoteUrl} (HTTP {$response->status()})");
        }

        if (!file_exists($destinationAbsolutePath) || filesize($destinationAbsolutePath) < 100) {
            throw new Exception("El archivo GLB descargado está vacío o corrupto.");
        }

        return true;
    }

    /**
     * Return available AI models with descriptions and credit costs.
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        return [
            [
                'id' => 'v3.1-20260211',
                'name' => 'Tripo v3.1 (Máxima Calidad)',
                'description' => 'SOTA gastronómico: mayor detalle geométrico, texturas PBR refinadas y definición de vajilla.',
                'credits' => 30,
                'recommended' => true,
            ],
            [
                'id' => 'v3.0-20250812',
                'name' => 'Tripo v3.0 (Calidad Alta)',
                'description' => 'Alta fidelidad volumétrica para platos con múltiples ingredientes.',
                'credits' => 30,
                'recommended' => false,
            ],
            [
                'id' => 'v2.5-20250123',
                'name' => 'Tripo v2.5 (Estándar Estable)',
                'description' => 'Generación balanceada y estable.',
                'credits' => 25,
                'recommended' => false,
            ],
            [
                'id' => 'P1-20260311',
                'name' => 'Tripo P1 (Low-Poly / Web Rápido)',
                'description' => 'Optimizado para bajo peso de malla y carga instantánea en móviles y AR.',
                'credits' => 20,
                'recommended' => false,
            ],
        ];
    }

    /**
     * Get credit cost for a given model version.
     */
    public function getRequiredCredits(string $modelVersion): int
    {
        foreach ($this->getAvailableModels() as $model) {
            if ($model['id'] === $modelVersion) {
                return $model['credits'];
            }
        }

        return 30; // default safe estimation
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new Exception("TRIPO_API_KEY no está configurada en las variables de entorno.");
        }
    }
}
