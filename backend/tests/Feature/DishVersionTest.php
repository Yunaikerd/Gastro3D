<?php

namespace Tests\Feature;

use App\Models\DishGeneration;
use App\Models\DishModel;
use App\Services\PlanPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DishVersionTest extends TestCase
{
    use DatabaseTransactions;

    protected string $existingDishId = '01a14c78-e8d9-7143-991c-3aab263381a9';

    public function test_existing_dish_has_version_1_registered_as_active(): void
    {
        $dish = DishModel::find($this->existingDishId);
        $this->assertNotNull($dish, 'Existing dish "Plato con papas" must exist in database');

        $activeGen = $dish->activeGeneration;
        $this->assertNotNull($activeGen, 'Dish must have an active generation');
        $this->assertEquals(1, $activeGen->version_number);
        $this->assertEquals('standard', $activeGen->mode);
        $this->assertEquals('success', $activeGen->status);
        $this->assertEquals(100, $activeGen->progress);
        $this->assertEquals('d5ca9bcd-9472-443e-b656-a47729144264', $activeGen->tripo_task_id);
        $this->assertEquals('models/01a14c78-e8d9-7143-991c-3aab263381a9.glb', $activeGen->glb_path);
        $this->assertTrue($activeGen->is_active);
    }

    public function test_dish_models_has_generations_relationship(): void
    {
        $dish = DishModel::find($this->existingDishId);
        $this->assertNotNull($dish);

        $generations = $dish->generations;
        $this->assertGreaterThanOrEqual(1, $generations->count());
        $this->assertInstanceOf(DishGeneration::class, $generations->first());
        $this->assertEquals($dish->id, $generations->first()->dishModel->id);
    }

    public function test_create_new_version_increments_version_number_and_shifts_active(): void
    {
        $dish = DishModel::find($this->existingDishId);
        $this->assertNotNull($dish);

        $v1 = $dish->activeGeneration;
        $this->assertTrue($v1->is_active);

        // Create a new version 2 (simulating a future multiview generation)
        $v2 = $dish->createNewVersion([
            'mode' => 'multiview',
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
            'texture_alignment' => 'original_image',
            'pbr' => true,
            'views' => [
                ['angle' => 'front', 'file_token' => 'file_mock_front'],
                ['angle' => 'left', 'file_token' => 'file_mock_left'],
                ['angle' => 'right', 'file_token' => 'file_mock_right'],
                ['angle' => 'back', 'file_token' => 'file_mock_back'],
            ],
            'status' => 'queued',
            'progress' => 15,
            'credits_consumed' => 40,
            'is_active' => true,
        ]);

        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals('multiview', $v2->mode);
        $this->assertEquals('detailed', $v2->texture_quality);
        $this->assertTrue($v2->is_active);

        // Version 1 must now be inactive
        $v1->refresh();
        $this->assertFalse($v1->is_active, 'Previous version 1 should be deactivated');

        // Parent dish_models should reflect the active version
        $dish->refresh();
        $this->assertEquals('queued', $dish->status);
        $this->assertEquals(15, $dish->progress);
    }

    public function test_activate_method_switches_active_version_and_syncs_parent(): void
    {
        $dish = DishModel::find($this->existingDishId);
        $v1 = $dish->generations()->where('version_number', 1)->first();

        // Create a dummy v2
        $v2 = $dish->createNewVersion([
            'mode' => 'multiview',
            'status' => 'queued',
            'progress' => 20,
            'is_active' => true,
        ]);

        $this->assertTrue($v2->is_active);
        $v1->refresh();
        $this->assertFalse($v1->is_active);

        // Switch back to v1 using activate()
        $v1->activate();

        $v1->refresh();
        $v2->refresh();
        $dish->refresh();

        $this->assertTrue($v1->is_active, 'v1 should be active again');
        $this->assertFalse($v2->is_active, 'v2 should be deactivated');
        $this->assertEquals('success', $dish->status, 'Parent dish should be synced to v1 status');
        $this->assertEquals(100, $dish->progress, 'Parent dish should be synced to v1 progress');
        $this->assertEquals('models/01a14c78-e8d9-7143-991c-3aab263381a9.glb', $dish->glb_path);
    }

    public function test_plan_policy_service_capabilities_and_quotas(): void
    {
        $service = app(PlanPolicyService::class);

        // Free Plan checks
        $freeCaps = $service->getPlanCapabilities(PlanPolicyService::PLAN_FREE);
        $this->assertEquals(5, $freeCaps['max_generations']);
        $this->assertFalse($freeCaps['multiview_enabled']);
        $this->assertContains('standard', $freeCaps['allowed_modes']);
        $this->assertNotContains('multiview', $freeCaps['allowed_modes']);

        $freeStandardCheck = $service->canGenerate(PlanPolicyService::PLAN_FREE, 'standard', 2);
        $this->assertTrue($freeStandardCheck['allowed']);

        $freeMultiviewCheck = $service->canGenerate(PlanPolicyService::PLAN_FREE, 'multiview', 2);
        $this->assertFalse($freeMultiviewCheck['allowed']);
        $this->assertStringContainsString('Gourmet Pro', $freeMultiviewCheck['reason']);

        $freeQuotaExceeded = $service->canGenerate(PlanPolicyService::PLAN_FREE, 'standard', 5);
        $this->assertFalse($freeQuotaExceeded['allowed']);

        // Premium Plan checks
        $premiumCaps = $service->getPlanCapabilities(PlanPolicyService::PLAN_PREMIUM);
        $this->assertTrue($premiumCaps['multiview_enabled']);
        $this->assertTrue($premiumCaps['version_history_enabled']);
        $this->assertTrue($premiumCaps['ar_menu_export']);

        $premiumCheck = $service->canGenerate(PlanPolicyService::PLAN_PREMIUM, 'multiview', 10);
        $this->assertTrue($premiumCheck['allowed']);

        // Estimated credit checks
        $this->assertEquals(30, $service->getEstimatedCredits('standard', 'standard'));
        $this->assertEquals(40, $service->getEstimatedCredits('multiview', 'detailed'));
        $this->assertEquals(30, $service->getEstimatedCredits('multiview', 'standard'));
    }

    public function test_existing_glb_and_thumbnail_still_exist_and_accessible(): void
    {
        $this->assertTrue(
            Storage::disk('public')->exists('models/01a14c78-e8d9-7143-991c-3aab263381a9.glb'),
            'Recovered GLB file must exist in public storage'
        );

        $size = Storage::disk('public')->size('models/01a14c78-e8d9-7143-991c-3aab263381a9.glb');
        $this->assertGreaterThan(40 * 1024 * 1024, $size, 'GLB file must be > 40MB');

        $this->assertTrue(
            Storage::disk('public')->exists('models/01a14c78-e8d9-7143-991c-3aab263381a9_thumb.webp'),
            'Thumbnail WebP file must exist in public storage'
        );
    }

    public function test_database_enforces_single_active_generation_per_dish(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempting to create a second active generation for the same dish must be blocked by the PostgreSQL partial unique index
        DishGeneration::create([
            'dish_model_id' => $this->existingDishId,
            'version_number' => 999,
            'mode' => 'standard',
            'status' => 'queued',
            'is_active' => true,
        ]);
    }
}

