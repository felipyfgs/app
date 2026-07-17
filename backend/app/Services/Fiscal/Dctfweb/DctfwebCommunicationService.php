<?php

namespace App\Services\Fiscal\Dctfweb;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientCommunicationPreference;
use App\Models\Office;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdCommunicationService;

/**
 * Comunicação TEMPLATE_ONLY do DCTFWeb — mesma infraestrutura, contexto isolado.
 * module_key=dctfweb, submodule_key=dctfweb; automatic_effective sempre false.
 */
final class DctfwebCommunicationService
{
    public const MODULE = 'dctfweb';

    public const SUBMODULE = 'dctfweb';

    public const BATCH_LIMIT = PgdasdCommunicationService::BATCH_LIMIT;

    private readonly PgdasdCommunicationService $inner;

    public function __construct(AuditLogger $audit)
    {
        $this->inner = new PgdasdCommunicationService(
            $audit,
            self::SUBMODULE,
            'dctfweb.communication',
            self::MODULE,
        );
    }

    public function getPreferences(Office $office, Client $client): ClientCommunicationPreference
    {
        return $this->inner->getPreferences($office, $client);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Office $office, Client $client): array
    {
        return $this->inner->summary($office, $client);
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    public function summariesForClients(Office $office, array $clientIds): array
    {
        return $this->inner->summariesForClients($office, $clientIds);
    }

    /**
     * @param  array{email_enabled: bool, whatsapp_enabled: bool, automatic_requested: bool, lock_version: int}  $input
     */
    public function updatePreferences(
        Office $office,
        Client $client,
        User $actor,
        OfficeRole $role,
        array $input,
    ): ClientCommunicationPreference {
        return $this->inner->updatePreferences($office, $client, $actor, $role, $input);
    }

    /**
     * @param  list<int>  $clientIds
     * @return list<ClientCommunicationPreference>
     */
    public function batchSetAutomatic(
        Office $office,
        User $actor,
        OfficeRole $role,
        array $clientIds,
        bool $automaticRequested,
    ): array {
        return $this->inner->batchSetAutomatic($office, $actor, $role, $clientIds, $automaticRequested);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Office $office, Client $client): array
    {
        return $this->inner->preview($office, $client);
    }

    /**
     * @return array<string, mixed>
     */
    public function tracking(Office $office, Client $client): array
    {
        return $this->inner->tracking($office, $client);
    }
}
