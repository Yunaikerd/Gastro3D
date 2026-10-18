<?php

namespace Tests\Feature;

use App\Models\DishGeneration;
use App\Models\DishModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DishMultiviewControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected string $existingDishId = '01a14c78-e8d9-7143-991c-3aab263381a9';

    /**
     * Test cost estimate endpoint calculation and balance check.
     */
    public function test_cost_estimate_endpoint(): void
    {
        Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => Http::response([
                'code' => 0,
                'status' => 'success',
                'data' => [
                    'balance' => 70,
                    'frozen' => 0,
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/dishes/cost-estimate', [
            'mode' => 'multiview',
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'mode' => 'multiview',
                'required_credits' => 40,
                'can_afford' => true, // balance is 70
            ]);

        // Standard texture multiview
        $responseStd = $this->postJson('/api/dishes/cost-estimate', [
            'mode' => 'multiview',
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'standard',
        ]);

        $responseStd->assertStatus(200)
            ->assertJson([
                'success' => true,
                'required_credits' => 30,
            ]);
    }

    /**
     * Test multiview upload validation rejects missing front view.
     */
    public function test_multiview_upload_rejects_missing_front_view(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/dishes/multiview', [
            'title' => 'Plato sin frontal',
            'images' => [
                'left' => UploadedFile::fake()->image('left.jpg'),
                'right' => UploadedFile::fake()->image('right.jpg'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'message' => "La vista frontal ('front') es obligatoria para la reconstrucción 3D multivista en Tripo V3.",
            ]);
    }

    /**
     * Test multiview upload validation rejects less than 2 views.
     */
    public function test_multiview_upload_rejects_less_than_two_views(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/dishes/multiview', [
            'title' => 'Solo una foto',
            'images' => [
                'front' => UploadedFile::fake()->image('front.jpg'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test multiview upload validation rejects non-image files.
     */
    public function test_multiview_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/dishes/multiview', [
            'title' => 'Archivo invalido',
            'images' => [
                'front' => UploadedFile::fake()->create('script.sh', 100, 'text/x-shellscript'),
                'left' => UploadedFile::fake()->image('left.jpg'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test successful multiview upload persists dish and version 1.
     */
    public function test_multiview_upload_succeeds_and_creates_version_1(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/dishes/multiview', [
            'title' => 'Lomo Saltado Multivista',
            'prompt' => 'carne tierna con cebolla y tomate',
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
            'images' => [
                'front' => UploadedFile::fake()->image('front.jpg', 800, 600),
                'left' => UploadedFile::fake()->image('left.jpg', 800, 600),
                'back' => UploadedFile::fake()->image('back.jpg', 800, 600),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Lomo Saltado Multivista',
                    'status' => 'pending',
                    'progress' => 0,
                    'active_generation' => [
                        'version_number' => 1,
                        'mode' => 'multiview',
                        'texture_quality' => 'detailed',
                        'status' => 'pending',
                        'is_active' => true,
                    ],
                ],
            ]);

        $dishId = $response->json('data.id');
        $this->assertDatabaseHas('dish_models', ['id' => $dishId, 'title' => 'Lomo Saltado Multivista']);
        $this->assertDatabaseHas('dish_generations', [
            'dish_model_id' => $dishId,
            'version_number' => 1,
            'mode' => 'multiview',
            'status' => 'pending',
        ]);

        $dish = DishModel::find($dishId);
        $v1 = $dish->activeGeneration;
        $this->assertCount(3, $v1->views);
        $this->assertEquals('front', $v1->views[0]['angle']);
        $this->assertEquals('left', $v1->views[1]['angle']);
        $this->assertEquals('back', $v1->views[2]['angle']);
    }

    /**
     * Test creating a new version for an existing dish without overwriting.
     */
    public function test_create_version_on_existing_dish(): void
    {
        Storage::fake('public');

        $dish = DishModel::find($this->existingDishId);
        $this->assertNotNull($dish);
        $initialVersionsCount = $dish->generations()->count();

        $response = $this->postJson("/api/dishes/{$dish->id}/versions", [
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
            'images' => [
                'front' => UploadedFile::fake()->image('front_v2.jpg'),
                'right' => UploadedFile::fake()->image('right_v2.jpg'),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'version_number' => $initialVersionsCount + 1,
                    'mode' => 'multiview',
                    'status' => 'pending',
                    'is_active' => false, // kept inactive so version 1 remains active
                ],
            ]);

        // Version 1 must still be active and intact
        $dish->refresh();
        $this->assertEquals(1, $dish->activeGeneration->version_number);
        $this->assertTrue($dish->activeGeneration->is_active);
    }

    /**
     * Test list versions endpoint.
     */
    public function test_list_versions_endpoint(): void
    {
        $response = $this->getJson("/api/dishes/{$this->existingDishId}/versions");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'dish_model_id',
                        'version_number',
                        'mode',
                        'status',
                        'progress',
                        'is_active',
                    ],
                ],
            ]);
    }

    /**
     * Test activate version switches active flag.
     */
    public function test_activate_version_endpoint(): void
    {
        $dish = DishModel::find($this->existingDishId);

        // Create version 2
        $v2 = $dish->createNewVersion([
            'mode' => 'multiview',
            'status' => 'queued',
            'progress' => 20,
            'is_active' => false,
        ]);

        $this->assertFalse($v2->is_active);

        // Activate version 2
        $response = $this->postJson("/api/dishes/{$dish->id}/versions/{$v2->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $v2->id,
                    'is_active' => true,
                ],
            ]);

        $v2->refresh();
        $this->assertTrue($v2->is_active);

        // Version 1 should now be inactive
        $v1 = $dish->generations()->where('version_number', 1)->first();
        $this->assertFalse($v1->is_active);
    }

    /**
     * Test generateVersion blocks when balance is insufficient (402).
     */
    public function test_generate_version_blocks_when_insufficient_balance(): void
    {
        Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => Http::response([
                'code' => 0,
                'data' => ['balance' => 15.0, 'frozen' => 0.0], // needs 40
                'status' => 'success',
            ], 200),
        ]);

        $dish = DishModel::find($this->existingDishId);
        $vPending = $dish->createNewVersion([
            'mode' => 'multiview',
            'texture_quality' => 'detailed',
            'status' => 'pending',
            'is_active' => false,
            'views' => [
                ['angle' => 'front', 'image_path' => 'dummy_front.jpg', 'file_token' => 'file_f'],
                ['angle' => 'left', 'image_path' => 'dummy_left.jpg', 'file_token' => 'file_l'],
            ],
        ]);

        $response = $this->postJson("/api/dishes/{$dish->id}/versions/{$vPending->id}/generate");

        $response->assertStatus(402)
            ->assertJson([
                'success' => false,
                'insufficient_balance' => true,
                'required_credits' => 40,
                'available_credits' => 15,
            ]);
    }

    /**
     * Test generateVersion dispatches multiview task using mocks without consuming credits.
     */
    public function test_generate_version_dispatches_multiview_task_successfully(): void
    {
        Storage::fake('public');

        // Create dummy local files for the views
        Storage::disk('public')->put('uploads/multiview/test_front.jpg', 'fake-image-content-front');
        Storage::disk('public')->put('uploads/multiview/test_left.jpg', 'fake-image-content-left');

        Http::fake([
            'https://openapi.tripo3d.ai/v3/account/balance' => Http::response([
                'code' => 0,
                'data' => ['balance' => 70.0, 'frozen' => 0.0],
                'status' => 'success',
            ], 200),
            'https://openapi.tripo3d.ai/v3/files' => Http::sequence()
                ->push(['code' => 0, 'data' => ['file_token' => 'file_token_front_mock'], 'status' => 'success'])
                ->push(['code' => 0, 'data' => ['file_token' => 'file_token_left_mock'], 'status' => 'success']),
            'https://openapi.tripo3d.ai/v3/generation/multiview-to-model' => Http::response([
                'code' => 0,
                'data' => ['task_id' => 'tripo-task-multiview-uuid-777'],
                'status' => 'success',
            ], 200),
        ]);

        $dish = DishModel::find($this->existingDishId);
        $vPending = $dish->createNewVersion([
            'mode' => 'multiview',
            'texture_quality' => 'detailed',
            'status' => 'pending',
            'is_active' => false,
            'views' => [
                ['angle' => 'front', 'image_path' => 'uploads/multiview/test_front.jpg', 'file_token' => null],
                ['angle' => 'left', 'image_path' => 'uploads/multiview/test_left.jpg', 'file_token' => null],
            ],
        ]);

        $response = $this->postJson("/api/dishes/{$dish->id}/versions/{$vPending->id}/generate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vPending->id,
                    'status' => 'queued',
                    'progress' => 15,
                    'tripo_task_id' => 'tripo-task-multiview-uuid-777',
                    'credits_consumed' => 40,
                ],
            ]);

        // Attempting to generate again immediately must be rejected with 409
        $responseDuplicate = $this->postJson("/api/dishes/{$dish->id}/versions/{$vPending->id}/generate");
        $responseDuplicate->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test checkVersionStatus updates progress and downloads GLB on success.
     */
    public function test_check_version_status_syncs_and_downloads_glb(): void
    {
        Storage::fake('public');

        $fakeGlbContent = str_repeat('glTF-mock-binary-data-3d-model', 50);

        Http::fake([
            'https://openapi.tripo3d.ai/v3/tasks/task-mv-mock-123' => Http::response([
                'code' => 0,
                'data' => [
                    'task_id' => 'task-mv-mock-123',
                    'status' => 'success',
                    'progress' => 100,
                    'output' => [
                        'model_url' => 'https://mock.tripo3d.ai/outputs/model.glb',
                        'rendered_image_url' => 'https://mock.tripo3d.ai/outputs/thumb.webp',
                    ],
                    'credits_consumed' => 40,
                ],
                'status' => 'success',
            ], 200),
            'https://mock.tripo3d.ai/outputs/model.glb' => Http::response($fakeGlbContent, 200),
            'https://mock.tripo3d.ai/outputs/thumb.webp' => Http::response('mock-webp-thumb', 200),
        ]);

        $dish = DishModel::find($this->existingDishId);
        $gen = $dish->createNewVersion([
            'mode' => 'multiview',
            'status' => 'running',
            'progress' => 70,
            'tripo_task_id' => 'task-mv-mock-123',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/dishes/{$dish->id}/versions/{$gen->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $gen->id,
                    'status' => 'success',
                    'progress' => 100,
                ],
            ]);

        $gen->refresh();
        $this->assertEquals('success', $gen->status);
        $this->assertEquals(100, $gen->progress);
        $this->assertNotNull($gen->glb_path);
        $this->assertTrue(Storage::disk('public')->exists($gen->glb_path));
    }
}
