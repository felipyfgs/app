<?php

namespace Tests\Unit\Domain\Work;

use App\Domain\Work\ProcessStateCalculator;
use App\Domain\Work\QueueBucketResolver;
use App\Domain\Work\WorkRiskCalculator;
use App\Enums\Work\ProcessStatus;
use App\Enums\Work\QueueBucket;
use App\Enums\Work\TaskStatus;
use App\Enums\Work\WorkRisk;
use PHPUnit\Framework\TestCase;

class ProcessStateAndRiskTest extends TestCase
{
    public function test_estado_a_fazer(): void
    {
        $calc = new ProcessStateCalculator;
        $status = $calc->derive([
            ['status' => TaskStatus::AFazer, 'is_required' => true],
            ['status' => TaskStatus::AFazer, 'is_required' => true],
        ]);
        $this->assertSame(ProcessStatus::AFazer, $status);
    }

    public function test_primeira_iniciada_vira_em_progresso(): void
    {
        $calc = new ProcessStateCalculator;
        $status = $calc->derive([
            ['status' => TaskStatus::EmProgresso, 'is_required' => true],
            ['status' => TaskStatus::AFazer, 'is_required' => true],
        ]);
        $this->assertSame(ProcessStatus::EmProgresso, $status);
    }

    public function test_critica_impedida(): void
    {
        $calc = new ProcessStateCalculator;
        $status = $calc->derive([
            ['status' => TaskStatus::Impedida, 'is_required' => true, 'is_critical' => true],
        ]);
        $this->assertSame(ProcessStatus::Impedido, $status);
    }

    public function test_obrigatorias_concluidas(): void
    {
        $calc = new ProcessStateCalculator;
        $status = $calc->derive([
            ['status' => TaskStatus::Concluida, 'is_required' => true],
            ['status' => TaskStatus::Dispensada, 'is_required' => true],
            ['status' => TaskStatus::AFazer, 'is_required' => false],
        ]);
        $this->assertSame(ProcessStatus::Concluido, $status);
    }

    public function test_risco_atrasada_e_multa_combinados(): void
    {
        $risks = (new WorkRiskCalculator)->forTask(
            TaskStatus::EmProgresso,
            '2026-07-01',
            '2026-07-10',
            true,
            1,
            '2026-07-15',
        );
        $values = array_map(fn (WorkRisk $r) => $r->value, $risks);
        $this->assertContains(WorkRisk::Atrasada->value, $values);
        $this->assertContains(WorkRisk::EmMulta->value, $values);
    }

    public function test_sem_prazo_e_sem_responsavel(): void
    {
        $risks = (new WorkRiskCalculator)->forTask(
            TaskStatus::AFazer,
            null,
            null,
            false,
            null,
            '2026-07-15',
        );
        $values = array_map(fn (WorkRisk $r) => $r->value, $risks);
        $this->assertContains(WorkRisk::SemPrazo->value, $values);
        $this->assertContains(WorkRisk::SemResponsavel->value, $values);
    }

    public function test_bucket_multa_antes_vence_hoje(): void
    {
        $resolver = new QueueBucketResolver;
        $multa = $resolver->resolve(
            TaskStatus::EmProgresso,
            [WorkRisk::Atrasada, WorkRisk::EmMulta],
            '2026-07-01',
            '2026-07-15',
        );
        $hoje = $resolver->resolve(
            TaskStatus::EmProgresso,
            [],
            '2026-07-15',
            '2026-07-15',
        );
        $this->assertSame(QueueBucket::EmMulta, $multa);
        $this->assertSame(QueueBucket::VenceHoje, $hoje);
        $this->assertTrue($multa->sortRank() < $hoje->sortRank());
    }

    public function test_ordenacao_estavel_por_id(): void
    {
        $resolver = new QueueBucketResolver;
        $a = [
            'bucket' => QueueBucket::DemaisAbertas,
            'effective_due' => '2026-07-20',
            'is_critical' => false,
            'created_at' => '2026-07-01 10:00:00',
            'id' => 2,
        ];
        $b = $a;
        $b['id'] = 1;
        $this->assertSame(1, $resolver->compare($a, $b));
    }
}
