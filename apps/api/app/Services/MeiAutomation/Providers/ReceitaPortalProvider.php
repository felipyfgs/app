<?php

namespace App\Services\MeiAutomation\Providers;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\FiscalSourceProvenance;
use App\Enums\MeiAutomationStatus;
use App\Enums\MeiProvider;
use App\Exceptions\MeiAutomationTransportException;
use App\Models\MeiAutomationAttempt;
use App\Services\MeiAutomation\MeiAutomationAttemptRepository;
use App\Services\MeiAutomation\MeiAutomationAttemptService;
use App\Services\MeiAutomation\MeiAutomationClient;
use App\Services\MeiAutomation\MeiAutomationSyncService;

final class ReceitaPortalProvider implements MeiOperationProvider
{
    public function __construct(
        private readonly MeiAutomationAttemptRepository $attempts,
        private readonly MeiAutomationAttemptService $attemptService,
        private readonly MeiAutomationClient $client,
        private readonly MeiAutomationSyncService $sync,
    ) {}

    public function execute(FiscalAdapterRequest $request, string $operationKey): MeiProviderOutcome
    {
        if (! (bool) config('mei_automation.fixture_enabled', false)) {
            return $this->unavailable('Provider portal live ainda não habilitado nesta versão.');
        }

        $attempt = $this->resolveFixtureAttempt($request);
        if ($attempt->external_job_id === null) {
            try {
                $job = $this->client->create($this->attemptService->jobRequest($attempt, []));
                $attempt = $this->attempts->synchronize($attempt, $job, ['result_scope' => 'fixture']);
                if ($attempt->status->shouldPoll()) {
                    $this->sync->schedule($attempt);
                }
            } catch (MeiAutomationTransportException) {
                return $this->unavailable('Sidecar MEI indisponível antes da execução.', $attempt);
            }
        }

        return $this->fixtureOutcome($request, $attempt);
    }

    private function resolveFixtureAttempt(FiscalAdapterRequest $request): MeiAutomationAttempt
    {
        $attemptId = $request->progress['mei_automation_attempt_id'] ?? null;
        if (is_int($attemptId) || (is_string($attemptId) && ctype_digit($attemptId))) {
            return $this->attempts->findForOffice((int) $request->office->id, (int) $attemptId);
        }

        return $this->attemptService->start(
            office: $request->office,
            client: $request->client,
            operationKey: 'fixture.health',
            provider: MeiProvider::Fixture,
            idempotencyKey: 'fixture:'.hash('sha256', (string) $request->run->idempotency_key),
            input: [],
            run: $request->run,
        );
    }

    private function fixtureOutcome(FiscalAdapterRequest $request, MeiAutomationAttempt $attempt): MeiProviderOutcome
    {
        if ($attempt->status->shouldPoll()) {
            return new MeiProviderOutcome(
                result: new FiscalAdapterResult(
                    result: FiscalRunResult::Requeued,
                    situation: FiscalSituation::Processing,
                    coverage: FiscalCoverage::Unknown,
                    shouldRequeue: true,
                    progress: [
                        ...$request->progress,
                        'mei_automation_attempt_id' => $attempt->id,
                        'mei_automation_provider' => MeiProvider::Fixture->value,
                    ],
                    requeueAfterSeconds: $this->sync->pollIntervalSeconds(),
                ),
                provider: MeiProvider::Fixture,
                attempt: $attempt,
            );
        }

        if ($attempt->status === MeiAutomationStatus::Succeeded) {
            $request->run->forceFill(['source_provenance' => FiscalSourceProvenance::Unverified])->save();

            return new MeiProviderOutcome(
                result: new FiscalAdapterResult(
                    result: FiscalRunResult::Success,
                    situation: FiscalSituation::Unknown,
                    coverage: FiscalCoverage::Unknown,
                    evidenceBytes: json_encode(['fixture' => true, 'attempt_id' => $attempt->id], JSON_THROW_ON_ERROR),
                    sourceVersion: 'fixture-v1',
                    normalized: ['fixture' => true],
                ),
                provider: MeiProvider::Fixture,
                attempt: $attempt,
            );
        }

        $submitted = $attempt->submitted_at !== null || $attempt->status === MeiAutomationStatus::Uncertain;
        $fallbackEligible = ! $submitted && in_array($attempt->status, [
            MeiAutomationStatus::Failed,
            MeiAutomationStatus::SyncLost,
        ], true);

        return new MeiProviderOutcome(
            result: FiscalAdapterResult::failed(
                'Fixture do portal MEI não concluiu.',
                $submitted ? 'PORTAL_RESULT_UNCERTAIN' : 'PORTAL_UNAVAILABLE',
            ),
            provider: MeiProvider::Fixture,
            fallbackEligible: $fallbackEligible,
            submitted: $submitted,
            fallbackReason: $fallbackEligible ? 'PORTAL_UNAVAILABLE' : null,
            attempt: $attempt,
        );
    }

    private function unavailable(string $message, ?MeiAutomationAttempt $attempt = null): MeiProviderOutcome
    {
        return new MeiProviderOutcome(
            result: FiscalAdapterResult::failed($message, 'PORTAL_UNAVAILABLE'),
            provider: MeiProvider::ReceitaPortal,
            fallbackEligible: true,
            submitted: false,
            fallbackReason: 'PORTAL_UNAVAILABLE',
            attempt: $attempt,
        );
    }
}
