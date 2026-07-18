<?php

namespace Tests\Support\Fakes;

use App\Contracts\EsocialEventClient;
use App\DTO\Esocial\EsocialEventDto;
use App\DTO\Esocial\EsocialFetchRequest;
use App\DTO\Esocial\EsocialFetchResult;
use App\Enums\EsocialEventCode;
use Carbon\CarbonImmutable;

/**
 * Double programável, exclusivo da suíte offline de eSocial.
 *
 * Não é carregado pelo autoload de produção e só pode ser resolvido após o
 * registro explícito de EsocialTestDoubleServiceProvider pelo teste.
 */
final class FakeEsocialEventClient implements EsocialEventClient
{
    /** @var list<EsocialEventDto> */
    private array $queue = [];

    private bool $sourceUnsupported = false;

    private ?string $forceError = null;

    public int $calls = 0;

    public function fetchEvents(EsocialFetchRequest $request): EsocialFetchResult
    {
        $this->calls++;

        if ($this->forceError !== null) {
            return EsocialFetchResult::failed($this->forceError);
        }

        if ($this->sourceUnsupported) {
            return EsocialFetchResult::unsupported(
                'Nenhuma API M2M eSocial configurada para este double offline.'
            );
        }

        $wanted = array_map(
            static fn (EsocialEventCode $c) => $c->value,
            $request->resolvedEventCodes(),
        );

        $matched = array_values(array_filter(
            $this->queue,
            function (EsocialEventDto $e) use ($request, $wanted): bool {
                if ($e->competencePeriodKey !== $request->competencePeriodKey) {
                    return false;
                }
                if (! in_array($e->eventCode->value, $wanted, true)) {
                    return false;
                }
                if ($request->establishment !== null && $e->establishmentCnpj !== null) {
                    return strtoupper($e->establishmentCnpj) === strtoupper((string) $request->establishment->cnpj);
                }

                return true;
            },
        ));

        return EsocialFetchResult::withEvents($matched);
    }

    public function seed(EsocialEventDto $event): self
    {
        $this->queue[] = $event;

        return $this;
    }

    /**
     * @param  list<EsocialEventDto>  $events
     */
    public function seedMany(array $events): self
    {
        foreach ($events as $event) {
            $this->seed($event);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->queue = [];
        $this->sourceUnsupported = false;
        $this->forceError = null;
        $this->calls = 0;

        return $this;
    }

    public function markUnsupported(bool $flag = true): self
    {
        $this->sourceUnsupported = $flag;

        return $this;
    }

    public function failWith(string $message): self
    {
        $this->forceError = $message;

        return $this;
    }

    public static function sampleTotalizer(
        string $competence,
        EsocialEventCode $code = EsocialEventCode::S5003,
        ?string $establishmentCnpj = null,
        string $baseFgts = '1500.00',
    ): EsocialEventDto {
        if (! $code->isTotalizer()) {
            throw new \InvalidArgumentException('sampleTotalizer exige S-5003 ou S-5013.');
        }

        $payload = json_encode([
            'evento' => $code->value,
            'competencia' => $competence,
            'base_fgts' => $baseFgts,
            'fonte' => 'esocial',
            'simulated' => true,
        ], JSON_THROW_ON_ERROR);

        return new EsocialEventDto(
            eventCode: $code,
            competencePeriodKey: $competence,
            payloadBytes: $payload,
            eventVersion: '1.0',
            receiptNumber: 'REC-TOT-'.substr(hash('sha256', $payload), 0, 12),
            establishmentCnpj: $establishmentCnpj,
            occurredAt: CarbonImmutable::now()->subDay(),
            observedAt: CarbonImmutable::now(),
            metadata: ['kind' => 'totalizer', 'simulated' => true],
        );
    }

    public static function sampleClosure(
        string $competence,
        ?string $establishmentCnpj = null,
    ): EsocialEventDto {
        $payload = json_encode([
            'evento' => EsocialEventCode::S1299->value,
            'competencia' => $competence,
            'indApuracao' => '1',
            'fonte' => 'esocial',
            'simulated' => true,
        ], JSON_THROW_ON_ERROR);

        return new EsocialEventDto(
            eventCode: EsocialEventCode::S1299,
            competencePeriodKey: $competence,
            payloadBytes: $payload,
            eventVersion: '1.0',
            receiptNumber: 'REC-CLO-'.substr(hash('sha256', $payload), 0, 12),
            establishmentCnpj: $establishmentCnpj,
            occurredAt: CarbonImmutable::now()->subHours(2),
            observedAt: CarbonImmutable::now(),
            metadata: ['kind' => 'closure', 'simulated' => true],
        );
    }
}
