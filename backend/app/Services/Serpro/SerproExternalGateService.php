<?php

namespace App\Services\Serpro;

use App\Enums\SerproExternalGateKind;
use App\Enums\SerproExternalGateStatus;
use App\Models\SerproExternalGate;
use App\Services\Audit\AuditLogger;

/**
 * Gates documentais externos (chamados SERPRO, jurídico, ops).
 * Alternativa OAuth fora de /authenticate permanece bloqueada no autenticador.
 */
final class SerproExternalGateService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Garante o conjunto mínimo de pendências da change de go-live.
     *
     * @return list<SerproExternalGate>
     */
    public function ensureBaselineGates(): array
    {
        $defs = [
            SerproExternalGateKind::OauthEndpointDivergence->value => [
                'title' => 'Chamado SERPRO: curl Área do Cliente vs /authenticate',
                'description' => 'Manter somente o endpoint canônico autenticacao.sapi.serpro.gov.br/authenticate até resposta formal. Fluxo alternativo na raiz do gateway permanece bloqueado.',
            ],
            SerproExternalGateKind::TermoXsdOfficial->value => [
                'title' => 'Solicitar XSD oficial do Termo de Autorização',
                'description' => 'Schema local é derivado e identificado como tal até XSD oficial versionado.',
            ],
            SerproExternalGateKind::CnpjAlphanumericSerialization->value => [
                'title' => 'Confirmar serialização de CNPJ alfanumérico no Termo/Eventos',
                'description' => 'FAQ declara suporte; páginas do Termo/Eventos ainda descrevem 14 dígitos numéricos.',
            ],
            SerproExternalGateKind::ContractVigencyTariff->value => [
                'title' => 'Revisão jurídico-comercial: vigência e tabela/ciclo tarifário',
                'description' => 'Divergência capa vs cláusulas; ciclo 21–20 e faixas oficiais exigem confirmação.',
            ],
            SerproExternalGateKind::SoftwareHouseLegalModel->value => [
                'title' => 'Revisão do modelo software-house / Termo em nome do escritório',
                'description' => 'Contrato permite arranjo, mas não substitui procuração RFB nem certificação jurídica da plataforma.',
            ],
            SerproExternalGateKind::OpsRolesRpoRto->value => [
                'title' => 'Definir responsáveis, on-call, RPO/RTO, escrow/KMS e custódia A1',
                'description' => 'Identidades de Office/cliente canário NÃO devem ser gravadas em artefatos OpenSpec versionados.',
            ],
        ];

        $gates = [];
        foreach ($defs as $kind => $meta) {
            $gates[] = SerproExternalGate::query()->firstOrCreate(
                ['kind' => $kind],
                [
                    'status' => SerproExternalGateStatus::Open->value,
                    'title' => $meta['title'],
                    'description' => $meta['description'],
                ]
            );
        }

        return $gates;
    }

    public function recordSubmission(
        SerproExternalGateKind $kind,
        string $ticketRef,
        ?string $evidenceRef = null,
        ?int $actorUserId = null,
    ): SerproExternalGate {
        $gate = SerproExternalGate::query()->where('kind', $kind->value)->first()
            ?? SerproExternalGate::query()->create([
                'kind' => $kind->value,
                'status' => SerproExternalGateStatus::Open->value,
                'title' => $kind->label(),
            ]);

        $gate->forceFill([
            'status' => SerproExternalGateStatus::Submitted,
            'ticket_ref' => mb_substr($ticketRef, 0, 120),
            'evidence_ref' => $evidenceRef !== null ? mb_substr($evidenceRef, 0, 200) : null,
            'submitted_at' => now(),
            'updated_by_user_id' => $actorUserId,
        ])->save();

        $this->audit->record('serpro.external_gate.submitted', 'SUCCESS', $gate, [
            'kind' => $kind->value,
            'ticket_ref' => $gate->ticket_ref,
        ], $actorUserId, null);

        return $gate->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSanitized(): array
    {
        $this->ensureBaselineGates();

        return SerproExternalGate::query()
            ->orderBy('kind')
            ->get()
            ->map->toSanitizedArray()
            ->all();
    }

    public function anyBlockingProduction(): bool
    {
        $this->ensureBaselineGates();

        return SerproExternalGate::query()
            ->get()
            ->contains(fn (SerproExternalGate $g) => $g->blocksProduction());
    }
}
