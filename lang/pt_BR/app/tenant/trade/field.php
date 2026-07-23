<?php

return [
    'navigation' => 'Campo',
    'title' => 'Campo',
    'greeting' => 'Olá, :name',
    'empty' => 'Nenhuma atividade aberta para você agora.',

    'groups' => [
        'atrasadas' => 'Atrasadas',
        'hoje' => 'Para hoje',
        'proximas' => 'Próximas',
        'sem_prazo' => 'Sem prazo',
    ],

    'push' => [
        'banner_title' => 'Ativar notificações',
        'banner_description' => 'Receba um aviso quando uma atividade for atribuída ou sua comprovação for avaliada.',
        'enable' => 'Ativar',
        'disable' => 'Desativar notificações',

        // Corpos disparados pelo backend (FieldPushNotifier / ActivitySupplierNotifier).
        'assigned_title' => 'Nova atividade para você',
        'assigned_body' => 'Você recebeu a atividade :activity.',
        'proof_approved_title' => 'Comprovação aprovada ✅',
        'proof_approved_body' => 'A comprovação de :activity foi aprovada.',
        'proof_rejected_title' => 'Comprovação reprovada',
        'proof_rejected_body' => 'A comprovação de :activity foi reprovada.',
    ],
];
