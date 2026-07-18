<?php

namespace Tests\Support\Fakes;

use App\Contracts\ParcelamentoSource;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\Enums\TaxInstallmentModality;
use App\Services\Integra\Parcelamento\ParcelamentoServiceCatalog;
use Carbon\CarbonImmutable;

/**
 * Fonte fake determinística para CI/trial — não vira evidência produtiva sem flag.
 *
 * Controles de teste via propriedades estáticas (reset em setUp).
 */
final class FakeParcelamentoSource implements ParcelamentoSource
{
    /** Simula timeout após possível envio (resultado incerto). */
    public static bool $forceTimeoutAfterSend = false;

    /** @var list<string> modalidades que o fake "não cobre" (sem poder remoto). */
    public static array $blockedModalities = [];

    /** Incrementa a cada chamada (assertions). */
    public static int $calls = 0;

    /** @var list<array{modality:string,operation:string,payload:array<string,mixed>}> */
    public static array $callLog = [];

    public static function reset(): void
    {
        self::$forceTimeoutAfterSend = false;
        self::$blockedModalities = [];
        self::$calls = 0;
        self::$callLog = [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     success: bool,
     *     simulated: bool,
     *     timeout_uncertain?: bool,
     *     error_code?: string,
     *     error_message?: string,
     *     body: array<string, mixed>
     * }
     */
    public function execute(
        TaxInstallmentModality $modality,
        string $operation,
        array $payload = [],
        ?FiscalAdapterRequest $request = null,
    ): array {
        self::$calls++;
        self::$callLog[] = [
            'modality' => $modality->value,
            'operation' => strtoupper($operation),
            'payload' => $payload,
        ];

        $operation = strtoupper($operation);

        if (in_array($modality->value, self::$blockedModalities, true)) {
            return [
                'success' => false,
                'simulated' => true,
                'error_code' => 'MODALITY_POWER_BLOCKED',
                'error_message' => "Modalidade {$modality->value} sem cobertura de poder na fonte fake.",
                'body' => [],
            ];
        }

        if (self::$forceTimeoutAfterSend && in_array($operation, ['EMITIR_DOCUMENTO', 'ADERIR', 'REPARCELAR', 'DESISTIR'], true)) {
            return [
                'success' => false,
                'simulated' => true,
                'timeout_uncertain' => true,
                'error_code' => 'TIMEOUT_AFTER_SEND',
                'error_message' => 'Timeout após possível processamento remoto.',
                'body' => [
                    'uncertain' => true,
                    'operation' => $operation,
                    'modality' => $modality->value,
                ],
            ];
        }

        if (ParcelamentoServiceCatalog::isMutatingOperation($operation)) {
            // Fake nunca executa mutação real — o adapter bloqueia antes.
            return [
                'success' => false,
                'simulated' => true,
                'error_code' => 'MUTATING_NOT_IMPLEMENTED',
                'error_message' => 'Operação mutante não implementada no fake.',
                'body' => [],
            ];
        }

        return match ($operation) {
            'CONSULTAR_PEDIDOS', 'MONITOR' => $this->ok($modality, $this->pedidosBody($modality)),
            'CONSULTAR_PARCELAMENTO' => $this->ok($modality, $this->parcelamentoBody($modality, $payload)),
            'CONSULTAR_PARCELAS' => $this->ok($modality, $this->parcelasBody($modality, $payload)),
            'CONSULTAR_PAGAMENTO' => $this->ok($modality, $this->pagamentoBody($modality, $payload)),
            'EMITIR_DOCUMENTO' => $this->ok($modality, $this->emitirBody($modality, $payload)),
            default => [
                'success' => false,
                'simulated' => true,
                'error_code' => 'UNKNOWN_OPERATION',
                'error_message' => "Operação {$operation} desconhecida.",
                'body' => [],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{success:bool,simulated:bool,body:array<string,mixed>}
     */
    private function ok(TaxInstallmentModality $modality, array $body): array
    {
        return [
            'success' => true,
            'simulated' => true,
            'body' => array_merge([
                'simulated' => true,
                'idSistema' => $modality->value,
                'idServico' => ParcelamentoServiceCatalog::idServico(
                    $modality,
                    (string) ($body['_operation'] ?? 'CONSULTAR_PEDIDOS'),
                ),
            ], $body),
        ];
    }

    /**
     * Pedidos: cada modalidade usa prefixo de id distinto (não fundir).
     *
     * @return array<string, mixed>
     */
    private function pedidosBody(TaxInstallmentModality $modality): array
    {
        $prefix = str_replace('-', '', $modality->value);
        $orderId = $prefix.'-PED-1001';

        return [
            '_operation' => 'CONSULTAR_PEDIDOS',
            'pedidos' => [
                [
                    'numero' => $orderId,
                    'situacao' => 'EM_ANDAMENTO',
                    'dataPedido' => CarbonImmutable::now()->subMonths(3)->toDateString(),
                    'quantidadeParcelas' => 12,
                    'valorTotalCentavos' => 120_000,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function parcelamentoBody(TaxInstallmentModality $modality, array $payload): array
    {
        $numero = (string) ($payload['numeroParcelamento'] ?? $this->defaultOrderId($modality));

        return [
            '_operation' => 'CONSULTAR_PARCELAMENTO',
            'numeroParcelamento' => $numero,
            'situacao' => 'EM_ANDAMENTO',
            'parcelas' => $this->sampleParcelRows(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function parcelasBody(TaxInstallmentModality $modality, array $payload): array
    {
        return [
            '_operation' => 'CONSULTAR_PARCELAS',
            'numeroParcelamento' => $payload['numeroParcelamento'] ?? $this->defaultOrderId($modality),
            'parcelasParaGerar' => [
                [
                    'parcela' => CarbonImmutable::now()->format('Ym'),
                    'vencimento' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                    'valorCentavos' => 10_000,
                    'disponivel' => true,
                ],
                [
                    // Vencida sem pagamento — projeção deve ser ATTENTION/PENDING, não "inadimplente"
                    'parcela' => CarbonImmutable::now()->subMonth()->format('Ym'),
                    'vencimento' => CarbonImmutable::now()->subMonth()->endOfMonth()->toDateString(),
                    'valorCentavos' => 10_000,
                    'disponivel' => true,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pagamentoBody(TaxInstallmentModality $modality, array $payload): array
    {
        $anoMes = (string) ($payload['anoMesParcela'] ?? CarbonImmutable::now()->subMonths(2)->format('Ym'));
        $confirmado = (bool) ($payload['force_paid'] ?? false);

        // Por padrão: parcela antiga com confirmação; a vencida recente sem confirmação.
        if (! array_key_exists('force_paid', $payload)) {
            $confirmado = $anoMes === CarbonImmutable::now()->subMonths(2)->format('Ym');
        }

        return [
            '_operation' => 'CONSULTAR_PAGAMENTO',
            'numeroParcelamento' => $payload['numeroParcelamento'] ?? $this->defaultOrderId($modality),
            'anoMesParcela' => $anoMes,
            'pagamentoConfirmado' => $confirmado,
            'dataPagamento' => $confirmado ? CarbonImmutable::now()->subMonths(2)->addDays(5)->toDateString() : null,
            'valorPagoCentavos' => $confirmado ? 10_000 : null,
            'referencia' => $confirmado ? 'PAG-'.$modality->value.'-'.$anoMes : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function emitirBody(TaxInstallmentModality $modality, array $payload): array
    {
        $parcela = (string) ($payload['parcelaParaEmitir'] ?? CarbonImmutable::now()->format('Ym'));
        $pdfStub = base64_encode("%PDF-1.4 fake {$modality->value} {$parcela}");
        $bytes = base64_decode($pdfStub, true) ?: $pdfStub;

        return [
            '_operation' => 'EMITIR_DOCUMENTO',
            'parcelaParaEmitir' => $parcela,
            'documento' => [
                'tipo' => $modality->isMei() ? 'DAS' : 'DAS',
                'identificador' => 'DOC-'.$modality->value.'-'.$parcela,
                'conteudoBase64' => base64_encode($bytes),
                'contentType' => 'application/pdf',
                'valorCentavos' => 10_000,
                'vencimento' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'validoAte' => CarbonImmutable::now()->addDays(5)->toIso8601String(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleParcelRows(): array
    {
        $now = CarbonImmutable::now();

        return [
            [
                'parcela' => $now->subMonths(2)->format('Ym'),
                'numero' => 1,
                'vencimento' => $now->subMonths(2)->endOfMonth()->toDateString(),
                'valorCentavos' => 10_000,
                'situacaoFonte' => 'PAGA',
            ],
            [
                'parcela' => $now->subMonth()->format('Ym'),
                'numero' => 2,
                'vencimento' => $now->subMonth()->endOfMonth()->toDateString(),
                'valorCentavos' => 10_000,
                'situacaoFonte' => 'EM_ABERTO',
            ],
            [
                'parcela' => $now->format('Ym'),
                'numero' => 3,
                'vencimento' => $now->endOfMonth()->toDateString(),
                'valorCentavos' => 10_000,
                'situacaoFonte' => 'A_VENCER',
            ],
        ];
    }

    private function defaultOrderId(TaxInstallmentModality $modality): string
    {
        $prefix = str_replace('-', '', $modality->value);

        return $prefix.'-PED-1001';
    }
}
