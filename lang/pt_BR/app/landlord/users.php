<?php

return [
    'navigation' => 'Usuarios',
    'title' => 'Usuarios',
    'description' => 'Gerencie os usuarios globais do landlord e seus perfis de acesso.',
    'actions' => [
        'new' => 'Novo usuario',
        'edit' => 'Editar usuario',
    ],
    'fields' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'password' => 'Senha',
        'password_confirmation' => 'Confirmacao de senha',
        'password_hint' => 'Preencha apenas para alterar a senha.',
        'roles' => 'Perfis de acesso',
        'is_active' => 'Ativo',
    ],
    'messages' => [
        'created' => 'Usuario criado com sucesso.',
        'updated' => 'Usuario atualizado com sucesso.',
        'deleted' => 'Usuario removido com sucesso.',
    ],
];
