<?php

namespace Platform\Core\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PolicyRegistrar
{
    /**
     * Registriert Policies für ein Modul
     */
    public static function registerModulePolicies(string $moduleKey, array $policies): void
    {
        foreach ($policies as $model => $policy) {
            try {
                if (class_exists($model) && class_exists($policy)) {
                    Gate::policy($model, $policy);
                    Log::debug("Policy registered: {$model} -> {$policy}");
                } else {
                    Log::warning("Policy registration failed: {$model} -> {$policy} (class not found)");
                }
            } catch (\Exception $e) {
                Log::error("Policy registration error: {$model} -> {$policy}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Standard-Policy-Mapping für häufige Patterns
     */
    public static function getStandardPolicies(): array
    {
        return [
            'owner' => \Platform\Core\Policies\OwnerPolicy::class,
            'team' => \Platform\Core\Policies\TeamPolicy::class,
            'role' => \Platform\Core\Policies\RolePolicy::class,
        ];
    }

    /**
     * Erstellt Policy-Klasse automatisch basierend auf Pattern
     */
    public static function createPolicyClass(string $modelClass, string $pattern = 'team'): string
    {
        $modelName = class_basename($modelClass);
        $moduleName = explode('\\', $modelClass)[1] ?? 'Core';
        
        $policyClass = "Platform\\{$moduleName}\\Policies\\{$modelName}Policy";
        
        // Falls Policy nicht existiert, Standard-Policy verwenden
        if (!class_exists($policyClass)) {
            $standardPolicies = self::getStandardPolicies();
            return $standardPolicies[$pattern] ?? $standardPolicies['team'];
        }
        
        return $policyClass;
    }
}
