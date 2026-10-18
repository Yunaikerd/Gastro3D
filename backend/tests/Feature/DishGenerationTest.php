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
    }

    public function test_tripo_v3_balance_api_endpoint(): void
    {
        $response = $this->getJson('/api/tripo/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'balance',
                'frozen',
                'models' => [
                    '*' => ['id', 'name', 'description', 'credits', 'recommended']
                ],
                'default_model',
            ]);
    }

    public function test_generate_blocks_and_protects_credits_when_insufficient_balance(): void
    {
        $dish = DishModel::create([
            'title' => 'Plato Bloqueado Test',
            'image_path' => 'uploads/test.jpg',
            'image_url' => 'http://localhost:8000/storage/uploads/test.jpg',
            'status' => 'pending',
            'progress' => 0,
            'model_version' => 'v3.1-20260211',
        ]);

        // Attempting to generate with insufficient balance returns HTTP 402
        $response = $this->postJson("/api/dishes/{$dish->id}/generate");

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'insufficient_balance' => true,
            ]);

        $this->assertDatabaseHas('dish_models', [
            'id' => $dish->id,
            'status' => 'pending', // Status remains pending, no task created
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
