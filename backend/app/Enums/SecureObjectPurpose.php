<?php

namespace App\Enums;

/**
 * Finalidades distintas no SecureObjectStore (AAD metadata `purpose`).
 * Nunca reutilizar purpose entre titulares/fluxos diferentes.
 */
enum SecureObjectPurpose: string
{
    /** PFX + senha do e-CNPJ contratante (software house / contrato SERPRO global). */
    case SerproContractorPfx = 'SERPRO_CONTRACTOR_PFX';

    /** Consumer Key/Secret OAuth2 do contrato SERPRO. */
    case SerproOauthSecrets = 'SERPRO_OAUTH_SECRETS';

    /** Bearer/JWT temporários do contratante (cache cifrado). */
    case SerproBearerToken = 'SERPRO_BEARER_TOKEN';

    /** Token autenticar_procurador (por escritório/autor). */
    case SerproProcuradorToken = 'SERPRO_PROCURADOR_TOKEN';

    /** Termo de Autorização XML assinado (imutável). */
    case SerproTermoXml = 'SERPRO_TERMO_XML';

    /** A1 opcional do Autor do Pedido (consentimento explícito). */
    case SerproAuthorPfx = 'SERPRO_AUTHOR_PFX';

    /** Evidência oficial de monitoramento fiscal (resposta/artefato imutável). */
    case FiscalEvidence = 'FISCAL_EVIDENCE';

    /** Corpo de mensagem da Caixa Postal (conteúdo fiscal restrito). */
    case MailboxMessageBody = 'MAILBOX_MESSAGE_BODY';

    /** Anexo de mensagem da Caixa Postal (conteúdo fiscal restrito). */
    case MailboxAttachment = 'MAILBOX_ATTACHMENT';

    /** Documento de guia fiscal (PDF/bytes oficiais — tenant-scoped). */
    case TaxGuideDocument = 'TAX_GUIDE_DOCUMENT';

    /** Evidência oficial de pagamento de guia (independente da emissão). */
    case TaxGuidePaymentEvidence = 'TAX_GUIDE_PAYMENT_EVIDENCE';

    public function label(): string
    {
        return match ($this) {
            self::SerproContractorPfx => 'Certificado contratante SERPRO',
            self::SerproOauthSecrets => 'Credenciais OAuth SERPRO',
            self::SerproBearerToken => 'Token Bearer/JWT contratante',
            self::SerproProcuradorToken => 'Token do procurador',
            self::SerproTermoXml => 'Termo de Autorização XML',
            self::SerproAuthorPfx => 'Certificado A1 do Autor do Pedido',
            self::FiscalEvidence => 'Evidência fiscal de monitoramento',
            self::MailboxMessageBody => 'Corpo de mensagem Caixa Postal',
            self::MailboxAttachment => 'Anexo Caixa Postal',
            self::TaxGuideDocument => 'Documento de guia fiscal',
            self::TaxGuidePaymentEvidence => 'Evidência de pagamento de guia',
        };
    }

    /**
     * @return array<string, scalar|null>
     */
    public function aadBase(array $extra = []): array
    {
        return array_merge(['purpose' => $this->value], $extra);
    }
}
