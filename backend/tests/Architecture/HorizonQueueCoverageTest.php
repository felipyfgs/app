<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * 8.4 — Inventário: todo onQueue() em Jobs deve ter supervisor Horizon.
 * Flags SERPRO: jobs mapeados em serpro.jobs.flag_capabilities.
 */
class HorizonQueueCoverageTest extends TestCase
{
    public function test_all_job_queues_have_horizon_supervisor(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $horizon = require $appRoot.'/config/horizon.php';
        $supervised = $this->collectSupervisedQueues($horizon);

        $this->assertNotEmpty($supervised, 'Horizon deve declarar ao menos uma fila.');

        $jobsRoot = $appRoot.'/app/Jobs';
        $this->assertDirectoryExists($jobsRoot);

        $orphan = [];
        $queuesFound = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($jobsRoot));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            // onQueue('name') ou onQueue((string) config(...))
            if (preg_match_all("/onQueue\(\s*'([^']+)'\s*\)/", $content, $m)) {
                foreach ($m[1] as $queue) {
                    $queuesFound[$queue] = true;
                    if (! in_array($queue, $supervised, true)) {
                        $rel = substr($file->getPathname(), strlen($appRoot) + 1);
                        $orphan[] = "{$rel} → queue '{$queue}'";
                    }
                }
            }

            // Defaults via config known keys
            if (preg_match_all("/config\(\s*'serpro\.queues\.fiscal'/", $content)) {
                $queuesFound['fiscal'] = true;
                if (! in_array('fiscal', $supervised, true)
                    && ! in_array((string) (getenv('SERPRO_QUEUE_FISCAL') ?: 'fiscal'), $supervised, true)
                ) {
                    // fiscal default must be supervised (hardcoded in horizon.php)
                    if (! $this->horizonMentionsFiscal($horizon)) {
                        $orphan[] = substr($file->getPathname(), strlen($appRoot) + 1).' → fiscal (config)';
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $orphan,
            "Filas de job sem supervisor Horizon:\n".implode("\n", $orphan)
            ."\n\nSupervisadas: ".implode(', ', $supervised)
        );

        $this->assertArrayHasKey('fiscal', $queuesFound + ['fiscal' => true], 'Espera-se uso da fila fiscal');
        $this->assertTrue(
            $this->horizonMentionsFiscal($horizon),
            'config/horizon.php deve incluir a fila fiscal'
        );
    }

    public function test_serpro_jobs_declare_flag_capabilities_map(): void
    {
        // Parse estático: require de config/serpro.php chama resource_path() e
        // exige Application Laravel; Architecture suite usa PHPUnit\TestCase puro.
        $appRoot = dirname(__DIR__, 2);
        $content = (string) file_get_contents($appRoot.'/config/serpro.php');

        $this->assertMatchesRegularExpression(
            "/'flag_capabilities'\\s*=>\\s*\\[/",
            $content,
            'config/serpro.php deve declarar jobs.flag_capabilities'
        );
        $this->assertMatchesRegularExpression(
            "/'RefreshRegistrationLinksJob'\\s*=>\\s*'registrations'/",
            $content
        );
        $this->assertMatchesRegularExpression(
            "/'RefreshTaxProcessesJob'\\s*=>\\s*'tax_processes'/",
            $content
        );
        $this->assertMatchesRegularExpression(
            "/'PollEventosAtualizacaoJob'\\s*=>\\s*'authorization'/",
            $content
        );
    }

    public function test_serpro_job_classes_recheck_flags_in_handle(): void
    {
        $appRoot = dirname(__DIR__, 2);
        $withDispatch = [
            $appRoot.'/app/Jobs/Fiscal/RefreshRegistrationLinksJob.php',
            $appRoot.'/app/Jobs/Fiscal/RefreshTaxProcessesJob.php',
        ];
        $withHandleCheck = [
            ...$withDispatch,
            $appRoot.'/app/Jobs/Serpro/PollEventosAtualizacaoJob.php',
        ];

        foreach ($withHandleCheck as $path) {
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString(
                'assertAllowed',
                $content,
                basename($path).' deve revalidar flags no handle'
            );
        }

        foreach ($withDispatch as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString('dispatchIfAllowed', $content);
            $this->assertStringContainsString('flagCheckedAtDispatch', $content);
        }
    }

    /**
     * @param  array<string, mixed>  $horizon
     * @return list<string>
     */
    private function collectSupervisedQueues(array $horizon): array
    {
        $queues = [];
        $defaults = $horizon['defaults'] ?? [];
        foreach ($defaults as $supervisor) {
            if (! is_array($supervisor)) {
                continue;
            }
            $q = $supervisor['queue'] ?? [];
            if (is_string($q)) {
                $queues[] = $q;
            } elseif (is_array($q)) {
                foreach ($q as $name) {
                    if (is_string($name) && $name !== '') {
                        $queues[] = $name;
                    }
                }
            }
        }

        return array_values(array_unique($queues));
    }

    /**
     * @param  array<string, mixed>  $horizon
     */
    private function horizonMentionsFiscal(array $horizon): bool
    {
        $supervised = $this->collectSupervisedQueues($horizon);

        return in_array('fiscal', $supervised, true)
            || in_array((string) (getenv('SERPRO_QUEUE_FISCAL') ?: 'fiscal'), $supervised, true);
    }
}
