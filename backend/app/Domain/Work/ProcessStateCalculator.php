<?php

namespace App\Domain\Work;

use App\Enums\Work\ProcessStatus;
use App\Enums\Work\TaskStatus;

/**
 * Recalcula o estado materializado do processo a partir das tarefas.
 *
 * Regras:
 * - A_FAZER enquanto nenhuma tarefa iniciada
 * - IMPEDIDO se existir tarefa crítica em IMPEDIDA
 * - CONCLUIDO quando todas as obrigatórias estão CONCLUIDA/DISPENSADA
 * - EM_PROGRESSO nos demais casos com execução
 */
final class ProcessStateCalculator
{
    /**
     * @param  list<array{status: TaskStatus|string, is_required?: bool, is_critical?: bool}>  $tasks
     */
    public function derive(array $tasks, ?ProcessStatus $current = null): ProcessStatus
    {
        if ($current === ProcessStatus::Arquivado) {
            return ProcessStatus::Arquivado;
        }

        if ($tasks === []) {
            return ProcessStatus::AFazer;
        }

        $normalized = array_map(function (array $t): array {
            $status = $t['status'] instanceof TaskStatus
                ? $t['status']
                : TaskStatus::from((string) $t['status']);

            return [
                'status' => $status,
                'is_required' => (bool) ($t['is_required'] ?? true),
                'is_critical' => (bool) ($t['is_critical'] ?? false),
            ];
        }, $tasks);

        $hasCriticalBlocked = false;
        $anyStarted = false;
        $required = [];
        foreach ($normalized as $t) {
            if ($t['is_critical'] && $t['status'] === TaskStatus::Impedida) {
                $hasCriticalBlocked = true;
            }
            if ($t['status'] !== TaskStatus::AFazer) {
                $anyStarted = true;
            }
            if ($t['is_required']) {
                $required[] = $t;
            }
        }

        if ($hasCriticalBlocked) {
            return ProcessStatus::Impedido;
        }

        if ($required !== []) {
            $allRequiredDone = true;
            foreach ($required as $t) {
                if (! $t['status']->countsAsSatisfiedForRequired()) {
                    $allRequiredDone = false;
                    break;
                }
            }
            if ($allRequiredDone) {
                return ProcessStatus::Concluido;
            }
        } else {
            // Sem obrigatórias: concluído se todas terminais
            $allDone = true;
            foreach ($normalized as $t) {
                if (! $t['status']->isTerminal()) {
                    $allDone = false;
                    break;
                }
            }
            if ($allDone) {
                return ProcessStatus::Concluido;
            }
        }

        if (! $anyStarted) {
            return ProcessStatus::AFazer;
        }

        return ProcessStatus::EmProgresso;
    }
}
