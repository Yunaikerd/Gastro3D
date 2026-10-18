<?php

namespace Tests\Feature;

use App\Models\DishGeneration;
use App\Models\DishModel;
use App\Services\TripoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class DishMultiviewServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected string $existingDishId = '01a14c78-e8d9-7143-991c-3aab263381a9';

    /**
     * Test multiview view normalizer accepts 2 to 4 valid views.
     */
    public function test_normalize_multiview_inputs_accepts_valid_views(): void
    {
        $service = app(TripoService::class);

        // Array with angle & file_token
        $views2 = [
            ['angle' => 'front', 'file_token' => 'file_front_001'],
            ['angle' => 'left', 'file_token' => 'file_left_002'],
        ];
        $normalized2 = $service->normalizeMultiviewInputs($views2);

        $this->assertCount(2, $normalized2);
        $this->assertEquals('file_front_001', $normalized2['front']);
        $this->assertEquals('file_left_002', $normalized2['left']);

        // Associative dictionary
        $views4 = [
            'front' => 'file_front_001',
            'left' => 'file_left_002',
            'back' => 'file_back_003',
            'right' => 'file_right_004',
        ];
        $normalized4 = $service->normalizeMultiviewInputs($views4);

        $this->assertCount(4, $normalized4);
        $this->assertEquals('file_front_001', $normalized4['front']);
        $this->assertEquals('file_left_002', $normalized4['left']);
        $this->assertEquals('file_back_003', $normalized4['back']);
        $this->assertEquals('file_right_004', $normalized4['right']);
    }

    /**
     * Test normalization rejects missing front view.
     */
    public function test_normalize_multiview_inputs_rejects_missing_front_view(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La vista frontal ('front') es obligatoria");

        $service->normalizeMultiviewInputs([
            ['angle' => 'left', 'file_token' => 'file_left_002'],
            ['angle' => 'right', 'file_token' => 'file_right_004'],
        ]);
    }

    /**
     * Test normalization rejects less than 2 views.
     */
    public function test_normalize_multiview_inputs_rejects_less_than_two_views(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("requiere entre 2 y 4 fotografías");

        $service->normalizeMultiviewInputs([
            ['angle' => 'front', 'file_token' => 'file_front_001'],
        ]);
    }

    /**
     * Test normalization rejects more than 4 views.
     */
    public function test_normalize_multiview_inputs_rejects_more_than_four_views(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->normalizeMultiviewInputs([
            ['angle' => 'front', 'file_token' => 'file_1'],
            ['angle' => 'left', 'file_token' => 'file_2'],
            ['angle' => 'back', 'file_token' => 'file_3'],
            ['angle' => 'right', 'file_token' => 'file_4'],
            ['angle' => 'front', 'file_token' => 'file_5'],
        ]);
    }

    /**
     * Test normalization rejects duplicate angles.
     */
    public function test_normalize_multiview_inputs_rejects_duplicate_angles(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Vista duplicada detectada: 'front'");

        $service->normalizeMultiviewInputs([
            ['angle' => 'front', 'file_token' => 'file_1'],
            ['angle' => 'front', 'file_token' => 'file_2'],
        ]);
    }

    /**
     * Test normalization rejects invalid angles.
     */
    public function test_normalize_multiview_inputs_rejects_invalid_angle(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Ángulo de vista no válido");

        $service->normalizeMultiviewInputs([
            ['angle' => 'front', 'file_token' => 'file_1'],
            ['angle' => 'top', 'file_token' => 'file_2'],
        ]);
    }

    /**
     * Test cost calculation matches verified official Tripo pricing.
     */
    public function test_calculate_cost_matches_verified_official_pricing(): void
    {
        $service = app(TripoService::class);

        // Standard single-image: 30 credits
        $this->assertEquals(30, $service->calculateCost('standard', 'v3.1-20260211'));

        // Multiview with standard texture: 30 credits (20 base + 10 standard texture)
        $this->assertEquals(30, $service->calculateCost('multiview', 'v3.1-20260211', [
            'texture_quality' => 'standard',
        ]));

        // Multiview with detailed texture: 40 credits (20 base + 20 detailed texture)
        $this->assertEquals(40, $service->calculateCost('multiview', 'v3.1-20260211', [
            'texture_quality' => 'detailed',
        ]));

        // Multiview P1 model: 40 credits
        $this->assertEquals(40, $service->calculateCost('multiview', 'P1-20260311'));
    }

    /**
     * Test incompatible parameters: detailed texture on model P1 throws exception.
     */
    public function test_create_multiview_task_rejects_detailed_texture_on_incompatible_models(): void
    {
        $service = app(TripoService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La calidad de textura 'detailed' no es compatible con el modelo 'P1-20260311'");

        $service->createMultiviewToModelTask([
            'front' => 'file_front_001',
            'left' => 'file_left_002',
        ], [
            'model_version' => 'P1-20260311',
            'texture_quality' => 'detailed',
        ]);
    }

    /**
     * Test createMultiviewToModelTask sends exact V3 payload structure using HTTP fake (0 credits consumed).
     */
    public function test_create_multiview_to_model_task_dispatches_exact_v3_structure(): void
    {
        $fakeTaskId = 'mock-task-uuid-mv-12345';

        Http::fake([
            'https://openapi.tripo3d.ai/v3/generation/multiview-to-model' => Http::response([
                'code' => 0,
                'data' => [
                    'task_id' => $fakeTaskId,
                ],
                'status' => 'success',
            ], 200),
        ]);

        $service = app(TripoService::class);

        $result = $service->createMultiviewToModelTask([
            'front' => 'file_token_front_abc',
            'left' => 'file_token_left_def',
            'right' => 'file_token_right_ghi',
        ], [
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
            'texture_alignment' => 'original_image',
            'pbr' => true,
            'auto_size' => true,
        ]);

        $this->assertEquals($fakeTaskId, $result['task_id']);
        $this->assertEquals(40, $result['credits_estimated']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://openapi.tripo3d.ai/v3/generation/multiview-to-model'
                && $data['model_version'] === 'v3.1-20260211'
                && count($data['inputs']) === 3
                && $data['inputs'][0] === ['front' => 'file_token_front_abc']
                && $data['inputs'][1] === ['left' => 'file_token_left_def']
                && $data['inputs'][2] === ['right' => 'file_token_right_ghi']
                && $data['texture'] === true
                && $data['pbr'] === true
                && $data['texture_quality'] === 'detailed'
                && $data['texture_alignment'] === 'original_image'
                && $data['auto_size'] === true;
        });
    }

    /**
     * Test storing task ID and syncing states in DishGeneration model.
     */
    public function test_dish_generation_records_task_and_syncs_states(): void
    {
        $dish = DishModel::find($this->existingDishId);
        $this->assertNotNull($dish);

        // Create a new pending multiview version
        $gen = $dish->createNewVersion([
            'mode' => 'multiview',
            'model_version' => 'v3.1-20260211',
            'texture_quality' => 'detailed',
            'texture_alignment' => 'original_image',
            'pbr' => true,
            'views' => [
                ['angle' => 'front', 'file_token' => 'file_front_abc'],
                ['angle' => 'left', 'file_token' => 'file_left_def'],
            ],
            'status' => 'pending',
            'progress' => 0,
            'is_active' => true,
        ]);

        $this->assertEquals('pending', $gen->status);
        $this->assertEquals(0, $gen->progress);

        // 1. Record task
        $gen->recordTask('mock-tripo-task-999', 40, ['status' => 'queued']);
        $gen->refresh();
        $dish->refresh();

        $this->assertEquals('mock-tripo-task-999', $gen->tripo_task_id);
        $this->assertEquals(40, $gen->credits_consumed);
        $this->assertEquals('queued', $gen->status);
        $this->assertEquals(15, $gen->progress);
        $this->assertEquals('queued', $dish->status);
        $this->assertEquals('mock-tripo-task-999', $dish->tripo_task_id);

        // 2. Sync running status
        $gen->syncFromTripoStatus([
            'status' => 'running',
            'progress' => 60,
        ]);
        $gen->refresh();
        $dish->refresh();

        $this->assertEquals('running', $gen->status);
        $this->assertEquals(60, $gen->progress);
        $this->assertEquals('running', $dish->status);
        $this->assertEquals(60, $dish->progress);

        // 3. Sync success status with GLB and thumbnail
        $gen->syncFromTripoStatus([
            'status' => 'success',
            'progress' => 100,
            'output' => [
                'model_url' => 'https://tripo-output.s3.amazonaws.com/mock.glb',
                'rendered_image_url' => 'https://tripo-output.s3.amazonaws.com/mock.webp',
            ],
            'credits_consumed' => 40,
        ]);
        $gen->refresh();
        $dish->refresh();

        $this->assertEquals('success', $gen->status);
        $this->assertEquals(100, $gen->progress);
        $this->assertEquals('https://tripo-output.s3.amazonaws.com/mock.glb', $gen->glb_url);
        $this->assertEquals('https://tripo-output.s3.amazonaws.com/mock.webp', $gen->thumbnail_url);
        $this->assertEquals('success', $dish->status);
        $this->assertEquals(100, $dish->progress);
        $this->assertEquals('https://tripo-output.s3.amazonaws.com/mock.glb', $dish->glb_url);
    }
}
