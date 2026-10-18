<?php

namespace Tests\Feature;

use App\Models\DishModel;
use App\Services\TripoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DishGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_healthy_with_postgres(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'service' => 'Gastro3D Backend API',
                'database' => [
                    'status' => 'connected',
                    'driver' => 'pgsql',
                    'error' => null,
                ],
            ]);
    }

    public function test_upload_dish_requires_image(): void
    {
        $response = $this->postJson('/api/dishes/upload', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_dish_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $fakeFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->postJson('/api/dishes/upload', [
            'image' => $fakeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_dish_succeeds_and_persists_in_database(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('lomo_saltado.jpg', 800, 600);

        $response = $this->postJson('/api/dishes/upload', [
            'image' => $file,
            'title' => 'Lomo Saltado',
            'prompt' => 'Carne salteada con cebolla y tomate',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Lomo Saltado',
                    'prompt' => 'Carne salteada con cebolla y tomate',
                    'status' => 'pending',
                    'progress' => 0,
                ],
            ]);

        $this->assertDatabaseHas('dish_models', [
            'title' => 'Lomo Saltado',
            'status' => 'pending',
            'progress' => 0,
        ]);
    }

    public function test_get_dish_details(): void
    {
        $dish = DishModel::create([
            'title' => 'Ceviche',
            'image_path' => 'uploads/ceviche.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/ceviche.jpg',
            'status' => 'pending',
            'progress' => 0,
        ]);

        $response = $this->getJson("/api/dishes/{$dish->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $dish->id,
                    'title' => 'Ceviche',
                ],
            ]);
    }

    public function test_tripo_service_is_configured(): void
    {
        $service = app(TripoService::class);
        $this->assertTrue($service->isConfigured(), 'TripoService should report configured when TRIPO_API_KEY is set');
    }

    public function test_tripo_api_key_authenticates_with_official_endpoint(): void
    {
        $service = app(TripoService::class);
        $balance = $service->getBalance();

        $this->assertIsArray($balance);
        $this->assertArrayHasKey('balance', $balance);
        $this->assertArrayHasKey('frozen', $balance);
        $this->assertArrayHasKey('available_credits', $balance);
        $this->assertArrayHasKey('frozen_credits', $balance);
    }

    public function test_tripo_v3_balance_api_endpoint(): void
    {
        $response = $this->getJson('/api/tripo/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'balance',
                'frozen',
                'available_credits',
                'frozen_credits',
                'total_credits',
                'models' => [
                    '*' => ['id', 'name', 'description', 'credits', 'recommended']
                ],
                'default_model',
            ]);
    }

    public function test_simulated_balance_100_available_and_0_frozen(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 100,
                    'frozen' => 0,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/tripo/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 100,
                'frozen' => 0,
                'available_credits' => 100,
                'frozen_credits' => 0,
                'total_credits' => 100,
            ]);
    }

    public function test_simulated_balance_0_available_and_100_frozen(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 0,
                    'frozen' => 100,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/tripo/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 0,
                'frozen' => 100,
                'available_credits' => 0,
                'frozen_credits' => 100,
                'total_credits' => 100,
            ]);
    }

    public function test_simulated_balance_0_available_and_0_frozen(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 0,
                    'frozen' => 0,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/tripo/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 0,
                'frozen' => 0,
                'available_credits' => 0,
                'frozen_credits' => 0,
                'total_credits' => 0,
            ]);
    }

    public function test_generate_blocks_when_balance_is_0_and_frozen_is_100(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 0,
                    'frozen' => 100,
                ],
            ], 200),
        ]);

        $dish = DishModel::create([
            'title' => 'Plato Bloqueado Test Frozen',
            'image_path' => 'uploads/test.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/test.jpg',
            'status' => 'pending',
            'progress' => 0,
            'model_version' => 'v3.1-20260211',
        ]);

        $response = $this->postJson("/api/dishes/{$dish->id}/generate");

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'insufficient_balance' => true,
                'current_balance' => 0,
                'available_credits' => 0,
                'frozen_credits' => 100,
                'required_credits' => 30,
            ]);

        $this->assertDatabaseHas('dish_models', [
            'id' => $dish->id,
            'status' => 'pending',
        ]);
    }

    public function test_generate_blocks_when_balance_is_0_and_frozen_is_0(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 0,
                    'frozen' => 0,
                ],
            ], 200),
        ]);

        $dish = DishModel::create([
            'title' => 'Plato Bloqueado Test Cero',
            'image_path' => 'uploads/test.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/test.jpg',
            'status' => 'pending',
            'progress' => 0,
            'model_version' => 'v3.1-20260211',
        ]);

        $response = $this->postJson("/api/dishes/{$dish->id}/generate");

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'insufficient_balance' => true,
                'current_balance' => 0,
                'available_credits' => 0,
                'frozen_credits' => 0,
                'required_credits' => 30,
            ]);

        $this->assertDatabaseHas('dish_models', [
            'id' => $dish->id,
            'status' => 'pending',
        ]);
    }

    public function test_generate_proceeds_safely_when_balance_is_100_and_frozen_is_0_with_mock(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/test_real.jpg', 'fake-image-content');

        \Illuminate\Support\Facades\Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 100,
                    'frozen' => 0,
                ],
            ], 200),
            'https://openapi.tripo3d.ai/v3/files' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'file_token' => 'file_mock_uuid_123',
                ],
            ], 200),
            'https://openapi.tripo3d.ai/v3/generation/image-to-model' => \Illuminate\Support\Facades\Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'task_id' => 'task_mock_456',
                ],
            ], 200),
        ]);

        $dish = DishModel::create([
            'title' => 'Plato Permitido Simulado',
            'image_path' => 'uploads/test_real.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/test_real.jpg',
            'status' => 'pending',
            'progress' => 0,
            'model_version' => 'v3.1-20260211',
        ]);

        $response = $this->postJson("/api/dishes/{$dish->id}/generate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $dish->id,
                    'tripo_task_id' => 'task_mock_456',
                    'status' => 'queued',
                ],
            ]);

        $this->assertDatabaseHas('dish_models', [
            'id' => $dish->id,
            'tripo_task_id' => 'task_mock_456',
            'status' => 'queued',
        ]);
    }

    public function test_tripo_v3_available_models_catalog(): void
    {
        $service = app(TripoService::class);
        $models = $service->getAvailableModels();

        $modelIds = array_column($models, 'id');
        $this->assertContains('v3.1-20260211', $modelIds);
        $this->assertContains('v3.0-20250812', $modelIds);
        $this->assertContains('v2.5-20250123', $modelIds);
        $this->assertContains('P1-20260311', $modelIds);
    }
}
