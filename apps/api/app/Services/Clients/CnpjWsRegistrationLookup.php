<?php

namespace App\Services\Clients;

use App\Contracts\CnpjRegistrationLookup;
use App\Domain\Cnpj;
use App\DTO\Cnpj\AddressData;
use App\DTO\Cnpj\ClientRegistrationData;
use App\DTO\Cnpj\CnpjRegistrationLookupResult;
use App\DTO\Cnpj\EstablishmentRegistrationData;
use App\Enums\RegistrationStatus;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Adaptador CNPJ.ws: mapeia somente a lista permitida; descarta QSA, CPF, capital, etc.
 */
final class CnpjWsRegistrationLookup implements CnpjRegistrationLookup
{
    public const CACHE_PREFIX = 'cnpj-registration-lookup:';

    public function find(string $cnpj): CnpjRegistrationLookupResult
    {
        $normalized = Cnpj::parse($cnpj)->value();
        if (! ctype_digit($normalized)) {
            throw new RuntimeException('A consulta pública ainda aceita somente CNPJ numérico. Preencha o cadastro manualmente.');
        }

        $ttl = max((int) config('services.cnpj_public_lookup.cache_seconds', 86400), 0);
        $cacheKey = self::CACHE_PREFIX.$normalized;

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return CnpjRegistrationLookupResult::fromArray($cached);
        }

        $result = $this->request($normalized);
        Cache::put($cacheKey, $result->toArray(), $ttl);

        return $result;
    }

    /**
     * Resolve proveniência a partir do cache sanitizado (sem segunda chamada externa).
     */
    public function getCached(string $cnpj): ?CnpjRegistrationLookupResult
    {
        try {
            $normalized = Cnpj::parse($cnpj)->value();
        } catch (\InvalidArgumentException) {
            return null;
        }

        $cached = Cache::get(self::CACHE_PREFIX.$normalized);
        if (! is_array($cached)) {
            return null;
        }

        return CnpjRegistrationLookupResult::fromArray($cached);
    }

    private function request(string $cnpj): CnpjRegistrationLookupResult
    {
        $baseUrl = rtrim((string) config('services.cnpj_public_lookup.url'), '/');

        try {
            $response = RateLimiter::attempt(
                'cnpj-ws-public-api',
                3,
                fn () => Http::acceptJson()
                    ->timeout(max((int) config('services.cnpj_public_lookup.timeout_seconds', 5), 1))
                    ->get("{$baseUrl}/{$cnpj}"),
                60,
            );
        } catch (ConnectionException) {
            throw new RuntimeException('A consulta pública de CNPJ está indisponível no momento.');
        }

        if ($response === false) {
            throw new RuntimeException('O limite da consulta pública foi atingido. Aguarde um minuto ou preencha o cadastro manualmente.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('CNPJ não localizado na consulta pública.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('A consulta pública de CNPJ está indisponível no momento.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $this->mapPayload($cnpj, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapPayload(string $cnpj, array $payload): CnpjRegistrationLookupResult
    {
        $legalName = $this->nullableString($payload['razao_social'] ?? null);
        if ($legalName === null) {
            throw new RuntimeException('A consulta pública retornou dados incompletos para este CNPJ.');
        }

        $establishment = is_array($payload['estabelecimento'] ?? null) ? $payload['estabelecimento'] : [];
        $natureza = is_array($payload['natureza_juridica'] ?? null) ? $payload['natureza_juridica'] : [];
        $porte = is_array($payload['porte'] ?? null) ? $payload['porte'] : [];
        $atividade = is_array($establishment['atividade_principal'] ?? null) ? $establishment['atividade_principal'] : [];
        $cidade = is_array($establishment['cidade'] ?? null) ? $establishment['cidade'] : [];
        $estado = is_array($establishment['estado'] ?? null) ? $establishment['estado'] : [];
        $pais = is_array($establishment['pais'] ?? null) ? $establishment['pais'] : [];
        $tipoLogradouro = $this->nullableString($establishment['tipo_logradouro'] ?? null);
        $situacaoRaw = $this->nullableString($establishment['situacao_cadastral'] ?? null);
        $status = RegistrationStatus::fromExternal($situacaoRaw);

        $sourceUpdatedAt = $this->nullableString(
            $establishment['atualizado_em']
                ?? $payload['atualizado_em']
                ?? $payload['updated_at']
                ?? null,
        );

        $parsed = Cnpj::parse($cnpj);

        // Telefone: DDD + número quando disponíveis; nunca QSA/sócios
        $ddd = $this->nullableString($establishment['ddd1'] ?? $establishment['ddd'] ?? null);
        $phone = $this->nullableString($establishment['telefone1'] ?? $establishment['telefone'] ?? null);
        $publicPhone = null;
        if ($ddd !== null && $phone !== null) {
            $publicPhone = $ddd.$phone;
        } elseif ($phone !== null) {
            $publicPhone = $phone;
        }

        $tipo = mb_strtoupper((string) ($establishment['tipo'] ?? ''));
        $isMatrix = in_array($tipo, ['MATRIZ', 'M', '1', 'S', 'TRUE'], true)
            || (($establishment['matriz'] ?? null) === true);

        return new CnpjRegistrationLookupResult(
            source: 'CNPJ_WS',
            sourceUpdatedAt: $sourceUpdatedAt,
            client: new ClientRegistrationData(
                rootCnpj: $parsed->root(),
                legalName: $legalName,
                legalNatureCode: $this->nullableString($natureza['id'] ?? $natureza['codigo'] ?? null),
                legalNatureName: $this->nullableString($natureza['descricao'] ?? null),
                companySizeCode: $this->nullableString($porte['id'] ?? $porte['codigo'] ?? null),
                companySizeName: $this->nullableString($porte['descricao'] ?? null),
            ),
            establishment: new EstablishmentRegistrationData(
                cnpj: $parsed->value(),
                tradeName: $this->nullableString($establishment['nome_fantasia'] ?? null),
                isMatrix: $isMatrix,
                registrationStatus: $status,
                registrationStatusAt: $this->dateOnly($establishment['data_situacao_cadastral'] ?? null),
                registrationStatusReason: $this->nullableString($establishment['motivo_situacao_cadastral'] ?? null),
                activityStartedAt: $this->dateOnly($establishment['data_inicio_atividade'] ?? null),
                mainCnaeCode: $this->nullableString($atividade['id'] ?? $atividade['codigo'] ?? null),
                mainCnaeName: $this->nullableString($atividade['descricao'] ?? null),
                address: new AddressData(
                    postalCode: $this->digitsOnly($establishment['cep'] ?? null),
                    streetType: $tipoLogradouro,
                    street: $this->nullableString($establishment['logradouro'] ?? null),
                    number: $this->nullableString($establishment['numero'] ?? null),
                    complement: $this->nullableString($establishment['complemento'] ?? null),
                    district: $this->nullableString($establishment['bairro'] ?? null),
                    city: $this->nullableString($cidade['nome'] ?? $establishment['municipio'] ?? null),
                    cityIbgeCode: $this->nullableString($cidade['ibge_id'] ?? $cidade['codigo_ibge'] ?? null),
                    state: $this->nullableString($estado['sigla'] ?? $establishment['uf'] ?? null),
                    country: $this->nullableString($pais['nome'] ?? $establishment['pais'] ?? 'Brasil'),
                ),
                publicEmail: $this->nullableString($establishment['email'] ?? null),
                publicPhone: $publicPhone,
                sourceUpdatedAt: $sourceUpdatedAt,
            ),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function digitsOnly(mixed $value): ?string
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function dateOnly(mixed $value): ?string
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }
        // Aceita ISO ou Y-m-d; não persiste lixo
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
