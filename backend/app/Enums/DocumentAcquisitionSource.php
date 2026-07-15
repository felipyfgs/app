<?php

namespace App\Enums;

/**
 * Proveniência de aquisição de documento (múltiplas fontes por chave).
 * Nunca inventa NSU para import/portal/consulta.
 */
enum DocumentAcquisitionSource: string
{
    case Import = 'IMPORT';
    case MaOfficialPackage = 'MA_OFFICIAL_PACKAGE';
    case MaM2mRetrieval = 'MA_M2M_RETRIEVAL';
    case MaAssistedUpload = 'MA_ASSISTED_UPLOAD';
    case Adn = 'ADN';
    case NfeDistDfe = 'NFE_DISTDFE';
    case CteDistDfe = 'CTE_DISTDFE';
    case ProtocolQuery = 'PROTOCOL_QUERY'; // só metadados/chave — não XML de guarda

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Importação manual',
            self::MaOfficialPackage => 'Pacote oficial SEFAZ-MA',
            self::MaM2mRetrieval => 'Recuperação M2M MA',
            self::MaAssistedUpload => 'Upload assistido MA',
            self::Adn => 'ADN NFS-e',
            self::NfeDistDfe => 'DistDFe NF-e',
            self::CteDistDfe => 'DistDFe CT-e',
            self::ProtocolQuery => 'Consulta de protocolo',
        };
    }

    public function isMaOutbound(): bool
    {
        return in_array($this, [
            self::MaOfficialPackage,
            self::MaM2mRetrieval,
            self::MaAssistedUpload,
        ], true);
    }
}
