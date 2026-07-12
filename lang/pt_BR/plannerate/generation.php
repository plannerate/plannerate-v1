<?php

/**
 * Geração assíncrona de planograma (fila + notificação + histórico de execuções).
 */
return [
    'queued' => 'Gôndola sendo gerada. Você será avisado quando terminar.',
    'queue_failed' => 'Não foi possível iniciar a geração da gôndola.',

    'notification' => [
        'done_title' => 'Gôndola gerada',
        'done_message' => ':count produto(s) posicionado(s). Clique para ver o relatório.',
        'cancelled_title' => 'Geração não concluída',
        'failed_title' => 'Falha ao gerar gôndola',
        'failed_message' => 'Não foi possível gerar a gôndola. Tente novamente.',
    ],

    'status' => [
        'queued' => 'Na fila',
        'running' => 'Gerando',
        'completed' => 'Concluída',
        'failed' => 'Falhou',
    ],

    'history' => [
        'title' => 'Histórico de gerações',
        'empty' => 'Esta gôndola ainda não foi gerada.',
        'occupancy' => 'Ocupação média',
        'duration' => 'Duração',
        'in_progress' => 'Geração em andamento...',
    ],

    /*
     * Relatório da geração. Ele vive em página própria (não mais despejado embaixo
     * do canvas); o editor mostra apenas a barra-resumo com o link.
     */
    'report' => [
        'head_title' => 'Relatório da geração — :gondola',
        'title' => 'Relatório da geração',
        'link' => 'Ver relatório completo',
        'back_to_editor' => 'Voltar ao editor',
        'empty' => 'Esta gôndola ainda não foi gerada — não há relatório para exibir.',
        'failed' => 'Esta execução falhou e não produziu relatório.',
        'generated_at' => 'Gerada em :date',

        'summary' => [
            'title' => 'Última geração',
            'positioned' => ':placed de :total produtos posicionados',
            'no_space' => ':count sem espaço',
            'no_dimensions' => ':count sem dimensão',
            'height_exceeds' => ':count acima da altura',
            'suggestions' => ':count sugestão(ões)',
            'validation_errors' => ':count erro(s) de validação',
        ],

        'metrics' => [
            'positioned' => 'Posicionados',
            'coverage' => 'Cobertura',
            'no_space' => 'Sem espaço',
            'no_dimensions' => 'Sem dimensão',
            'height_exceeds' => 'Altura excede a prateleira',
            'occupancy' => 'Ocupação média',
            'duration' => 'Duração',
            'mode' => 'Modo',
        ],

        'runs' => [
            'title' => 'Execuções anteriores',
            'viewing' => 'Exibindo esta execução',
        ],
    ],
];
