<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\FiscalIdentity;
use App\DTO\Serpro\IntegraRequest;
use App\Enums\AuthorIdentityType;
use App\Services\Serpro\Catalog\OperationKeyMap;
use App\Services\Serpro\IntegraBillingClassifier;
use InvalidArgumentException;
use Tests\TestCase;

final class IntegraGatewaySecureTest extends TestCase
{
    public function test_fiscal_identity_preserves_alphanumeric_cnpj_uppercase(): void
    {
        $id = FiscalIdentity::fromNumero('12abc.345.01de/35');
        $this->assertSame(AuthorIdentityType::Cnpj, $id->tipo);
        $this->assertSame('12ABC34501DE35', $id->numero);
        $this->assertSame(2, $id->envelopeTipo());
    }

    public function test_fiscal_identity_rejects_invalid_cpf_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FiscalIdentity(AuthorIdentityType::Cpf, '123');
    }

    public function test_fiscal_identity_rejects_partial_placeholder_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FiscalIdentity::fromNumero('123');
    }

    public function test_integra_request_rejects_root_cnpj_as_contributor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333',
            operationKey: 'sitfis.solicitar_protocolo',
        );
    }

    public function test_integra_request_requires_operation_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: '  ',
        );
    }

    public function test_request_tag_is_opaque_deterministic_and_max_32(): void
    {
        $a = new IntegraRequest(
            officeId: 9,
            clientId: 3,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
            idempotencyKey: 'idem-1',
        );
        $b = new IntegraRequest(
            officeId: 9,
            clientId: 3,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
            idempotencyKey: 'idem-1',
        );

        $this->assertSame($a->resolvedRequestTag(), $b->resolvedRequestTag());
        $this->assertSame(32, strlen($a->resolvedRequestTag()));
        $this->assertStringNotContainsString('11222333000181', $a->resolvedRequestTag());
        $this->assertStringNotContainsString('52998224725', $a->resolvedRequestTag());
    }

    public function test_billing_classifier_official_rules(): void
    {
        $c = new IntegraBillingClassifier;

        $this->assertFalse($c->isBillableAttempt('Apoiar', 200));
        $this->assertFalse($c->isBillableAttempt('Monitorar', 202));
        $this->assertFalse($c->isBillableAttempt('Consultar', 204));
        $this->assertFalse($c->isBillableAttempt('Consultar', 304));
        $this->assertFalse($c->isBillableAttempt('Consultar', 401));
        $this->assertFalse($c->isBillableAttempt('Consultar', 429));
        $this->assertFalse($c->isBillableAttempt('Consultar', 503));
        $this->assertTrue($c->isBillableAttempt('Consultar', 200));
        $this->assertTrue($c->isBillableAttempt('Consultar', 202));
        $this->assertTrue($c->isBillableAttempt('Consultar', 403));
        $this->assertTrue($c->isBillableAttempt('Emitir', 200));
        $this->assertFalse($c->isBillableAttempt('Emitir', 500));
    }

    public function test_legacy_sicalc_mutation_resolves_to_official_operation_key(): void
    {
        $this->assertSame(
            'sicalc.consolidargerardarf',
            OperationKeyMap::require(null, 'INTEGRA_PAGAMENTO', 'SICALC', 'EMITIR_GUIA'),
        );
    }
}
