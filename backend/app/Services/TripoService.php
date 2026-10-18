<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
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
        $balance = isset($data['balance']) ? (float) $data['balance'] : 0.0;
        $frozen = isset($data['frozen']) ? (float) $data['frozen'] : 0.0;

        return [
            'balance' => $balance,
            'frozen' => $frozen,
            'available_credits' => $balance,
            'frozen_credits' => $frozen,
            'total_credits' => $balance + $frozen,
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
     * Normalize and validate multiview inputs for Tripo V3 API.
     * Ensures:
     * - Between 2 and 4 images
     * - 'front' view is mandatory
     * - Only allowed angles: 'front', 'left', 'back', 'right'
     * - No duplicate angles
     * - Tokens are non-empty valid strings
     *
     * @param array $views
     * @return array Associative array of ['front' => 'token', 'left' => 'token', ...]
     * @throws InvalidArgumentException
     */
    public function normalizeMultiviewInputs(array $views): array
    {
        $allowedAngles = ['front', 'left', 'back', 'right'];
        $normalized = [];

        foreach ($views as $key => $item) {
            $angle = null;
            $token = null;

            if (is_string($key) && in_array(strtolower($key), $allowedAngles)) {
                // Formats: 'front' => 'token' OR 'front' => ['file_token' => 'token']
                $angle = strtolower($key);
                if (is_string($item)) {
                    $token = trim($item);
                } elseif (is_array($item)) {
                    $token = trim((string) ($item['file_token'] ?? $item['token'] ?? $item['image_token'] ?? ''));
                }
            } elseif (is_array($item)) {
                if (isset($item['angle'])) {
                    // Format: ['angle' => 'front', 'file_token' => 'token']
                    $angle = strtolower(trim((string) $item['angle']));
                    $token = trim((string) ($item['file_token'] ?? $item['token'] ?? $item['image_token'] ?? ''));
                } else {
                    // Format: ['front' => 'token']
                    foreach ($allowedAngles as $possibleAngle) {
                        if (isset($item[$possibleAngle])) {
                            $angle = $possibleAngle;
                            $val = $item[$possibleAngle];
                            $token = is_array($val) ? trim((string) ($val['file_token'] ?? '')) : trim((string) $val);
                            break;
                        }
                    }
                }
            }

            if (!$angle || !in_array($angle, $allowedAngles)) {
                $reported = is_string($key) && !is_numeric($key) ? $key : (is_array($item) ? ($item['angle'] ?? json_encode($item)) : (string) $item);
                throw new InvalidArgumentException("Ángulo de vista no válido o no reconocido: '{$reported}'. Los ángulos permitidos son: front, left, back, right.");
            }

            if (isset($normalized[$angle])) {
                throw new InvalidArgumentException("Vista duplicada detectada: '{$angle}'. Cada perspectiva solo puede proporcionarse una vez.");
            }

            if (empty($token)) {
                throw new InvalidArgumentException("El token de archivo para la vista '{$angle}' está vacío o es inválido.");
            }

            $normalized[$angle] = $token;
        }

        $count = count($normalized);
        if ($count < 2 || $count > 4) {
            throw new InvalidArgumentException("La generación multivista requiere entre 2 y 4 fotografías distintas (se proporcionaron {$count}).");
        }

        if (!isset($normalized['front'])) {
            throw new InvalidArgumentException("La vista frontal ('front') es obligatoria para la generación multivista en Tripo V3.");
        }

        return $normalized;
    }

    /**
     * Calculate exact verified credit cost for a given mode, model, and texture options.
     *
     * @param string $mode 'standard' (1 photo) or 'multiview' (2-4 photos)
     * @param string $modelVersion
     * @param array $options ['texture_quality' => 'standard'|'detailed', 'pbr' => bool]
     * @return int
     */
    public function calculateCost(string $mode, string $modelVersion, array $options = []): int
    {
        $textureQuality = $options['texture_quality'] ?? 'standard';

        if ($mode === 'standard') {
            return $this->getRequiredCredits($modelVersion);
        }

        // Multiview mode:
        if (in_array($modelVersion, ['v3.1-20260211', 'v3.0-20250812'])) {
            return ($textureQuality === 'detailed') ? 40 : 30;
        }

        if ($modelVersion === 'P1-20260311') {
            return 40;
        }

        return 30;
    }

    /**
     * Create a multiview-to-model 3D generation task using Tripo V3 API.
     * Endpoint: POST /v3/generation/multiview-to-model
     *
     * Supports between 2 and 4 views. The front view is mandatory.
     * Compatible with Tripo V3 payload structure and parameter constraints.
     *
     * @param array $views Array of views:
     *                     - [['angle' => 'front', 'file_token' => '...'], ...]
     *                     - ['front' => 'token', 'left' => 'token', ...]
     *                     - [['front' => 'token'], ['left' => 'token'], ...]
     * @param array $options [
     *                         'model_version' => 'v3.1-20260211',
     *                         'texture' => true,
     *                         'pbr' => true,
     *                         'texture_quality' => 'standard'|'detailed',
     *                         'texture_alignment' => 'original_image'|'geometry',
     *                         'auto_size' => true,
     *                         'enable_image_autofix' => true,
     *                       ]
     * @return array ['task_id' => string, 'raw' => array, 'credits_estimated' => int, 'payload' => array]
     * @throws Exception
     */
    public function createMultiviewToModelTask(array $views, array $options = []): array
    {
        $this->ensureConfigured();

        $modelVersion = $options['model_version'] ?? $this->defaultModel;
        $textureQuality = $options['texture_quality'] ?? 'standard';
        $textureAlignment = $options['texture_alignment'] ?? 'original_image';
        $pbr = $options['pbr'] ?? true;
        $autoSize = $options['auto_size'] ?? true;

        // 1. Validate and normalize view inputs
        $normalizedViews = $this->normalizeMultiviewInputs($views);

        // 2. Validate model & texture compatibility
        if ($textureQuality === 'detailed' && !in_array($modelVersion, ['v3.0-20250812', 'v3.1-20260211'])) {
            throw new InvalidArgumentException("La calidad de textura 'detailed' no es compatible con el modelo '{$modelVersion}'. Requiere un modelo Tripo v3.0 o superior.");
        }

        // 3. Build ordered inputs array per Tripo V3 specification
        $inputs = [];
        $viewOrder = ['front', 'left', 'back', 'right'];
        foreach ($viewOrder as $angle) {
            if (isset($normalizedViews[$angle])) {
                $inputs[] = [
                    $angle => $normalizedViews[$angle],
                ];
            }
        }

        // 4. Build exact V3 payload
        $payload = [
            'model_version' => $modelVersion,
            'inputs' => $inputs,
            'texture' => (bool) ($options['texture'] ?? true),
            'pbr' => (bool) $pbr,
            'auto_size' => (bool) $autoSize,
        ];

        if (in_array($modelVersion, ['v3.0-20250812', 'v3.1-20260211'])) {
            $payload['texture_quality'] = $textureQuality;
            $payload['texture_alignment'] = $textureAlignment;
            $payload['enable_image_autofix'] = (bool) ($options['enable_image_autofix'] ?? true);
        }

        $estimatedCost = $this->calculateCost('multiview', $modelVersion, $options);

        // 5. Dispatch HTTP POST to Tripo V3
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/generation/multiview-to-model", $payload);

        if (!$response->successful()) {
            Log::error('Tripo AI V3 Create Multiview Task Failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'payload' => $payload,
            ]);
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new Exception("Error al crear tarea multivista en Tripo AI V3: {$msg}");
        }

        $json = $response->json();
        if (($json['code'] ?? -1) !== 0) {
            $msg = $json['message'] ?? 'Error desconocido';
            throw new Exception("Tripo AI V3 devolvió error al despachar tarea multivista: {$msg}");
        }

        $taskId = $json['data']['task_id'] ?? null;
        if (!$taskId) {
            throw new Exception("La respuesta de Tripo AI V3 no incluyó el 'task_id'.");
        }

        return [
            'task_id' => $taskId,
            'raw' => $json,
            'credits_estimated' => $estimatedCost,
            'payload' => $payload,
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
                'name' => 'Tripo v3.1 (Máxima Calidad PBR)',
                'description' => 'SOTA gastronómico: mayor detalle geométrico, texturas PBR refinadas y definición de vajilla.',
                'credits' => 30,
                'recommended' => true,
            ],
            [
                'id' => 'v3.0-20250812',
                'name' => 'Tripo v3.0 (Calidad Alta PBR)',
                'description' => 'Alta fidelidad volumétrica para platos con múltiples ingredientes.',
                'credits' => 30,
                'recommended' => false,
            ],
            [
                'id' => 'v2.5-20250123',
                'name' => 'Tripo v2.5 (Estándar Estable PBR)',
                'description' => 'Generación balanceada y estable con texturas PBR.',
                'credits' => 30,
                'recommended' => false,
            ],
            [
                'id' => 'P1-20260311',
                'name' => 'Tripo P1 (Clean Topology / Web)',
                'description' => 'Topología limpia para motores de juego, web y AR (textura estándar).',
                'credits' => 40,
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
