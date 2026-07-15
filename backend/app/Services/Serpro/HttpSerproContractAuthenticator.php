<?php

namespace App\Services\Serpro;

use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\SerproAuthToken;
use App\Models\SerproContract;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * OAuth2 client_credentials + mTLS (role-type TERCEIROS, e-CNPJ contratante).
 */
final class HttpSerproContractAuthenticator implements SerproContractAuthenticator
{
    public function __construct(
        private readonly SerproContractService $contracts,
        private readonly SerproTokenCache $tokenCache,
        private readonly SerproHttpTransport $transport,
        private readonly AuditLogger $audit,
    ) {}

    public function authenticate(SerproContract $contract): SerproAuthToken
    {
        if (! $contract->isUsable()) {
            throw new RuntimeException('Contrato SERPRO não está ACTIVE.');
        }

        $cached = $this->tokenCache->get($contract);
        if ($cached !== null) {
            return $cached;
        }

        return $this->tokenCache->withRefreshLock($contract, function () use ($contract): SerproAuthToken {
            $cached = $this->tokenCache->get($contract);
            if ($cached !== null) {
                return $cached;
            }

            return $this->fetchAndStore($contract);
        });
    }

    public function invalidate(SerproContract $contract): void
    {
        $this->tokenCache->invalidate($contract);
    }

    private function fetchAndStore(SerproContract $contract): SerproAuthToken
    {
        $pfx = $this->contracts->loadPfxMaterial($contract);
        $oauth = $this->contracts->loadOauthSecrets($contract);

        // Validar fingerprint/identidade antes da chamada
        if ($contract->fingerprint_sha256 === null || $contract->fingerprint_sha256 === '') {
            $this->blockContract($contract, 'Fingerprint do certificado ausente.');
            throw new RuntimeException('Contrato bloqueado: fingerprint ausente.');
        }

        $tokenUrl = (string) config('serpro.oauth.token_url');
        $roleType = (string) config('serpro.oauth.role_type', 'TERCEIROS');
        $correlationId = $this->audit->correlationId();

        $body = http_build_query([
            'grant_type' => 'client_credentials',
        ]);

        $basic = base64_encode($oauth['consumer_key'].':'.$oauth['consumer_secret']);
        $headers = [
            'Authorization: Basic '.$basic,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'role-type: '.$roleType,
            'x-cnpj: '.$contract->contractor_cnpj,
        ];

        try {
            $response = $this->transport->request(
                'POST',
                $tokenUrl,
                $pfx,
                $body,
                $headers,
                $correlationId,
            );
        } catch (Throwable $e) {
            $this->audit->record('serpro.oauth.token', 'FAILED', $contract, [
                'message' => 'Falha de transporte OAuth',
                'correlation_id' => $correlationId,
            ], null, null);
            throw new RuntimeException('Falha ao autenticar contrato SERPRO.', 0, $e);
        } finally {
            unset($pfx, $oauth, $basic);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->audit->record('serpro.oauth.token', 'FAILED', $contract, [
                'http_status' => $response['status'],
                'correlation_id' => $correlationId,
            ], null, null);

            if (in_array($response['status'], [401, 403], true)) {
                $this->blockContract($contract, 'Autenticação OAuth rejeitada ('.$response['status'].').');
            }

            throw new RuntimeException('OAuth SERPRO retornou HTTP '.$response['status'].'.');
        }

        /** @var array{access_token?: string, token_type?: string, expires_in?: int, jwt?: string} $json */
        $json = json_decode($response['body'], true);
        if (! is_array($json) || empty($json['access_token'])) {
            throw new RuntimeException('Resposta OAuth SERPRO inválida.');
        }

        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        $token = new SerproAuthToken(
            accessToken: (string) $json['access_token'],
            tokenType: (string) ($json['token_type'] ?? 'Bearer'),
            expiresAt: CarbonImmutable::now()->addSeconds(max(60, $expiresIn)),
            jwt: isset($json['jwt']) ? (string) $json['jwt'] : null,
            fromCache: false,
        );

        $this->tokenCache->put($contract, $token);

        $contract->health_status = 'OK';
        $contract->health_message = 'OAuth ok.';
        $contract->last_verified_at = now();
        $contract->save();

        $this->audit->record('serpro.oauth.token', 'SUCCESS', $contract, [
            'expires_at' => $token->expiresAt->toIso8601String(),
            'correlation_id' => $correlationId,
            'from_cache' => false,
        ], null, null);

        return $token;
    }

    /**
     * Alinha com {@see SerproContractService::block}: marca BLOCKED + kill switch global.
     */
    private function blockContract(SerproContract $contract, string $reason): void
    {
        $this->contracts->block($contract, $reason.' [source=oauth]', null);
    }
}
