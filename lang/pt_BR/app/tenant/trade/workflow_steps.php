<?php

return [
    'build' => 'Montar workflow',
    'pick_template' => 'Escolha uma etapa (template)…',
    'pick_category' => 'Escolha uma categoria…',
    'untitled' => 'Etapa',
    'due' => 'Prazo',
    'empty' => 'Nenhuma etapa no workflow.',
    'prompt_skip' => 'Informe o motivo para pular a etapa:',

    'actions' => [
        'start' => 'Iniciar',
        'complete' => 'Concluir',
        'skip' => 'Pular',
        'reset' => 'Reabrir',
        'set_current' => 'Definir como atual',
        'apply' => 'Aplicar categoria',
    ],

    'messages' => [
        'added' => 'Etapa adicionada ao workflow.',
        'template_applied' => ':count etapa(s) adicionada(s) do template.',
        'reordered' => 'Ordem das etapas atualizada.',
        'dates_recalculated' => 'Datas recalculadas.',
        'updated' => 'Etapa atualizada.',
        'removed' => 'Etapa removida.',
        'cannot_move' => 'Você não tem permissão para mover esta etapa.',
        'not_deletable' => 'Não é possível remover etapas em andamento ou concluídas.',
        'started' => 'Etapa iniciada.',
        'completed' => 'Etapa concluída.',
        'skipped' => 'Etapa pulada.',
        'failed' => 'Etapa marcada como falhada.',
        'reset' => 'Etapa reaberta.',
        'invalid_transition' => [
            'start' => 'Esta etapa não pode ser iniciada agora.',
            'complete' => 'Esta etapa não pode ser concluída agora.',
            'skip' => 'Esta etapa não pode ser pulada.',
        ],
    ],
];
