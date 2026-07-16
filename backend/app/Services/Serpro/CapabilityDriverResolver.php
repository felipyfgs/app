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
     * Prefixo de operation_key → chave de config.
     *
     * @var array<string, string>
     */
    private const PREFIX_CAPABILITY = [
        'sitfis.' => 'sitfis',
        'autentica_procurador.' => 'autentica_procurador',
        'autenticaprocurador.' => 'autentica_procurador',
        'procuracoes.' => 'authorization',
        'eventosatualizacao.' => 'authorization',
        'caixa_postal.' => 'mailbox',
        'dte.' => 'mailbox',
        'dctfweb.' => 'dctfweb',
        'mit.' => 'dctfweb',
        'pgdasd.' => 'simples_mei',
        'pgmei.' => 'simples_mei',
        'ccmei.' => 'simples_mei',
        'defis.' => 'simples_mei',
        'regimeapuracao.' => 'simples_mei',
        'dasnsimei.' => 'simples_mei',
        'parc' => 'installments', // parcmei., parcsn., etc.
        'pert' => 'installments',
        'relp' => 'installments',
        'sicalc.' => 'guides',
        'pagtoweb.' => 'guides',
        'pnr_contador.' => 'registrations',
        'eprocesso.' => 'tax_processes',
    ];

    public function forOperationKey(string $operationKey): SerproCapabilityDriver
    {
        return $this->forCapability($this->capabilityForOperationKey($operationKey));
    }

    public function capabilityForOperationKey(string $operationKey): string
    {
        $key = strtolower(trim($operationKey));
        foreach (self::PREFIX_CAPABILITY as $prefix => $capability) {
            if (str_starts_with($key, $prefix)) {
                return $capability;
            }
        }

        return 'default';
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

    /**
     * @return list<string>
     */
    public function knownCapabilities(): array
    {
        return array_values(array_unique(array_merge(
            array_values(self::PREFIX_CAPABILITY),
            ['default'],
        )));
    }

    private function isProduction(): bool
    {
        return app()->environment('production');
    }
}
