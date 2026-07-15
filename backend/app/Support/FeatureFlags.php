<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Helper type-safe para feature flags do hub fiscal.
 *
 * Ordem de precedência (mais restritiva vence):
 * 1. kill_switch global
 * 2. global_enabled
 * 3. módulo enabled
 * 4. allowlist / allow_all_offices (quando officeId informado)
 * 5. para mutações: mutating.kill_switch, mutating.enabled, module.mutating_enabled
 *
 * Defaults de config: tudo OFF. Nunca hardcode enable=true neste helper.
 */
final class FeatureFlags
{
    /** @var list<string> */
    public const MODULES = [
        'simples_mei',
        'dctfweb_mit',
        'parcelamentos',
        'sitfis',
        'mailbox',
        'declaracoes',
        'guias',
        'fgts',
        'mutacoes',
    ];

    public static function isKillSwitchActive(): bool
    {
        return (bool) config('features.kill_switch', false);
    }

    public static function isGloballyEnabled(): bool
    {
        if (self::isKillSwitchActive()) {
            return false;
        }

        return (bool) config('features.global_enabled', false);
    }

    /**
     * @return list<string>
     */
    public static function knownModules(): array
    {
        return self::MODULES;
    }

    public static function assertKnownModule(string $module): void
    {
        if (! in_array($module, self::MODULES, true)) {
            throw new InvalidArgumentException("Módulo de feature desconhecido: {$module}");
        }
    }

    /**
     * Módulo habilitado globalmente (e para o tenant, se officeId informado).
     */
    public static function isModuleEnabled(string $module, ?int $officeId = null): bool
    {
        self::assertKnownModule($module);

        if (! self::isGloballyEnabled()) {
            return false;
        }

        if (! (bool) config("features.modules.{$module}.enabled", false)) {
            return false;
        }

        if ($officeId === null) {
            return true;
        }

        return self::isOfficeAllowedForModule($module, $officeId);
    }

    /**
     * Operação mutante permitida para o módulo (e tenant opcional).
     */
    public static function isMutatingEnabled(string $module, ?int $officeId = null): bool
    {
        self::assertKnownModule($module);

        if (self::isKillSwitchActive()) {
            return false;
        }

        if ((bool) config('features.mutating.kill_switch', false)) {
            return false;
        }

        if (! (bool) config('features.mutating.enabled', false)) {
            return false;
        }

        if (! self::isModuleEnabled($module, $officeId)) {
            return false;
        }

        return (bool) config("features.modules.{$module}.mutating_enabled", false);
    }

    public static function isOfficeAllowedForModule(string $module, int $officeId): bool
    {
        self::assertKnownModule($module);

        /** @var list<int> $allowlist */
        $allowlist = config("features.modules.{$module}.office_allowlist", []);
        if (! is_array($allowlist)) {
            $allowlist = [];
        }

        if ($allowlist === []) {
            return (bool) config("features.modules.{$module}.allow_all_offices", false);
        }

        return in_array($officeId, $allowlist, true);
    }

    /**
     * Snapshot sanitizado para ops/diagnóstico (sem segredos).
     *
     * @return array{
     *     kill_switch: bool,
     *     global_enabled: bool,
     *     mutating: array{enabled: bool, kill_switch: bool},
     *     modules: array<string, array{enabled: bool, mutating_enabled: bool, allow_all_offices: bool, allowlist_count: int}>
     * }
     */
    public static function snapshot(): array
    {
        $modules = [];
        foreach (self::MODULES as $module) {
            /** @var list<int>|mixed $allowlist */
            $allowlist = config("features.modules.{$module}.office_allowlist", []);
            $count = is_array($allowlist) ? count($allowlist) : 0;
            $modules[$module] = [
                'enabled' => (bool) config("features.modules.{$module}.enabled", false),
                'mutating_enabled' => (bool) config("features.modules.{$module}.mutating_enabled", false),
                'allow_all_offices' => (bool) config("features.modules.{$module}.allow_all_offices", false),
                'allowlist_count' => $count,
            ];
        }

        return [
            'kill_switch' => self::isKillSwitchActive(),
            'global_enabled' => (bool) config('features.global_enabled', false),
            'mutating' => [
                'enabled' => (bool) config('features.mutating.enabled', false),
                'kill_switch' => (bool) config('features.mutating.kill_switch', false),
            ],
            'modules' => $modules,
        ];
    }
}
