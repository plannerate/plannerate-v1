<?php

use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Services\Integrations\IntegrationHttpClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/*
 * `auth.token_header` da ponta a ponta: o campo do formulário do tenant vira
 * config, e a config manda o token no header certo.
 *
 * Sem ele, o motor só sabe `Authorization: Bearer` — e a RP Info ignora esse
 * header, exigindo `token: <jwt>`.
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

/**
 * Monta a config a partir dos campos do formulário, sem passar pela rota — aqui
 * só interessa o mapeamento dos campos de auth.
 *
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function tenantIntegrationConfig(array $input): array
{
    $data = [
        'integration_type' => 'blueprint-ulid',
        'api_url' => 'https://erp.test',
        ...$input,
    ];

    $request = UpdateTenantIntegrationRequest::create('/', 'PUT', $data);
    $request->setValidator(Validator::make($data, array_fill_keys(array_keys($data), 'nullable')));

    return $request->integrationPayload()['config'];
}

test('o campo do formulário vira auth.token_header na config', function (): void {
    $config = tenantIntegrationConfig([
        'auth_type' => 'bearer',
        'auth_bearer_mode' => 'fetch',
        'auth_token_header' => 'token',
        'auth_token_username' => '100077',
        'auth_token_password' => 'segredo',
        'auth_token_path' => '/v1.1/auth',
        'auth_token_response_path' => 'response.token',
    ]);

    expect(data_get($config, 'auth.token_header'))->toBe('token')
        ->and(data_get($config, 'auth.token_mode'))->toBe('fetch')
        ->and(data_get($config, 'auth.token_request.response_path'))->toBe('response.token');
});

test('sem o campo preenchido a config fica com token_header vazio — mantém Authorization: Bearer', function (): void {
    $config = tenantIntegrationConfig([
        'auth_type' => 'bearer',
        'auth_bearer_mode' => 'manual',
        'auth_token' => 'jwt-estatico',
    ]);

    expect(data_get($config, 'auth.token_header'))->toBe('');
});

test('a validação recusa nome de header inválido', function (string $value, bool $valid): void {
    $rule = ['auth_token_header' => (new UpdateTenantIntegrationRequest)->rules()['auth_token_header']];

    expect(Validator::make(['auth_token_header' => $value], $rule)->passes())->toBe($valid);
})->with([
    'simples' => ['token', true],
    'com hífen' => ['X-Auth-Token', true],
    'com espaço' => ['x token', false],
    'com dois-pontos' => ['token:', false],
    'com quebra de linha' => ["token\r\nX-Injected", false],
]);

test('com token_header o cliente HTTP manda o token nesse header e não no Authorization', function (): void {
    Http::fake(['erp.test/*' => Http::response(['ok' => true])]);

    (new IntegrationHttpClient([
        'connection' => ['base_url' => 'https://erp.test'],
        'auth' => [
            'type' => 'bearer',
            'token_header' => 'token',
            'credentials' => ['token' => 'jwt-abc'],
        ],
    ]))->call('get', 'https://erp.test/produtos', []);

    Http::assertSent(fn ($request): bool => $request->header('token') === ['jwt-abc']
        && $request->header('Authorization') === []);
});

test('sem token_header o cliente HTTP mantém o Authorization: Bearer', function (): void {
    Http::fake(['erp.test/*' => Http::response(['ok' => true])]);

    (new IntegrationHttpClient([
        'connection' => ['base_url' => 'https://erp.test'],
        'auth' => [
            'type' => 'bearer',
            'credentials' => ['token' => 'jwt-abc'],
        ],
    ]))->call('get', 'https://erp.test/produtos', []);

    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer jwt-abc']
        && $request->header('token') === []);
});
