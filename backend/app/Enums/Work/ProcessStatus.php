<?php

namespace App\Enums\Work;

/**
 * Estado materializado do processo — derivado das tarefas (não editável livremente).
 */
enum ProcessStatus: string
{
    case AFazer = 'A_FAZER';
    case EmProgresso = 'EM_PROGRESSO';
    case Impedido = 'IMPEDIDO';
    case Concluido = 'CONCLUIDO';
    case Arquivado = 'ARQUIVADO';
}
