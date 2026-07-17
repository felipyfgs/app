<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDispatchStatus;
use App\Enums\CommunicationExecutionMode;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientCommunicationDispatch;
use App\Models\ClientCommunicationPreference;
use App\Models\ClientContact;
use App\Models\Office;
use App\Models\PgdasdArtifact;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\Dctfweb\DctfwebPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Preferências, prévia e rastreio TEMPLATE_ONLY — sem provider, job, Mail ou envio real.
 */
final class PgdasdCommunicationService
{
    public const MODULE = 'simples_mei';

    public const SUBMODULE = 'pgdasd';

    public const BATCH_LIMIT = 100;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly string $submoduleKey = self::SUBMODULE,
        private readonly string $auditPrefix = 'pgdasd.communication',
        private readonly string $moduleKey = self::MODULE,
    ) {}

    public function submoduleKey(): string
    {
        return $this->submoduleKey;
    }

    public function moduleKey(): string
    {
        return $this->moduleKey;
    }

    /**
     * Leitura sem efeito colateral: preferência ausente vira default somente em memória.
     */
    public function getPreferences(Office $office, Client $client): ClientCommunicationPreference
    {
        $this->assertClient($office, $client);

        return ClientCommunicationPreference::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('module_key', $this->moduleKey)
            ->where('submodule_key', $this->submoduleKey)
            ->first() ?? new ClientCommunicationPreference([
                'office_id' => $office->id,
                'client_id' => $client->id,
                'module_key' => $this->moduleKey,
                'submodule_key' => $this->submoduleKey,
                'automatic_requested' => false,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                // Default transitório: primeira mutação espera 0 e persiste versão 1.
                'lock_version' => 0,
            ]);
    }

    /**
     * Resumo reutilizável por carteira/linha sem persistir defaults.
     *
     * @return array<string, mixed>
     */
    public function summary(Office $office, Client $client): array
    {
        $this->assertClient($office, $client);

        return $this->summariesForClients($office, [(int) $client->id])[(int) $client->id];
    }

    /**
     * Resumo em lote para evitar N+1 no portfolio PGDAS-D.
     *
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    public function summariesForClients(Office $office, array $clientIds): array
    {
        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        if ($clientIds === []) {
            return [];
        }

        $preferences = ClientCommunicationPreference::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('module_key', $this->moduleKey)
            ->where('submodule_key', $this->submoduleKey)
            ->get()
            ->keyBy('client_id');
        $eligible = $this->eligibleChannelsForClients($office, $clientIds);
        $dispatches = ClientCommunicationDispatch::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('module_key', $this->moduleKey)
            ->where('submodule_key', $this->submoduleKey)
            ->get(['id', 'client_id', 'status'])
            ->groupBy('client_id');

        $result = [];
        foreach ($clientIds as $clientId) {
            /** @var ClientCommunicationPreference|null $persisted */
            $persisted = $preferences->get($clientId);
            $preference = $persisted ?? new ClientCommunicationPreference([
                'office_id' => $office->id,
                'client_id' => $clientId,
                'module_key' => $this->moduleKey,
                'submodule_key' => $this->submoduleKey,
                'automatic_requested' => false,
                'email_enabled' => false,
                'whatsapp_enabled' => false,
                'lock_version' => 0,
            ]);
            $eligibleChannels = $eligible[$clientId] ?? [];
            /** @var Collection<int, ClientCommunicationDispatch> $clientDispatches */
            $clientDispatches = $dispatches->get($clientId, collect());
            $status = $this->trackingStatus($persisted, $eligibleChannels, $clientDispatches);

            $result[$clientId] = $preference->toPublicArray($eligibleChannels, $status->value);
        }

        return $result;
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
        $this->assertCanWrite($role);
        $this->assertClient($office, $client);

        $expectedVersion = (int) $input['lock_version'];
        $automatic = (bool) $input['automatic_requested'];
        $email = (bool) $input['email_enabled'];
        $whatsapp = (bool) $input['whatsapp_enabled'];

        if ($automatic) {
            $this->assertEligibleForAutomatic($office, $client, $email, $whatsapp);
        }

        try {
            $preference = DB::transaction(function () use (
                $office,
                $client,
                $actor,
                $expectedVersion,
                $automatic,
                $email,
                $whatsapp,
            ): ClientCommunicationPreference {
                $current = ClientCommunicationPreference::query()
                    ->withoutGlobalScopes()
                    ->where('office_id', $office->id)
                    ->where('client_id', $client->id)
                    ->where('module_key', $this->moduleKey)
                    ->where('submodule_key', $this->submoduleKey)
                    ->lockForUpdate()
                    ->first();

                if ($current === null) {
                    if ($expectedVersion !== 0) {
                        throw $this->conflict();
                    }

                    return ClientCommunicationPreference::query()->create([
                        'office_id' => $office->id,
                        'client_id' => $client->id,
                        'module_key' => $this->moduleKey,
                        'submodule_key' => $this->submoduleKey,
                        'automatic_requested' => $automatic,
                        'email_enabled' => $email,
                        'whatsapp_enabled' => $whatsapp,
                        'lock_version' => 1,
                        'updated_by_user_id' => $actor->id,
                    ]);
                }

                if ((int) $current->lock_version !== $expectedVersion) {
                    throw $this->conflict();
                }

                $affected = DB::table('client_communication_preferences')
                    ->where('id', $current->id)
                    ->where('office_id', $office->id)
                    ->where('lock_version', $expectedVersion)
                    ->update([
                        'automatic_requested' => $automatic,
                        'email_enabled' => $email,
                        'whatsapp_enabled' => $whatsapp,
                        'lock_version' => $expectedVersion + 1,
                        'updated_by_user_id' => $actor->id,
                        'updated_at' => now(),
                    ]);

                if ($affected !== 1) {
                    throw $this->conflict();
                }

                return $current->refresh();
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $this->conflict();
            }

            throw $exception;
        }

        $this->audit->record(
            action: $this->auditPrefix.'.preference.update',
            result: 'SUCCESS',
            subject: $preference,
            context: [
                'client_id' => $client->id,
                'automatic_requested' => $automatic,
                'email_enabled' => $email,
                'whatsapp_enabled' => $whatsapp,
                'lock_version' => $preference->lock_version,
            ],
            userId: $actor->id,
            officeId: (int) $office->id,
        );

        return $preference;
    }

    /**
     * Lote atômico do switch geral. Canais permanecem inalterados.
     *
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
        $this->assertCanWrite($role);

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        if ($clientIds === [] || count($clientIds) > self::BATCH_LIMIT) {
            throw new HttpException(422, 'Lote deve conter entre 1 e '.self::BATCH_LIMIT.' clientes.');
        }

        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereNull('deleted_at')
            ->whereIn('id', $clientIds)
            ->get()
            ->keyBy('id');
        if ($clients->count() !== count($clientIds)) {
            throw new HttpException(422, 'Lote contém cliente inacessível ao escritório.');
        }

        $eligible = $automaticRequested
            ? $this->eligibleChannelsForClients($office, $clientIds)
            : [];

        $updated = DB::transaction(function () use (
            $office,
            $actor,
            $clientIds,
            $clients,
            $eligible,
            $automaticRequested,
        ): array {
            $preferences = ClientCommunicationPreference::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->whereIn('client_id', $clientIds)
                ->where('module_key', $this->moduleKey)
                ->where('submodule_key', $this->submoduleKey)
                ->lockForUpdate()
                ->get()
                ->keyBy('client_id');

            // Valida o lote inteiro antes da primeira escrita.
            if ($automaticRequested) {
                foreach ($clientIds as $clientId) {
                    /** @var ClientCommunicationPreference|null $preference */
                    $preference = $preferences->get($clientId);
                    /** @var Client $client */
                    $client = $clients->get($clientId);
                    if ($preference === null) {
                        throw new HttpException(422, "Cliente {$clientId} não possui canais configurados.");
                    }
                    $this->assertEligibleChannels(
                        $client,
                        (bool) $preference->email_enabled,
                        (bool) $preference->whatsapp_enabled,
                        $eligible[$clientId] ?? [],
                    );
                }
            }

            $result = [];
            foreach ($clientIds as $clientId) {
                /** @var ClientCommunicationPreference|null $preference */
                $preference = $preferences->get($clientId);
                if ($preference === null) {
                    $preference = ClientCommunicationPreference::query()->create([
                        'office_id' => $office->id,
                        'client_id' => $clientId,
                        'module_key' => $this->moduleKey,
                        'submodule_key' => $this->submoduleKey,
                        'automatic_requested' => false,
                        'email_enabled' => false,
                        'whatsapp_enabled' => false,
                        'lock_version' => 1,
                        'updated_by_user_id' => $actor->id,
                    ]);
                } else {
                    $preference->forceFill([
                        'automatic_requested' => $automaticRequested,
                        'lock_version' => ((int) $preference->lock_version) + 1,
                        'updated_by_user_id' => $actor->id,
                    ])->save();
                }

                $result[] = $preference->refresh();
            }

            return $result;
        });

        $this->audit->record(
            action: $this->auditPrefix.'.preference.bulk_update',
            result: 'SUCCESS',
            subject: $office,
            context: [
                'client_ids' => $clientIds,
                'automatic_requested' => $automaticRequested,
                'count' => count($updated),
            ],
            userId: $actor->id,
            officeId: (int) $office->id,
        );

        return $updated;
    }

    /**
     * Prévia mascarada e estritamente local.
     *
     * @return array<string, mixed>
     */
    public function preview(Office $office, Client $client): array
    {
        $this->assertClient($office, $client);
        $preference = $this->getPreferences($office, $client);
        $contacts = $this->eligibleContacts($office, $client);
        $eligibleChannels = $this->channelNames($contacts);
        $trackingStatus = $this->trackingStatusForClient($office, $client, $preference, $eligibleChannels);
        $timezone = is_string($office->timezone) && $office->timezone !== ''
            ? $office->timezone
            : 'America/Sao_Paulo';
        $periodKey = match ($this->submoduleKey) {
            'pgmei' => (string) CarbonImmutable::now($timezone)->year,
            'dctfweb' => DctfwebPeriod::toPeriodKey(
                DctfwebPeriod::expectedPa(null, $timezone)
            ),
            default => PgdasdPeriod::toPeriodKey(PgdasdPeriod::expectedPa(null, $timezone)),
        };

        $documents = [];
        if ($this->submoduleKey === self::SUBMODULE && $this->moduleKey === self::MODULE) {
            $documents = PgdasdArtifact::query()
                ->withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('client_id', $client->id)
                ->orderByDesc('observed_at')
                ->limit(20)
                ->get()
                ->map(static fn (PgdasdArtifact $artifact): array => $artifact->toTenantDocumentArray())
                ->values()
                ->all();
        }

        $channels = [
            $this->previewChannel(
                CommunicationChannel::Email,
                (bool) $preference->email_enabled,
                $contacts['email'],
            ),
            $this->previewChannel(
                CommunicationChannel::Whatsapp,
                (bool) $preference->whatsapp_enabled,
                $contacts['whatsapp'],
            ),
        ];

        $warnings = [];
        if (! $preference->email_enabled && ! $preference->whatsapp_enabled) {
            $warnings[] = 'Nenhum canal está habilitado.';
        }
        if ($preference->email_enabled && $contacts['email'] === []) {
            $warnings[] = 'E-mail habilitado sem destinatário elegível.';
        }
        if ($preference->whatsapp_enabled && $contacts['whatsapp'] === []) {
            $warnings[] = 'WhatsApp habilitado sem destinatário elegível.';
        }

        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
            ],
            'period_key' => $periodKey,
            'execution_mode' => CommunicationExecutionMode::TemplateOnly->value,
            'can_send' => false,
            'automatic_effective' => false,
            'preferences' => $preference->toPublicArray($eligibleChannels, $trackingStatus->value),
            'channels' => $channels,
            'documents' => $documents,
            'warnings' => $warnings,
        ];
    }

    /**
     * Rastreio somente leitura: não cria eventos e não marca READ.
     *
     * @return array<string, mixed>
     */
    public function tracking(Office $office, Client $client): array
    {
        $this->assertClient($office, $client);
        $preference = $this->getPreferences($office, $client);
        $eligibleChannels = $this->channelNames($this->eligibleContacts($office, $client));
        $dispatches = ClientCommunicationDispatch::query()
            ->withoutGlobalScopes()
            ->with('events')
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('module_key', $this->moduleKey)
            ->where('submodule_key', $this->submoduleKey)
            ->orderByDesc('id')
            ->get();
        $status = $this->trackingStatus(
            $preference->exists ? $preference : null,
            $eligibleChannels,
            $dispatches,
        );

        $channels = [];
        foreach ([CommunicationChannel::Email, CommunicationChannel::Whatsapp] as $channel) {
            $items = $dispatches
                ->filter(fn (ClientCommunicationDispatch $dispatch): bool => $dispatch->channel === $channel)
                ->values();
            $channelStatus = $items->isEmpty()
                ? ($status === CommunicationDispatchStatus::NotConfigured
                    ? CommunicationDispatchStatus::NotConfigured
                    : CommunicationDispatchStatus::NoHistory)
                : $this->aggregateStatus($items);
            $channels[] = [
                'channel' => $channel->value,
                'status' => $channelStatus->value,
                'dispatches' => $items
                    ->map(static fn (ClientCommunicationDispatch $dispatch): array => $dispatch->toPublicArray())
                    ->all(),
            ];
        }

        return [
            'client_id' => $client->id,
            'status' => $status->value,
            'execution_mode' => CommunicationExecutionMode::TemplateOnly->value,
            'channels' => $channels,
        ];
    }

    private function assertEligibleForAutomatic(
        Office $office,
        Client $client,
        bool $emailEnabled,
        bool $whatsappEnabled,
    ): void {
        $this->assertEligibleChannels(
            $client,
            $emailEnabled,
            $whatsappEnabled,
            $this->channelNames($this->eligibleContacts($office, $client)),
        );
    }

    /**
     * @param  list<string>  $eligibleChannels
     */
    private function assertEligibleChannels(
        Client $client,
        bool $emailEnabled,
        bool $whatsappEnabled,
        array $eligibleChannels,
    ): void {
        if (! $emailEnabled && ! $whatsappEnabled) {
            throw new HttpException(422, "Cliente {$client->id}: ativação exige ao menos um canal habilitado.");
        }

        $hasEmail = $emailEnabled && in_array(CommunicationChannel::Email->value, $eligibleChannels, true);
        $hasWhatsapp = $whatsappEnabled && in_array(CommunicationChannel::Whatsapp->value, $eligibleChannels, true);
        if (! $hasEmail && ! $hasWhatsapp) {
            throw new HttpException(422, "Cliente {$client->id}: ativação exige contato ativo elegível.");
        }
    }

    /**
     * @return array{email: list<ClientContact>, whatsapp: list<ClientContact>}
     */
    private function eligibleContacts(Office $office, Client $client): array
    {
        $contacts = ClientContact::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('receives_alerts', true)
            ->get();

        $email = [];
        $whatsapp = [];
        foreach ($contacts as $contact) {
            $rawEmail = trim((string) $contact->email);
            if ($rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL) !== false) {
                $email[] = $contact;
            }
            $phone = preg_replace('/\D/', '', (string) $contact->phone) ?? '';
            if ($contact->is_whatsapp && strlen($phone) >= 8) {
                $whatsapp[] = $contact;
            }
        }

        return ['email' => $email, 'whatsapp' => $whatsapp];
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, list<string>>
     */
    private function eligibleChannelsForClients(Office $office, array $clientIds): array
    {
        $contacts = ClientContact::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('receives_alerts', true)
            ->get();
        $result = [];
        foreach ($contacts as $contact) {
            $channels = $result[(int) $contact->client_id] ?? [];
            $email = trim((string) $contact->email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $channels[] = CommunicationChannel::Email->value;
            }
            $phone = preg_replace('/\D/', '', (string) $contact->phone) ?? '';
            if ($contact->is_whatsapp && strlen($phone) >= 8) {
                $channels[] = CommunicationChannel::Whatsapp->value;
            }
            $result[(int) $contact->client_id] = array_values(array_unique($channels));
        }

        return $result;
    }

    /**
     * @param  array{email: list<ClientContact>, whatsapp: list<ClientContact>}  $contacts
     * @return list<string>
     */
    private function channelNames(array $contacts): array
    {
        $channels = [];
        if ($contacts['email'] !== []) {
            $channels[] = CommunicationChannel::Email->value;
        }
        if ($contacts['whatsapp'] !== []) {
            $channels[] = CommunicationChannel::Whatsapp->value;
        }

        return $channels;
    }

    /**
     * @param  list<ClientContact>  $contacts
     * @return array<string, mixed>
     */
    private function previewChannel(CommunicationChannel $channel, bool $enabled, array $contacts): array
    {
        return [
            'channel' => $channel->value,
            'enabled' => $enabled,
            'eligible' => $contacts !== [],
            'recipients' => array_map(function (ClientContact $contact) use ($channel): array {
                return [
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'masked' => $this->maskContact($contact, $channel),
                ];
            }, $contacts),
        ];
    }

    private function maskContact(ClientContact $contact, CommunicationChannel $channel): string
    {
        if ($channel === CommunicationChannel::Email) {
            [$local, $domain] = array_pad(explode('@', trim((string) $contact->email), 2), 2, '');
            $first = mb_substr($local, 0, 1);

            return ($first !== '' ? $first : '*').'***@'.$domain;
        }

        $digits = preg_replace('/\D/', '', (string) $contact->phone) ?? '';

        return '***'.substr($digits, -4);
    }

    /**
     * @param  list<string>  $eligibleChannels
     */
    private function trackingStatusForClient(
        Office $office,
        Client $client,
        ClientCommunicationPreference $preference,
        array $eligibleChannels,
    ): CommunicationDispatchStatus {
        $dispatches = ClientCommunicationDispatch::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('module_key', $this->moduleKey)
            ->where('submodule_key', $this->submoduleKey)
            ->get(['id', 'status']);

        return $this->trackingStatus(
            $preference->exists ? $preference : null,
            $eligibleChannels,
            $dispatches,
        );
    }

    /**
     * @param  list<string>  $eligibleChannels
     * @param  Collection<int, ClientCommunicationDispatch>  $dispatches
     */
    private function trackingStatus(
        ?ClientCommunicationPreference $preference,
        array $eligibleChannels,
        Collection $dispatches,
    ): CommunicationDispatchStatus {
        if ($dispatches->isNotEmpty()) {
            return $this->aggregateStatus($dispatches);
        }

        $configured = $preference !== null
            && (($preference->email_enabled
                && in_array(CommunicationChannel::Email->value, $eligibleChannels, true))
                || ($preference->whatsapp_enabled
                    && in_array(CommunicationChannel::Whatsapp->value, $eligibleChannels, true)));

        return $configured
            ? CommunicationDispatchStatus::NoHistory
            : CommunicationDispatchStatus::NotConfigured;
    }

    /**
     * @param  Collection<int, ClientCommunicationDispatch>  $dispatches
     */
    private function aggregateStatus(Collection $dispatches): CommunicationDispatchStatus
    {
        $statuses = $dispatches
            ->map(static fn (ClientCommunicationDispatch $dispatch): string => $dispatch->status->value)
            ->unique()
            ->values();

        if ($statuses->count() > 1) {
            return CommunicationDispatchStatus::Partial;
        }

        return CommunicationDispatchStatus::tryFrom((string) $statuses->first())
            ?? CommunicationDispatchStatus::NoHistory;
    }

    private function assertCanWrite(OfficeRole $role): void
    {
        if (! $role->canManageClients()) {
            throw new HttpException(403, 'Perfil sem permissão para alterar comunicação.');
        }
    }

    private function assertClient(Office $office, Client $client): void
    {
        if ((int) $client->office_id !== (int) $office->id) {
            throw new RuntimeException('Cliente não pertence ao escritório ativo.');
        }
    }

    private function conflict(): ConflictHttpException
    {
        return new ConflictHttpException('Preferência alterada por outro usuário (lock_version).');
    }
}
