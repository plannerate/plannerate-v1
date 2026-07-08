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
        // Assunto do e-mail
        'subject' => 'Credenciais de acesso ao sistema',
        'resend_subject' => 'Reenvio — Credenciais de acesso ao sistema',

        // Cabeçalho (barra escura com o logo)
        'header_subtitle' => 'Credenciais de acesso ao sistema',

        // Corpo
        'greeting' => 'Prezado(a) Sr.(a). :name,',
        'intro' => 'Comunicamos que suas credenciais de acesso ao sistema foram geradas com sucesso.',
        'resend_intro' => 'Reenviamos as suas informações de acesso ao sistema conforme solicitado.',
        'instructions' => 'Para realizar o primeiro acesso, utilize as informações abaixo:',

        // Cartão de credenciais
        'label_system' => 'Link do sistema',
        'label_username' => 'Usuário',
        'label_password' => 'Senha provisória',
        'password_value' => 'Não enviada por e-mail. Defina sua senha no primeiro acesso.',

        // Botão de ação
        'action' => 'Acessar e definir senha',

        // Avisos de segurança
        'security_notice' => 'Por razões de segurança da informação, será obrigatória a alteração da senha no primeiro acesso. Recomendamos que a nova senha seja de uso pessoal, intransferível e não compartilhada com terceiros.',
        'expiry' => 'Este link de acesso é válido por :days dias a partir do envio deste e-mail.',
        'support' => 'Em caso de dúvidas ou necessidade de suporte técnico, favor entrar em contato com nosso atendimento por meio dos canais oficiais.',

        // Assinatura
        'salutation' => 'Atenciosamente,',
        'team' => 'Equipe Plannerate',

        // Rodapé (avisos legais)
        'footer' => [
            'confidentiality_title' => 'Aviso de Confidencialidade:',
            'confidentiality' => 'Esta mensagem e seus anexos são confidenciais e destinados exclusivamente ao(s) destinatário(s). Caso tenha recebido este e-mail por engano, solicitamos que nos comunique imediatamente e proceda com a exclusão permanente da mensagem. É proibida a utilização, divulgação, cópia ou distribuição sem autorização formal.',
            'security_title' => 'Segurança da Informação:',
            'security' => 'O uso indevido das credenciais é de responsabilidade do usuário titular. A empresa poderá registrar logs de acesso para fins de auditoria, conformidade e segurança, conforme legislação aplicável.',
        ],
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
