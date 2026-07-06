<?php

return [
    'errors' => [
        'invalid_code' => 'Este link de definição de senha é inválido.',
        'expired_code' => 'Este link de definição de senha expirou. Solicite um novo link ao administrador.',
        'already_used' => 'Este link de definição de senha já foi utilizado.',
        'tenant_unavailable' => 'Não é possível definir a senha: este cliente (tenant) não está ativo.',
        'target_unavailable' => 'Não é possível definir a senha: este usuário não está ativo.',
    ],
    'mail' => [
        'subject' => 'Defina sua senha de acesso',
        'resend_subject' => 'Reenvio: defina sua senha de acesso',
        'greeting' => 'Olá, :name!',
        'intro' => 'Uma conta foi criada para você. Para acessar o sistema, defina sua senha clicando no botão abaixo.',
        'resend_intro' => 'Reenviamos o link para você definir sua senha de acesso.',
        'action' => 'Definir minha senha',
        'expiry' => 'Este link expira em :days dias. Se você não reconhece esta solicitação, ignore este e-mail.',
    ],
    'page' => [
        'title' => 'Defina sua senha',
        'description' => 'Escolha uma senha para acessar sua conta.',
        'submit' => 'Definir senha e entrar',
        'success' => 'Senha definida com sucesso. Bem-vindo!',
    ],
    'resend' => [
        'trigger' => 'Reenviar link de senha',
        'title' => 'Reenviar link de definição de senha?',
        'description' => 'Um novo link será enviado para :name. O link anterior deixará de funcionar.',
        'confirm' => 'Reenviar link',
    ],
];
