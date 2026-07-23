<?php

return [
    'navigation' => 'Tipos de atividade',
    'title' => 'Tipos de atividade',
    'description' => 'Configure os tipos de atividade e as exigências de auditoria na conclusão.',
    'audit' => 'Auditoria',

    'actions' => [
        'new' => 'Novo tipo',
        'edit' => 'Editar tipo',
    ],

    'fields' => [
        'name' => 'Nome',
        'slug' => 'Slug',
        'description' => 'Descrição',
        'sort_order' => 'Ordem',
        'is_active' => 'Ativo',
        'is_audit' => 'É um tipo de auditoria',
        'audit_config' => 'Exigências da auditoria',
        'status' => 'Situação',
    ],

    'audit_config' => [
        'requires_checklist' => 'Exige checklist',
        'requires_photos' => 'Exige fotos',
        'requires_reason_if_incomplete' => 'Exige motivo quando incompleto',
        'requires_non_conformity' => 'Exige registro de não conformidade',
    ],

    'messages' => [
        'created' => 'Tipo de atividade criado com sucesso.',
        'updated' => 'Tipo de atividade atualizado com sucesso.',
        'deleted' => 'Tipo de atividade excluído com sucesso.',
        'force_deleted' => 'Tipo de atividade excluído definitivamente.',
        'restored' => 'Tipo de atividade restaurado com sucesso.',
    ],
];
