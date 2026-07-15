<?php

namespace App\Services\Integra;

use App\Contracts\IntegraProcuracoesClient;
use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\TaxProxyPower;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use RuntimeException;

final class TaxProxyPowerService
{
    public function __construct(
        private readonly IntegraProcuracoesClient $procuracoes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Importação manual de evidência oficial (quando API não cobre o caso).
     */
    public function importManualEvidence(
        Office $office,
        Client $client,
        OfficeSerproAuthorization $auth,
        string $powerCode,
        string $systemCode,
        ?string $serviceCode,
        ?CarbonImmutable $validFrom,
        ?CarbonImmutable $validTo,
        string $evidenceRef,
        ?string $evidenceSha256 = null,
        ?int $actorUserId = null,
    ): TaxProxyPower {
        if ($client->office_id !== $office->id) {
            throw new RuntimeException('Contribuinte não pertence ao escritório.');
        }

        if ($auth->office_id !== $office->id) {
            throw new RuntimeException('Autorização de outro escritório.');
        }

        $contributor = $this->resolveContributorCnpj($client);

        $power = TaxProxyPower::query()->updateOrCreate(
            [
                'office_id' => $office->id,
                'client_id' => $client->id,
                'power_code' => strtoupper($powerCode),
                'author_identity' => $auth->author_identity,
                'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
            ],
            [
                'office_serpro_authorization_id' => $auth->id,
                'contributor_cnpj' => $contributor,
                'system_code' => strtoupper($systemCode),
                'service_code' => $serviceCode !== null ? strtoupper($serviceCode) : null,
                'status' => TaxProxyPowerStatus::Active,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'evidence_ref' => mb_substr($evidenceRef, 0, 120),
                'evidence_sha256' => $evidenceSha256,
                'verified_at' => now(),
                'last_check_result' => 'MANUAL_IMPORT',
            ],
        );

        $this->audit->record('serpro.proxy_power.import', 'SUCCESS', $power, [
            'power_code' => $power->power_code,
            'client_id' => $client->id,
            'source' => $power->source->value,
        ], $actorUserId, $office->id);

        return $power->refresh();
    }

    /**
     * Sincroniza poderes via adapter Integra-Procurações (fake ou real).
     *
     * @return list<TaxProxyPower>
     */
    public function syncFromApi(
        Office $office,
        Client $client,
        OfficeSerproAuthorization $auth,
        SerproEnvironment $environment,
        ?string $powerCode = null,
        ?int $actorUserId = null,
    ): array {
        if ($client->office_id !== $office->id || $auth->office_id !== $office->id) {
            throw new RuntimeException('Isolamento de tenant violado.');
        }

        $contributor = $this->resolveContributorCnpj($client);

        $result = $this->procuracoes->lookup(new ProcuracaoLookupRequest(
            officeId: $office->id,
            environment: $environment->value,
            authorIdentity: $auth->author_identity,
            contributorCnpj: $contributor,
            powerCode: $powerCode,
            correlationId: $this->audit->correlationId(),
        ));

        if (! $result->success) {
            throw new RuntimeException($result->errorMessage ?? 'Falha ao consultar procurações.');
        }

        $saved = [];
        foreach ($result->powers as $row) {
            $saved[] = TaxProxyPower::query()->updateOrCreate(
                [
                    'office_id' => $office->id,
                    'client_id' => $client->id,
                    'power_code' => strtoupper($row['power_code']),
                    'author_identity' => $auth->author_identity,
                    'source' => TaxProxyPowerSource::IntegraProcuracoes->value,
                ],
                [
                    'office_serpro_authorization_id' => $auth->id,
                    'contributor_cnpj' => $contributor,
                    'system_code' => strtoupper($row['system_code']),
                    'service_code' => isset($row['service_code']) ? strtoupper((string) $row['service_code']) : null,
                    'status' => TaxProxyPowerStatus::tryFrom(strtoupper($row['status'])) ?? TaxProxyPowerStatus::Active,
                    'valid_from' => ! empty($row['valid_from']) ? CarbonImmutable::parse($row['valid_from']) : null,
                    'valid_to' => ! empty($row['valid_to']) ? CarbonImmutable::parse($row['valid_to']) : null,
                    'evidence_ref' => $result->evidenceRef,
                    'verified_at' => now(),
                    'last_check_result' => $result->simulated ? 'SIMULATED' : 'API_OK',
                    'metadata' => ['simulated' => $result->simulated],
                ],
            );
        }

        $this->audit->record('serpro.proxy_power.sync', 'SUCCESS', $auth, [
            'client_id' => $client->id,
            'count' => count($saved),
            'simulated' => $result->simulated,
        ], $actorUserId, $office->id);

        return $saved;
    }

    public function findUsablePower(
        int $officeId,
        int $clientId,
        string $powerCode,
        string $authorIdentity,
    ): ?TaxProxyPower {
        $power = TaxProxyPower::query()
            ->where('office_id', $officeId)
            ->where('client_id', $clientId)
            ->where('power_code', strtoupper($powerCode))
            ->where('author_identity', $authorIdentity)
            ->where('status', TaxProxyPowerStatus::Active->value)
            ->orderByDesc('id')
            ->first();

        if ($power === null) {
            return null;
        }

        if (! $power->isCurrentlyValid()) {
            if ($power->valid_to !== null && $power->valid_to->isPast()) {
                $power->status = TaxProxyPowerStatus::Expired;
                $power->save();
            }

            return null;
        }

        return $power;
    }

    private function resolveContributorCnpj(Client $client): string
    {
        $matrix = $client->establishments()
            ->where('is_matrix', true)
            ->orderBy('id')
            ->first();

        if ($matrix !== null && is_string($matrix->cnpj) && strlen($matrix->cnpj) === 14) {
            return strtoupper($matrix->cnpj);
        }

        $any = $client->establishments()->orderBy('id')->first();
        if ($any !== null && is_string($any->cnpj) && strlen($any->cnpj) === 14) {
            return strtoupper($any->cnpj);
        }

        $root = strtoupper((string) $client->root_cnpj);
        if (strlen($root) === 14) {
            return $root;
        }

        // Fallback determinístico para testes sem establishment (não é CNPJ válido de DV).
        return str_pad(substr($root, 0, 8), 14, '0');
    }
}
