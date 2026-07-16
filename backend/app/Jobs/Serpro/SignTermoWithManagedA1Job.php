<?php

namespace App\Jobs\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\AuthorCertificateMode;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Services\Audit\AuditLogger;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Integra\TermoXmlSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Assina Termo com A1 gerenciado (consentimento versionado + ADMIN + 2FA na API).
 * Temporários protegidos e limpeza comprovada; não retem PEM em disco.
 */
final class SignTermoWithManagedA1Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $officeId,
        public readonly string $environment,
        public readonly int $authorizationId,
        public readonly ?int $actorUserId = null,
        public readonly ?string $correlationId = null,
    ) {
        // Fila fiscal com supervisor Horizon (config/horizon.php + serpro.queues.fiscal).
        $this->onQueue((string) config('serpro.queues.fiscal', 'fiscal'));
    }

    public function handle(
        SecureObjectStore $store,
        TermoXmlSigner $signer,
        OfficeSerproAuthorizationService $authorizations,
        AuditLogger $audit,
    ): void {
        $office = Office::query()->findOrFail($this->officeId);
        $env = SerproEnvironment::from(strtoupper($this->environment));
        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $this->officeId)
            ->whereKey($this->authorizationId)
            ->firstOrFail();

        if ($auth->certificate_mode !== AuthorCertificateMode::ManagedA1) {
            throw new RuntimeException('Modo de certificado não é A1 gerenciado.');
        }
        if (! $auth->managed_a1_consent || $auth->author_pfx_vault_object_id === null) {
            throw new RuntimeException('Consentimento/A1 gerenciado ausente.');
        }

        $meta = is_array($auth->metadata) ? $auth->metadata : [];
        $draftId = $meta['termo_draft_vault_object_id'] ?? null;
        $draftSha = $meta['termo_draft_sha256'] ?? null;
        if (! is_string($draftId) || $draftId === '' || ! is_string($draftSha) || $draftSha === '') {
            throw new RuntimeException('Draft do Termo ausente; gere o draft antes de assinar.');
        }

        $draftAad = SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => $office->id,
            'environment' => $env->value,
            'kind' => 'draft',
            'sha256' => $draftSha,
            'author_identity' => $auth->author_identity,
        ]);
        $unsignedXml = $store->get($draftId, $draftAad);

        $pfxAad = SecureObjectPurpose::SerproAuthorPfx->aadBase([
            'office_id' => $office->id,
            'environment' => $env->value,
            'fingerprint' => $auth->author_fingerprint_sha256,
            'author_identity' => $auth->author_identity,
        ]);
        $pfxPayload = $store->get($auth->author_pfx_vault_object_id, $pfxAad);
        /** @var array{pfx?: string, password?: string} $decoded */
        $decoded = json_decode($pfxPayload, true, 512, JSON_THROW_ON_ERROR);
        $pfxB64 = $decoded['pfx'] ?? null;
        $password = $decoded['password'] ?? null;
        unset($pfxPayload, $decoded);

        if (! is_string($pfxB64) || $pfxB64 === '' || ! is_string($password)) {
            throw new RuntimeException('Material A1 incompleto no vault.');
        }

        $pfxBinary = base64_decode($pfxB64, true);
        unset($pfxB64);
        if ($pfxBinary === false || $pfxBinary === '') {
            throw new RuntimeException('PFX A1 inválido no vault.');
        }

        $tempDir = null;
        $tempPfx = null;
        try {
            // Temporário protegido (0600) apenas se necessário para libs futuras; assinatura em memória.
            $tempDir = sys_get_temp_dir().'/serpro-termo-'.$this->officeId.'-'.bin2hex(random_bytes(8));
            if (! mkdir($tempDir, 0700, true) && ! is_dir($tempDir)) {
                throw new RuntimeException('Falha ao criar diretório temporário protegido.');
            }
            $tempPfx = $tempDir.'/author.pfx';
            if (file_put_contents($tempPfx, $pfxBinary, LOCK_EX) === false) {
                throw new RuntimeException('Falha ao gravar PFX temporário.');
            }
            chmod($tempPfx, 0600);

            $signedXml = $signer->signWithPfx($unsignedXml, $pfxBinary, $password);
            unset($pfxBinary, $password, $unsignedXml);

            $authorizations->uploadTermo($office, $env, $signedXml, $this->actorUserId);
            unset($signedXml);

            $audit->record('serpro.authorization.termo_managed_a1_sign', 'SUCCESS', $auth, [
                'environment' => $env->value,
                'authorization_id' => $auth->id,
            ], $this->actorUserId, $office->id);
        } catch (Throwable $e) {
            $audit->record('serpro.authorization.termo_managed_a1_sign', 'FAILED', $auth, [
                'environment' => $env->value,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ], $this->actorUserId, $office->id);
            throw $e;
        } finally {
            unset($pfxBinary, $password);
            $this->secureCleanup($tempPfx, $tempDir);
        }
    }

    private function secureCleanup(?string $tempPfx, ?string $tempDir): void
    {
        if (is_string($tempPfx) && is_file($tempPfx)) {
            $size = filesize($tempPfx) ?: 0;
            if ($size > 0) {
                file_put_contents($tempPfx, str_repeat("\0", $size));
            }
            @unlink($tempPfx);
        }
        if (is_string($tempDir) && is_dir($tempDir)) {
            @rmdir($tempDir);
        }
    }
}
