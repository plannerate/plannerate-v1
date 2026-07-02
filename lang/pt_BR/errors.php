<?php

/*
 * Textos das páginas de erro personalizadas do Plannerate.
 * Cada código de status HTTP tem um título curto e uma descrição amigável.
 * Consumido pela página Inertia resources/js/pages/Error.vue via useT('errors.{status}.*').
 */

return [
    // Ação/link de retorno exibido em todas as páginas de erro
    'actions' => [
        'home' => 'Voltar ao início',
        'back' => 'Voltar à página anterior',
        'login' => 'Entrar novamente',
    ],

    // Rótulo genérico acima do código (ex.: "Erro 404")
    'label' => 'Erro',

    // 403 — Acesso negado
    '403' => [
        'title' => 'Acesso negado',
        'description' => 'Você não tem permissão para acessar esta página. Se acredita que isso é um engano, fale com o administrador da sua loja.',
    ],

    // 404 — Não encontrado
    '404' => [
        'title' => 'Página não encontrada',
        'description' => 'A página que você procura não existe, foi movida ou o endereço está incorreto.',
    ],

    // 419 — Sessão expirada (CSRF)
    '419' => [
        'title' => 'Sessão expirada',
        'description' => 'Sua sessão expirou por inatividade. Atualize a página e tente novamente.',
    ],

    // 429 — Muitas requisições
    '429' => [
        'title' => 'Muitas requisições',
        'description' => 'Você fez muitas solicitações em pouco tempo. Aguarde alguns instantes e tente novamente.',
    ],

    // 500 — Erro interno
    '500' => [
        'title' => 'Algo deu errado',
        'description' => 'Ocorreu um erro inesperado no servidor. Nossa equipe foi notificada. Tente novamente em alguns instantes.',
    ],

    // 503 — Em manutenção
    '503' => [
        'title' => 'Em manutenção',
        'description' => 'O sistema está temporariamente indisponível para manutenção. Voltamos em breve.',
    ],

    // Fallback para qualquer outro status não mapeado
    'generic' => [
        'title' => 'Ops! Algo não saiu como esperado',
        'description' => 'Encontramos um problema ao processar sua solicitação. Tente novamente ou volte ao início.',
    ],
];
