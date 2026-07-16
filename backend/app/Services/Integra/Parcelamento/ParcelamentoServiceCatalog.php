<?php

namespace App\Services\Integra\Parcelamento;

use App\Enums\TaxInstallmentModality;
use InvalidArgumentException;

/**
 * Catálogo de idSistema / idServico Integra-Parcelamento.
 * Service codes oficiais por modalidade (não fundir SN/MEI).
 *
 * Referência trial PARCMEI: PEDIDOSPARC203, OBTERPARC204, PARCELASPARAGERAR202,
 * DETPAGTOPARC205, GERARDAS201. Demais modalidades seguem o mesmo padrão de idServico
 * com sufixos oficiais conhecidos; ajustes finos quando swagger prod for pinado.
 */
final class ParcelamentoServiceCatalog
{
    public const SOLUTION = 'INTEGRA_PARCELAMENTO';

    public const MODULE_KEY = 'parcelamentos';

    /** @var list<string> */
    public const READ_OPERATIONS = [
        'MONITOR',
        'CONSULTAR_PEDIDOS',
        'CONSULTAR_PARCELAMENTO',
        'CONSULTAR_PARCELAS',
        'CONSULTAR_PAGAMENTO',
    ];

    /** @var list<string> */
    public const DOCUMENT_OPERATIONS = [
        'EMITIR_DOCUMENTO',
    ];

    /** @var list<string> */
    public const MUTATING_OPERATIONS = [
        'ADERIR',
        'REPARCELAR',
        'DESISTIR',
    ];

    /**
     * idServico SERPRO por operação canônica, por modalidade.
     *
     * @return array<string, string> operation => idServico
     */
    public static function serviceIds(TaxInstallmentModality $modality): array
    {
        // Números oficiais documentados no trial (PARCMEI) e paralelos por família.
        return match ($modality) {
            TaxInstallmentModality::Parcmei => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC203',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC204',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR202',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC205',
                'EMITIR_DOCUMENTO' => 'GERARDAS201',
            ],
            TaxInstallmentModality::ParcmeiEsp => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC213',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC214',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR212',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC215',
                'EMITIR_DOCUMENTO' => 'GERARDAS211',
            ],
            TaxInstallmentModality::Pertmei => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC223',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC224',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR222',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC225',
                'EMITIR_DOCUMENTO' => 'GERARDAS221',
            ],
            TaxInstallmentModality::Relpmei => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC233',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC234',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR232',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC235',
                'EMITIR_DOCUMENTO' => 'GERARDAS231',
            ],
            TaxInstallmentModality::Parcsn => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC163',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC164',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR162',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC165',
                'EMITIR_DOCUMENTO' => 'GERARDAS161',
            ],
            TaxInstallmentModality::ParcsnEsp => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC173',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC174',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR172',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC175',
                'EMITIR_DOCUMENTO' => 'GERARDAS171',
            ],
            TaxInstallmentModality::Pertsn => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC183',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC184',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR182',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC185',
                'EMITIR_DOCUMENTO' => 'GERARDAS181',
            ],
            TaxInstallmentModality::Relpsn => [
                'CONSULTAR_PEDIDOS' => 'PEDIDOSPARC193',
                'CONSULTAR_PARCELAMENTO' => 'OBTERPARC194',
                'CONSULTAR_PARCELAS' => 'PARCELASPARAGERAR192',
                'CONSULTAR_PAGAMENTO' => 'DETPAGTOPARC195',
                'EMITIR_DOCUMENTO' => 'GERARDAS191',
            ],
        };
    }

    public static function idServico(TaxInstallmentModality $modality, string $operation): string
    {
        $map = self::serviceIds($modality);
        $op = strtoupper($operation);
        if ($op === 'MONITOR') {
            $op = 'CONSULTAR_PEDIDOS';
        }
        if (! isset($map[$op])) {
            throw new InvalidArgumentException(
                "Operação {$operation} sem idServico para modalidade {$modality->value}."
            );
        }

        return $map[$op];
    }

    public static function parseModality(string $serviceCode): ?TaxInstallmentModality
    {
        return TaxInstallmentModality::tryFrom(strtoupper(trim($serviceCode)));
    }

    public static function isMutatingOperation(string $operation): bool
    {
        return in_array(strtoupper($operation), self::MUTATING_OPERATIONS, true);
    }

    public static function isReadOperation(string $operation): bool
    {
        return in_array(strtoupper($operation), self::READ_OPERATIONS, true);
    }

    public static function isDocumentOperation(string $operation): bool
    {
        return in_array(strtoupper($operation), self::DOCUMENT_OPERATIONS, true);
    }
}
