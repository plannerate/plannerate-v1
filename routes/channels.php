<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
     
    
    return $authorized;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
     
    
    return $authorized;
});

Broadcast::channel('sync.user.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
     
    
    return $authorized;
});

Broadcast::channel('sync.client.{id}', function ($user, $id) {
    // Verifica se o usuário tem acesso ao cliente através do contexto atual
    // O client_id pode vir do contexto do domínio/tenant atual
    $currentClientId = config('app.current_domainable_id');
    $authorized = $currentClientId && (string) $currentClientId === (string) $id;
     
    
    return $authorized;
});

// Canal privado para notificações de importação do usuário
Broadcast::channel('import.user.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
     
    
    return $authorized;
});

// Canal privado para notificações de importação do tenant
Broadcast::channel('import.tenant.{id}', function ($user, $id) {
    // Verifica se o usuário pertence ao tenant
    $authorized = (string) $user->tenant_id === (string) $id; 
    
    return $authorized;
});
