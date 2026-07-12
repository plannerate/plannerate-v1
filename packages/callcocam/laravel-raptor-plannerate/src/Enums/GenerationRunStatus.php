<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Ciclo de vida de uma execução de geração de planograma (PlanogramGenerationRun).
 *
 * queued    → registro criado no controller, job despachado, ainda não pegou worker
 * running   → job começou (handle() marcou started_at)
 * completed → geração concluída e persistida com sucesso
 * failed    → erro técnico ou cancelamento de negócio (ex.: sem produtos elegíveis)
 */
enum GenerationRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Na fila',
            self::Running => 'Gerando',
            self::Completed => 'Concluída',
            self::Failed => 'Falhou',
        };
    }

    /** Execução ainda em andamento (o usuário pode estar aguardando a notificação). */
    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    /** Execução terminou (com sucesso ou não) — não haverá mais mudança de estado. */
    public function isFinished(): bool
    {
        return ! $this->isPending();
    }
}
