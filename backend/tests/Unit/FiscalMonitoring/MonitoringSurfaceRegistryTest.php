<?php

namespace Tests\Unit\FiscalMonitoring;

use App\Enums\FiscalModuleKey;
use App\Enums\MonitoringChannel;
use App\Enums\MonitoringDocumentPolicy;
use App\Enums\MonitoringOfficialStateSummary;
use App\Enums\MonitoringResultKind;
use App\Enums\SerproOfficialState;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceCatalogValidator;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceContract;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceRegistry;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class MonitoringSurfaceRegistryTest extends TestCase
{
    private MonitoringSurfaceRegistry $registry;

    private MonitoringSurfaceCatalogValidator $validator;

    private OfficialServiceCatalogManifest $catalog;

    /** @var list<string> */
    private const EXPECTED_SURFACES = [
        'monitoring_dashboard',
        'simples_mei_pgdasd',
        'simples_mei_pgmei',
        'dctfweb',
        'mit',
        'fgts',
        'installments',
        'sitfis',
        'mailbox_list',
        'mailbox_detail',
        'declarations',
        'guides',
        'registrations',
        'tax_processes',
        'client_detail',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new OfficialServiceCatalogManifest;
        $this->validator = new MonitoringSurfaceCatalogValidator($this->catalog);
        $this->registry = new MonitoringSurfaceRegistry($this->catalog, $this->validator);
    }

    public function test_registry_covers_every_matrix_surface(): void
    {
        $keys = $this->registry->keys();
        sort($keys);
        $expected = self::EXPECTED_SURFACES;
        sort($expected);

        $this->assertSame($expected, $keys);
        $this->assertCount(15, $keys);
    }

    public function test_contracts_validate_against_official_catalog(): void
    {
        $result = $this->validator->validate($this->registry->all());
        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);
    }

    public function test_public_array_hides_serpro_coordinates(): void
    {
        foreach ($this->registry->all() as $contract) {
            $public = $contract->toPublicArray();
            $json = json_encode($public, JSON_THROW_ON_ERROR);

            $this->assertArrayHasKey('surface_key', $public);
            $this->assertArrayHasKey('route', $public);
            $this->assertArrayHasKey('responsibility', $public);
            $this->assertArrayHasKey('result_kind', $public);
            $this->assertArrayHasKey('allows_document', $public);
            $this->assertArrayHasKey('official_state_label', $public);
            $this->assertArrayHasKey('channel_label', $public);

            $this->assertArrayNotHasKey('operation_keys', $public);
            $this->assertArrayNotHasKey('operation_key', $public);
            $this->assertStringNotContainsString('id_sistema', $json);
            $this->assertStringNotContainsString('idSistema', $json);
            $this->assertStringNotContainsString('id_servico', $json);
            $this->assertStringNotContainsString('idServico', $json);
            $this->assertStringNotContainsString('operation_key', $json);
        }
    }

    public function test_structured_surfaces_forbid_document_action(): void
    {
        foreach (['mit', 'mailbox_list', 'mailbox_detail', 'registrations', 'tax_processes'] as $key) {
            $c = $this->registry->get($key);
            $this->assertFalse($c->allowsDocument, $key);
            $this->assertSame(MonitoringDocumentPolicy::Never, $c->documentPolicy);
            $this->assertSame(MonitoringResultKind::Structured, $c->resultKind);
        }
    }

    public function test_pgmei_is_structured_without_document_action(): void
    {
        $c = $this->registry->get('simples_mei_pgmei');
        $this->assertSame(MonitoringResultKind::Structured, $c->resultKind);
        $this->assertSame(['pgmei.dividaativa'], $c->operationKeys);
        $this->assertFalse($c->allowsDocument);
        $this->assertSame(MonitoringDocumentPolicy::Never, $c->documentPolicy);
    }

    public function test_sitfis_is_async_pdf_when_artifact(): void
    {
        $c = $this->registry->get('sitfis');
        $this->assertSame(MonitoringResultKind::AsyncPdf, $c->resultKind);
        $this->assertTrue($c->allowsDocument);
        $this->assertSame(MonitoringDocumentPolicy::AsyncWhenReady, $c->documentPolicy);
    }

    public function test_fgts_is_esocial_not_integra(): void
    {
        $c = $this->registry->get('fgts');
        $this->assertSame(MonitoringChannel::Esocial, $c->channel);
        $this->assertSame([], $c->operationKeys);
        $this->assertFalse($c->allowsDocument);
    }

    public function test_missing_operation_key_fails_closed(): void
    {
        $bogus = new MonitoringSurfaceContract(
            surfaceKey: 'bogus',
            routePattern: '/bogus',
            responsibility: 'teste',
            channel: MonitoringChannel::Integra,
            operationKeys: ['definitely.missing.operation.key'],
            officialState: MonitoringOfficialStateSummary::Production,
            resultKind: MonitoringResultKind::Structured,
            allowsDocument: false,
            documentPolicy: MonitoringDocumentPolicy::Never,
            sourceLabel: 'Bogus',
        );

        $result = $this->validator->validate([$bogus]);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('ausente no catálogo', $result['errors'][0]);
    }

    public function test_non_production_op_on_production_document_surface_fails(): void
    {
        $bad = new MonitoringSurfaceContract(
            surfaceKey: 'bad_prod',
            routePattern: '/bad',
            responsibility: 'teste',
            channel: MonitoringChannel::Integra,
            operationKeys: ['dasnsimei.transdeclaracao'],
            officialState: MonitoringOfficialStateSummary::Production,
            resultKind: MonitoringResultKind::Pdf,
            allowsDocument: true,
            documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
            sourceLabel: 'Bad',
        );

        $result = $this->validator->validate([$bad]);
        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(
                fn (string $e): bool => str_contains($e, 'não-PRODUCTION')
                    || str_contains($e, 'PRODUCTION com ops não produtivas'),
            ),
            implode('; ', $result['errors']),
        );
    }

    public function test_assert_valid_throws_on_invalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->assertValid([
            new MonitoringSurfaceContract(
                surfaceKey: 'x',
                routePattern: '/x',
                responsibility: 'x',
                channel: MonitoringChannel::Integra,
                operationKeys: ['no.such.key'],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'X',
            ),
        ]);
    }

    public function test_resolve_for_module_maps_submodules(): void
    {
        $this->assertSame(
            'simples_mei_pgmei',
            $this->registry->resolveForModule(FiscalModuleKey::SimplesMei, 'PGMEI')->surfaceKey,
        );
        $this->assertSame(
            'mit',
            $this->registry->resolveForModule(FiscalModuleKey::Dctfweb, 'MIT')->surfaceKey,
        );
        $this->expectException(InvalidArgumentException::class);
        $this->registry->resolveForModule(FiscalModuleKey::SimplesMei, 'DASN_SIMEI');
    }

    public function test_resolve_sitfis(): void
    {
        $this->assertSame(
            'sitfis',
            $this->registry->resolveForModule(FiscalModuleKey::Sitfis)->surfaceKey,
        );
    }

    public function test_unknown_surface_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->get('not_a_surface');
    }

    public function test_all_operation_keys_exist_and_match_official_state_summary(): void
    {
        $manifest = $this->catalog->load();
        $index = $this->validator->indexByOperationKey($manifest);

        foreach ($this->registry->all() as $contract) {
            foreach ($contract->operationKeys as $op) {
                $this->assertArrayHasKey($op, $index, "op {$op} em {$contract->surfaceKey}");
            }

            if ($contract->officialState === MonitoringOfficialStateSummary::Production
                && $contract->operationKeys !== []
            ) {
                foreach ($contract->operationKeys as $op) {
                    $this->assertSame(
                        SerproOfficialState::Production->value,
                        $index[$op]['official_state'],
                        "{$contract->surfaceKey}/{$op}",
                    );
                }
            }
        }
    }

    /**
     * Matriz page-payload: result_kind canônico por surface_key (task 5.4).
     *
     * @return array<string, MonitoringResultKind>
     */
    public static function matrixResultKinds(): array
    {
        return [
            'monitoring_dashboard' => MonitoringResultKind::Aggregate,
            'simples_mei_pgdasd' => MonitoringResultKind::Pdf,
            'simples_mei_pgmei' => MonitoringResultKind::Structured,
            'dctfweb' => MonitoringResultKind::Pdf,
            'mit' => MonitoringResultKind::Structured,
            'fgts' => MonitoringResultKind::Structured,
            'installments' => MonitoringResultKind::Pdf,
            'sitfis' => MonitoringResultKind::AsyncPdf,
            'mailbox_list' => MonitoringResultKind::Structured,
            'mailbox_detail' => MonitoringResultKind::Structured,
            'declarations' => MonitoringResultKind::Aggregate,
            'guides' => MonitoringResultKind::Pdf,
            'registrations' => MonitoringResultKind::Structured,
            'tax_processes' => MonitoringResultKind::Structured,
            'client_detail' => MonitoringResultKind::Aggregate,
        ];
    }

    public function test_every_surface_matches_matrix_result_kind(): void
    {
        $expected = self::matrixResultKinds();
        $this->assertSame(
            self::EXPECTED_SURFACES,
            array_keys($expected),
            'matrixResultKinds deve enumerar exatamente EXPECTED_SURFACES',
        );

        foreach ($expected as $key => $kind) {
            $c = $this->registry->get($key);
            $this->assertSame(
                $kind,
                $c->resultKind,
                "surface {$key} result_kind",
            );
        }
    }

    public function test_core_result_kinds_are_represented(): void
    {
        $kinds = [];
        foreach ($this->registry->all() as $c) {
            $kinds[$c->resultKind->value] = true;
        }

        foreach ([
            MonitoringResultKind::Structured,
            MonitoringResultKind::Pdf,
            MonitoringResultKind::AsyncPdf,
            MonitoringResultKind::Aggregate,
        ] as $case) {
            $this->assertArrayHasKey(
                $case->value,
                $kinds,
                "result_kind {$case->value} deve aparecer em ao menos uma superfície",
            );
        }
    }

    public function test_pdf_and_async_pdf_surfaces_allow_document_only_with_policy(): void
    {
        foreach ($this->registry->all() as $c) {
            if (in_array($c->resultKind, [MonitoringResultKind::Pdf, MonitoringResultKind::AsyncPdf], true)) {
                $this->assertTrue($c->allowsDocument, $c->surfaceKey);
                $this->assertContains(
                    $c->documentPolicy,
                    [MonitoringDocumentPolicy::WhenArtifact, MonitoringDocumentPolicy::AsyncWhenReady],
                    $c->surfaceKey,
                );
            }
        }

        $async = $this->registry->get('sitfis');
        $this->assertSame(MonitoringDocumentPolicy::AsyncWhenReady, $async->documentPolicy);
    }

    public function test_aggregate_surfaces_do_not_own_serpro_ops(): void
    {
        foreach (['monitoring_dashboard', 'declarations', 'client_detail'] as $key) {
            $c = $this->registry->get($key);
            $this->assertSame(MonitoringResultKind::Aggregate, $c->resultKind, $key);
            $this->assertSame(MonitoringChannel::Aggregate, $c->channel, $key);
            $this->assertSame([], $c->operationKeys, $key);
        }
    }

    public function test_never_document_surfaces_include_mit_mailbox_registrations_tax_processes(): void
    {
        $never = ['mit', 'mailbox_list', 'mailbox_detail', 'registrations', 'tax_processes', 'fgts', 'simples_mei_pgmei', 'monitoring_dashboard', 'client_detail'];
        foreach ($never as $key) {
            $c = $this->registry->get($key);
            $this->assertFalse($c->allowsDocument, $key);
            $this->assertSame(MonitoringDocumentPolicy::Never, $c->documentPolicy, $key);
        }
    }
}
