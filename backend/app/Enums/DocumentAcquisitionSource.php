<?php

namespace App\Enums;

/**
 * Proveniência de aquisição de documento (múltiplas fontes por chave).
 * Nunca inventa NSU para import/portal/consulta.
 */
enum DocumentAcquisitionSource: string
{
    case Import = 'IMPORT'; // legado síncrono — preferir MANUAL_XML / MANUAL_ZIP
    case ManualXml = 'MANUAL_XML';
    case ManualZip = 'MANUAL_ZIP';
    case AutXmlDistNsu = 'AUTXML_DIST_NSU';
    case MaOfficialPackage = 'MA_OFFICIAL_PACKAGE';
    case MaM2mRetrieval = 'MA_M2M_RETRIEVAL';
    case MaAssistedUpload = 'MA_ASSISTED_UPLOAD';
    case SvrsNfceDownloadXmlDfe = 'SVRS_NFCE_DOWNLOAD_XML_DFE';
    case Adn = 'ADN';
    case NfeDistDfe = 'NFE_DISTDFE';
    case CteDistDfe = 'CTE_DISTDFE';
    case ProtocolQuery = 'PROTOCOL_QUERY'; // só metadados/chave — não XML de guarda

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Importação manual',
            self::ManualXml => 'Importação XML',
            self::ManualZip => 'Importação ZIP',
            self::AutXmlDistNsu => 'DistDFe autXML (NSU)',
            self::MaOfficialPackage => 'Pacote oficial SEFAZ-MA',
            self::MaM2mRetrieval => 'Recuperação M2M MA',
            self::MaAssistedUpload => 'Upload assistido MA',
            self::SvrsNfceDownloadXmlDfe => 'Download XML NFC-e SVRS',
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
            self::SvrsNfceDownloadXmlDfe,
        ], true);
    }

    public function isManualImport(): bool
    {
        return in_array($this, [
            self::Import,
            self::ManualXml,
            self::ManualZip,
        ], true);
    }

    public function isAutXml(): bool
    {
        return $this === self::AutXmlDistNsu;
    }
}
