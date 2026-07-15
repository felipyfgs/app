<?php

namespace App\Services\Serpro;

use App\Enums\SerproCapabilityDriver;
use RuntimeException;

/**
 * Resolve driver por capacidade (disabled|simulated|real) sem fallback.
 * Preflight de produção rejeita simulated.
 */
final class CapabilityDriverResolver
{
    /**
     * Mapa estável operation_key / família → chave de config.
     *
     * @var array<string, string>
     */
    private const OPERATION_CAPABILITY = [
        'sitfis.solicitar_protocolo' => 'sitfis',
        'sitfis.emitir_relatorio' => 'sitfis',
        'autentica_procurador.envio_xml_assinado' => 'autentica_procurador',
    ];

    public function forOperationKey(string $operationKey): SerproCapabilityDriver
    {
        $capability = self::OPERATION_CAPABILITY[$operationKey]
            ?? $this->guessCapability($operationKey);

        return $this->forCapability($capability);
    }

    public function forCapability(string $capability): SerproCapabilityDriver
    {
        $raw = (string) config(
            'serpro.capabilities.'.$capability,
            config('serpro.capabilities.default', 'disabled'),
        );
        $driver = SerproCapabilityDriver::tryFrom(strtolower(trim($raw)));
        if ($driver === null) {
            throw new RuntimeException("Driver SERPRO inválido para capacidade {$capability}: {$raw}");
        }

        if ($driver === SerproCapabilityDriver::Simulated && $this->isProduction()) {
            throw new RuntimeException(
                "Driver simulated proibido em produção (capacidade: {$capability})."
            );
        }

        return $driver;
    }

    /**
     * Falha no boot se alguma capacidade estiver simulated em production.
     *
     * @return list<string> problemas (vazio = ok)
     */
    public function preflightProduction(): array
    {
        if (! $this->isProduction()) {
            return [];
        }

        $problems = [];
        $caps = (array) config('serpro.capabilities', []);
        foreach ($caps as $name => $value) {
            if ($name === 'default') {
                continue;
            }
            if (strtolower((string) $value) === SerproCapabilityDriver::Simulated->value) {
                $problems[] = "serpro.capabilities.{$name}=simulated em production";
            }
        }

        return $problems;
    }

    public function assertProductionSafe(): void
    {
        $problems = $this->preflightProduction();
        if ($problems !== []) {
            throw new RuntimeException(
                'Preflight SERPRO falhou: '.implode('; ', $problems)
            );
        }
    }

    private function guessCapability(string $operationKey): string
    {
        if (str_starts_with($operationKey, 'sitfis.')) {
            return 'sitfis';
        }
        if (str_starts_with($operationKey, 'autentica_procurador.')) {
            return 'autentica_procurador';
        }

        return 'default';
    }

    private function isProduction(): bool
    {
        return app()->environment('production');
    }
}
