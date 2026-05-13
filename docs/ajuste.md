## Análise do Erro

O erro é bem direto: **timeout de conexão HTTP** ao tentar chamar uma API externa.

```
cURL error 28: Operation timed out after 30003 milliseconds with 0 bytes received
URL: https://s1mobilealberti.sysmo.com.br:8443/sysmo-integrador-api/api/...
```

O job `FetchIntegrationPageJob` está tentando chamar a API do Sysmo Integrador e não consegue nem estabelecer conexão (0 bytes recebidos), ou seja, o servidor remoto não está respondendo.

---

## Causas Prováveis

**1. API externa fora do ar ou instável** — o servidor `s1mobilealberti.sysmo.com.br:8443` está inacessível no momento.

**2. Problema de rede/firewall** — o servidor onde seu Laravel roda não consegue alcançar aquele host/porta (porta 8443 pode estar bloqueada).

**3. Timeout muito curto** — 30s pode ser pouco dependendo da carga da API do cliente.

---

## O que verificar agora

Dentro do container/servidor, testa a conectividade:

```bash
# Testa se a porta está acessível
curl -v --max-time 10 https://s1mobilealberti.sysmo.com.br:8443/sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos

# Ou só o host/porta
nc -zv s1mobilealberti.sysmo.com.br 8443
```

Se der timeout aqui também, é problema de rede ou o servidor do cliente está fora.

---

## Melhorias no Código

Olhando o stack trace, o `IntegrationHttpClient` não tem retry/tratamento adequado. Algumas sugestões:

**`IntegrationHttpClient.php` — adicionar timeout configurável e retry:**

```php
public function call(string $method, string $url, array $data = []): mixed
{
    return Http::timeout(config('integrations.timeout', 60))
        ->connectTimeout(10)
        ->retry(3, 5000, function ($exception) {
            // Só retenta em timeout/conexão, não em erros de negócio
            return $exception instanceof \Illuminate\Http\Client\ConnectionException;
        })
        ->{$method}($url, $data);
}
```

**`FetchIntegrationPageJob.php` — tratar a exceção graciosamente:**

```php
public function handle(): void
{
    try {
        $response = $this->client->call('post', $this->url, $this->payload);
        // processa...
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        // Loga e reagenda ao invés de explodir
        Log::warning("Integração timeout [{$this->url}]: {$e->getMessage()}");
        
        // Libera para retry automático do Laravel Queue
        $this->release(now()->addMinutes(5));
    }
}
```

**No Job, configura tentativas e backoff:**

```php
class FetchIntegrationPageJob implements ShouldQueue
{
    public int $tries = 5;
    public int $maxExceptions = 3;

    public function backoff(): array
    {
        return [30, 60, 120, 300]; // segundos entre tentativas
    }
}
```

---

## Resumo

O erro em si é externo (API do cliente fora do ar), mas o código precisa ser mais resiliente. O mais importante agora é confirmar se o host está acessível a partir do teu servidor, e depois blindar o job com retry + backoff adequados.