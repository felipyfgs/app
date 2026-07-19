<?php

namespace App\Domain\Work;

use App\Enums\Work\TaskStatus;
use App\Enums\Work\WorkRisk;

/**
 * Dimensões de risco combináveis para fila, KPIs e export.
 */
final class WorkRiskCalculator
{
    /**
     * @return list<WorkRisk>
     */
    public function forTask(
        TaskStatus $status,
        ?string $taskDueDate,
        ?string $processDueDate,
        bool $processSubjectToFine,
        ?int $assigneeMembershipId,
        string $todayYmd,
    ): array {
        $risks = [];

        if ($status->isTerminal()) {
            return $risks;
        }

        $effectiveDue = $taskDueDate ?: $processDueDate;

        if ($effectiveDue === null || $effectiveDue === '') {
            $risks[] = WorkRisk::SemPrazo;
        } elseif ($effectiveDue < $todayYmd) {
            $risks[] = WorkRisk::Atrasada;
            if ($processSubjectToFine) {
                $risks[] = WorkRisk::EmMulta;
            }
        }

        if ($assigneeMembershipId === null) {
            $risks[] = WorkRisk::SemResponsavel;
        }

        return $risks;
    }

    /**
     * Prazo efetivo da fila: prazo da tarefa com fallback para o do processo.
     */
    public function effectiveDueDate(?string $taskDueDate, ?string $processDueDate): ?string
    {
        if ($taskDueDate !== null && $taskDueDate !== '') {
            return $taskDueDate;
        }

        if ($processDueDate !== null && $processDueDate !== '') {
            return $processDueDate;
        }

        return null;
    }
}
