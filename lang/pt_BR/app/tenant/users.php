<?php

return [
    'navigation' => 'Usuarios',
    'title' => 'Usuarios',
    'description' => 'Gerencie os usuarios.',
    'actions' => [
        'new' => 'Novo usuario',
        'edit' => 'Editar usuario',
    ],
    'fields' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'password' => 'Senha',
        'password_confirmation' => 'Confirmacao de senha',
        'password_hint' => 'Preencha apenas se quiser alterar a senha.',
        'roles' => 'Perfis',
        'is_active' => 'Ativo',
        'is_active_hint' => 'Usuarios inativos nao conseguem fazer login.',
    ],
    'messages' => [
        'created' => 'Usuario criado com sucesso.',
        'updated' => 'Usuario atualizado com sucesso.',
        'deleted' => 'Usuario removido com sucesso.',
    ],
    'limit' => [
        'title' => 'Limite de usuarios',
    ],
];
