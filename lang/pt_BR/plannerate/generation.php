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
];
