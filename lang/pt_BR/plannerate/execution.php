<?php

return [
    'common' => [
        'cancel' => 'Cancelar',
        'close' => 'Fechar',
    ],

    'bar' => [
        'loading' => 'Carregando execução...',
        'status' => 'Status',
        'evidences' => 'evidências',
        'divergences' => 'divergências',
        'sla_none' => 'Sem SLA',
        'sla_remaining' => ':days dia(s) restante(s)',
        'sla_overdue' => 'Vencido há :days dia(s)',
    ],

    'actions' => [
        'add_evidence' => 'Adicionar evidência',
        'add_divergence' => 'Apontar divergência',
        'complete' => 'Concluir execução',
    ],

    'evidence' => [
        'title' => 'Adicionar evidência',
        'description' => 'Anexe fotos da execução da gôndola na loja.',
        'type' => 'Tipo de evidência',
        'types' => [
            'general_photo' => 'Foto geral',
            'module' => 'Módulo',
            'product' => 'Produto',
            'other' => 'Outro',
        ],
        'module' => 'Módulo',
        'drop_hint' => 'Arraste imagens aqui ou clique para selecionar',
        'limits' => 'JPG, PNG ou HEIC — até 10 MB por arquivo, 10 fotos por envio.',
        'notes' => 'Observação (opcional)',
        'uploading' => 'Enviando :current de :total...',
        'save' => 'Salvar evidências',
    ],

    'divergence' => [
        'title' => 'Apontar divergência',
        'description' => 'Registre divergências encontradas durante a execução.',
        'type' => 'Tipo de divergência',
        'types' => [
            'ruptura' => 'Ruptura',
            'divergente' => 'Produto divergente',
            'falta_espaco' => 'Falta de espaço',
            'embalagem_diferente' => 'Embalagem diferente',
            'nao_localizado' => 'Não localizado',
            'sem_cadastro' => 'Sem cadastro',
            'quantidade_insuficiente' => 'Quantidade insuficiente',
            'outro' => 'Outro',
        ],
        'module' => 'Módulo',
        'shelf' => 'Prateleira',
        'position' => 'Posição',
        'product' => 'Produto',
        'notes' => 'Observação',
        'photos' => 'Fotos (opcional)',
        'save' => 'Registrar divergência',
        'registered' => 'Divergências registradas',
        'empty' => 'Nenhuma divergência registrada.',
        'status' => [
            'aberta' => 'Aberta',
            'justificada' => 'Justificada',
            'em_analise' => 'Em análise',
            'resolvida' => 'Resolvida',
            'rejeitada' => 'Rejeitada',
        ],
        'justify' => 'Justificar',
        'resolve' => 'Resolver',
    ],

    'complete' => [
        'title' => 'Concluir execução',
        'description' => 'Revise as pendências antes de encerrar a execução em loja.',
        'evidences' => 'Evidências obrigatórias: :provided/:required',
        'add_evidence' => 'Adicionar',
        'divergences' => 'Divergências pendentes: :count',
        'resolve_divergence' => 'Resolver',
        'notice' => 'Ao concluir, a execução é encerrada e a revisão periódica será gerada conforme o prazo cadastrado.',
        'confirm' => 'Concluir execução',
    ],
];
