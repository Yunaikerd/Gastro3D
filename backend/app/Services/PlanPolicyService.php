<?php

namespace App\Services;

class PlanPolicyService
{
    public const PLAN_FREE = 'free';
    public const PLAN_PREMIUM = 'premium';

    /**
     * Get feature matrix and limitations by plan tier.
     */
    public function getPlanCapabilities(string $plan = self::PLAN_PREMIUM): array
    {
        return match ($plan) {
            self::PLAN_FREE => [
                'plan' => self::PLAN_FREE,
                'name' => 'Plan Inicial (Free)',
                'max_generations' => 5,
                'allowed_modes' => ['standard'],
                'allowed_texture_qualities' => ['standard'],
                'multiview_enabled' => false,
                'version_history_enabled' => false,
                'ar_menu_export' => false,
                'max_versions_per_dish' => 1,
            ],
            self::PLAN_PREMIUM => [
                'plan' => self::PLAN_PREMIUM,
                'name' => 'Plan Gourmet Pro (Premium)',
                'max_generations' => -1, // Unlimited
                'allowed_modes' => ['standard', 'multiview'],
                'allowed_texture_qualities' => ['standard', 'detailed'],
                'multiview_enabled' => true,
                'version_history_enabled' => true,
                'ar_menu_export' => true,
                'max_versions_per_dish' => 10,
            ],
            default => $this->getPlanCapabilities(self::PLAN_FREE),
        };
    }

    /**
     * Validate whether a restaurant or account can trigger a generation under their plan.
     * Note: In Phase 1.2, this is architectural only (no billing/lockouts active yet).
     */
    public function canGenerate(string $plan, string $mode, int $currentGenerationsCount = 0): array
    {
        $caps = $this->getPlanCapabilities($plan);

        if (!in_array($mode, $caps['allowed_modes'], true)) {
            return [
                'allowed' => false,
                'reason' => "El modo '{$mode}' requiere suscripción al Plan Gourmet Pro.",
                'required_plan' => self::PLAN_PREMIUM,
            ];
        }

        if ($caps['max_generations'] > 0 && $currentGenerationsCount >= $caps['max_generations']) {
            return [
                'allowed' => false,
                'reason' => "Has alcanzado el límite de {$caps['max_generations']} generaciones de prueba del plan gratuito.",
                'required_plan' => self::PLAN_PREMIUM,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'capabilities' => $caps,
        ];
    }

    /**
     * Estimate credit consumption based on mode and texture quality.
     */
    public function getEstimatedCredits(string $mode = 'standard', string $textureQuality = 'standard'): int
    {
        if ($mode === 'multiview') {
            return $textureQuality === 'detailed' ? 40 : 30;
        }

        return 30; // standard single-photo with PBR
    }
}
