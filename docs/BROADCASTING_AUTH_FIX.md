# Correção de Autenticação de Canais de Broadcasting

## Problema

Ao tentar conectar a canais privados de broadcasting (WebSocket), ocorria erro `403 Forbidden` na autenticação. O erro indicava que o Laravel não conseguia autorizar o acesso aos canais porque as rotas de autorização não estavam definidas.

### Sintomas

- Erro `403 (Forbidden)` ao tentar autenticar em canais privados
- Logs mostrando: `verifyUserCanAccessChannel(): Channel [private-sync.user.xxx] not found`
- Conexão WebSocket estabelecida, mas falha na autenticação de canais privados

## Causa

O problema ocorria porque as rotas de autorização para os canais privados não estavam definidas no arquivo `routes/channels.php`. Quando o Laravel tenta autenticar um canal privado, ele precisa verificar se o usuário tem permissão para acessar aquele canal específico através das rotas definidas em `routes/channels.php`.

### Canais Afetados

Os seguintes canais privados estavam sem rotas de autorização:

1. `user.{id}` - Canal privado para notificações do usuário
2. `sync.user.{id}` - Canal privado para notificações de sincronização do usuário
3. `sync.client.{id}` - Canal privado para notificações de sincronização do cliente

## Solução

Adicionar as rotas de autorização para todos os canais privados utilizados no arquivo `routes/channels.php`.

### Implementação

```php
<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// Canal padrão do Laravel para modelos User
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
    Log::info('Broadcast channel authorization', [
        'channel' => 'App.Models.User.{id}',
        'user_id' => $user->id,
        'requested_id' => $id,
        'authorized' => $authorized,
    ]);
    return $authorized;
});

// Canal privado para notificações do usuário
Broadcast::channel('user.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
    Log::info('Broadcast channel authorization', [
        'channel' => 'user.{id}',
        'user_id' => $user->id,
        'requested_id' => $id,
        'authorized' => $authorized,
    ]);
    return $authorized;
});

// Canal privado para notificações de sincronização do usuário
Broadcast::channel('sync.user.{id}', function ($user, $id) {
    // Compara ULIDs como strings (não converter para int)
    $authorized = (string) $user->id === (string) $id;
    Log::info('Broadcast channel authorization', [
        'channel' => 'sync.user.{id}',
        'user_id' => $user->id,
        'requested_id' => $id,
        'authorized' => $authorized,
    ]);
    return $authorized;
});

// Canal privado para notificações de sincronização do cliente
Broadcast::channel('sync.client.{id}', function ($user, $id) {
    // Verifica se o usuário tem acesso ao cliente através do contexto atual
    // O client_id pode vir do contexto do domínio/tenant atual
    $currentClientId = config('app.current_domainable_id');
    $authorized = $currentClientId && (string) $currentClientId === (string) $id;
    Log::info('Broadcast channel authorization', [
        'channel' => 'sync.client.{id}',
        'user_id' => $user->id,
        'current_client_id' => $currentClientId,
        'requested_client_id' => $id,
        'authorized' => $authorized,
    ]);
    return $authorized;
});
```

### Pontos Importantes

1. **Comparação de ULIDs**: Como o projeto usa ULIDs (não IDs numéricos), é importante comparar como strings usando `(string) $user->id === (string) $id`. Converter para `int` causaria problemas.

2. **Canal `sync.client.{id}`**: Este canal verifica o acesso através do contexto atual (`config('app.current_domainable_id')`), não diretamente através do modelo User, pois a relação User-Client é indireta.

3. **Logs de Debug**: Os logs foram mantidos para facilitar troubleshooting futuro, mas podem ser removidos em produção se necessário.

## Verificação

Após adicionar as rotas, verifique:

1. **Logs do Laravel**: Deve aparecer `Broadcasting auth success` ao conectar aos canais
2. **Console do Navegador**: Não deve mais aparecer erros `403 Forbidden`
3. **Status da Conexão**: O status deve mudar para `connected` após autenticação bem-sucedida

## Exemplo de Uso

### Frontend (Vue)

```vue
<script setup>
import { useEcho } from '@laravel/echo-vue'

const user = computed(() => page.props.auth.user)
const channelName = `user.${user.value.id}`

const { listen } = useEcho(
  channelName,
  '.test.notification', // Note o ponto inicial quando usa broadcastAs()
  (data) => {
    console.log('Notificação recebida:', data)
  },
  [user.value.id],
  'private' // Especifica que é um canal privado
)

// Inicia a escuta
listen()
</script>
```

### Backend (Laravel)

```php
// Evento
class TestNotificationSent implements ShouldBroadcastNow
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'test.notification';
    }
}

// Disparar evento
event(new TestNotificationSent($message, $userId));
```

## Notas Adicionais

### Nome do Evento com `broadcastAs()`

Quando você usa `broadcastAs()` no Laravel, o evento é broadcastado com um **ponto no início**. Então:

- `broadcastAs()` retorna: `'test.notification'`
- O Laravel broadcasta como: `.test.notification`
- O listener precisa escutar: `'.test.notification'` (com ponto inicial)

### Eventos Imediatos

Para garantir que os eventos sejam broadcastados imediatamente (sem fila), use `ShouldBroadcastNow` em vez de `ShouldBroadcast`:

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TestNotificationSent implements ShouldBroadcastNow
{
    // ...
}
```

## Referências

- [Laravel Broadcasting - Authorizing Channels](https://laravel.com/docs/broadcasting#authorizing-channels)
- [Laravel Broadcasting - Private Channels](https://laravel.com/docs/broadcasting#private-channels)
- [@laravel/echo-vue Documentation](./doc-echo-vue.md)

