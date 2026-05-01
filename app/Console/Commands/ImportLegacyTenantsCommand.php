<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\multiselect;

class ImportLegacyTenantsCommand extends Command
{
    protected $signature = 'import:legacy-tenants
        {--dry-run : Mostra o que seria importado sem realmente importar}
        {--all : Importa todos os clientes sem confirmação}
        {--skip-users : Não importa usuários}
        {--fresh-users : Recria usuários mesmo que já existam}
        {--skip-rbac : Não executa o LandlordRbacSeeder ao final}';

    protected $description = 'Importa clientes da base legada como tenants e seus usuários';

    private Connection $legacy;

    /** @var array<int|string, Tenant> Maps legacy client.id → new Tenant */
    private array $tenantMap = [];

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $clients = $this->selectClients();

        if (empty($clients)) {
            $this->warn('Nenhum cliente selecionado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('🏢 %d cliente(s) selecionado(s).', count($clients)));
        $this->newLine();

        $results = [];

        foreach ($clients as $client) {
            $stats = $this->importClient($client);
            $results[] = $stats;
        }

        $this->newLine();
        $this->table(
            ['Cliente', 'Tenant', 'DB', 'Usuários'],
            array_map(fn ($r) => [
                $r['client'],
                $r['tenant'],
                $r['database'],
                $r['users'] > 0 ? "<fg=green>{$r['users']}</>" : ($r['skipped_users'] > 0 ? "<fg=yellow>{$r['skipped_users']} ignorados</>" : '0'),
            ], $results)
        );

        if (! $this->option('skip-users')) {
            $this->newLine();
            $this->importGlobalUsers();
        }

        if (! $this->option('dry-run') && ! $this->option('skip-rbac')) {
            $this->newLine();
            $this->info('🔑 Executando LandlordRbacSeeder...');
            Artisan::call('db:seed', ['--class' => 'LandlordRbacSeeder', '--force' => true, '--no-interaction' => true]);
            $this->info('✅ RBAC atualizado.');
        }

        $this->newLine();
        $this->info('✅ Concluído!');

        return self::SUCCESS;
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('✅ Conectado à base de origem (mysql_legacy)');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Falha na conexão com mysql_legacy: '.$e->getMessage());

            return false;
        }
    }

    /** @return list<object> */
    private function selectClients(): array
    {
        $query = $this->legacy->table('clients')->where('status', 'published');
        $all = $query->orderBy('name')->get();

        if ($all->isEmpty()) {
            $this->warn('Nenhum cliente publicado encontrado na base legada.');

            return [];
        }

        if ($this->option('all')) {
            return $all->all();
        }

        $options = $all->pluck('name', 'id')->toArray();

        $selected = multiselect(
            label: 'Selecione os clientes para importar como tenants',
            options: $options,
            hint: 'Use espaço para selecionar, enter para confirmar',
        );

        return $all->whereIn('id', $selected)->values()->all();
    }

    /** @return array{client: string, tenant: string, database: string, users: int, skipped_users: int} */
    private function importClient(object $client): array
    {
        $slug = str($client->slug ?? $client->name)->slug('_')->replace('supermercado_', '')->replace('_supermercados', '')->replace('_ltda', '')->replace(' LTDA', '');
        $database = 'tenant_'.$slug;
        $landlordDomain = config('app.landlord_domain', env('LANDLORD_DOMAIN', 'plannerate-v1.test'));
        $host = "{$slug}.{$landlordDomain}";

        $this->line("  <fg=cyan>↓  {$client->name}</> → <fg=blue>{$database}</>");

        if ($this->option('dry-run')) {
            $this->line("     <fg=gray>[dry-run] Criaria tenant '{$slug}' com host '{$host}'</>");

            return ['client' => $client->name, 'tenant' => $slug, 'database' => $database, 'users' => 0, 'skipped_users' => 0];
        }

        // Validate database name safety
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            $this->warn("  ⚠️  Database inválido ignorado: {$database}");

            return ['client' => $client->name, 'tenant' => $slug, 'database' => $database, 'users' => 0, 'skipped_users' => 0];
        }

        $this->createTenantDatabase($database);

        $tenant = Tenant::on('landlord')->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $client->name,
                'database' => $database,
                'status' => 'active',
            ]
        );

        $tenant->domains()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'host' => $host,
                'type' => 'subdomain',
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        $this->tenantMap[$client->id] = $tenant;

        $usersImported = 0;
        $usersSkipped = 0;

        $tenant->execute(function () use ($client, &$usersImported, &$usersSkipped): void {
            $this->runMigrationsIfNeeded();

            if (! $this->option('skip-users')) {
                [$usersImported, $usersSkipped] = $this->importTenantSpecificUsers($client->id);
            }
        });

        $this->line("     <fg=green>✓  {$tenant->name}</> ({$database}) — {$usersImported} usuário(s) importado(s)");

        return [
            'client' => $client->name,
            'tenant' => $slug,
            'database' => $database,
            'users' => $usersImported,
            'skipped_users' => $usersSkipped,
        ];
    }

    private function createTenantDatabase(string $database): void
    {
        $landlord = DB::connection('landlord');

        if (! in_array($landlord->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $landlord->statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));
    }

    private function runMigrationsIfNeeded(): void
    {
        $tenantConnection = (string) (config('multitenancy.tenant_database_connection_name') ?? config('database.default'));

        if ($tenantConnection === '') {
            throw new InvalidArgumentException('Tenant connection name is not configured.');
        }

        if (Schema::connection($tenantConnection)->hasTable('users')) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => $tenantConnection,
            '--path' => 'database/migrations',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->line('     <fg=gray>  migrations executadas</>');
    }

    /**
     * Import users that belong specifically to this client (client_id = client.id).
     *
     * @return array{int, int} [imported, skipped]
     */
    private function importTenantSpecificUsers(int|string $clientId): array
    {
        $legacyUsers = $this->legacy->table('users')
            ->where('client_id', $clientId)
            ->get();

        return $this->upsertUsers($legacyUsers->all());
    }

    /**
     * Import users with client_id IS NULL into ALL tenant databases.
     */
    private function importGlobalUsers(): void
    {
        if (empty($this->tenantMap)) {
            return;
        }

        $globalUsers = $this->legacy->table('users')
            ->whereNull('client_id')
            ->get();

        if ($globalUsers->isEmpty()) {
            return;
        }

        $this->info(sprintf('👥 Importando %d usuário(s) globais para todos os tenants...', $globalUsers->count()));

        foreach ($this->tenantMap as $tenant) {
            $tenant->execute(function () use ($tenant, $globalUsers): void {
                [$imported, $skipped] = $this->upsertUsers($globalUsers->all());
                $this->line("  <fg=cyan>{$tenant->name}</>: {$imported} importado(s), {$skipped} ignorado(s)");
            });
        }
    }

    /**
     * Upsert a list of legacy user objects into the currently active tenant DB.
     *
     * @param  list<object>  $legacyUsers
     * @return array{int, int} [imported, skipped]
     */
    private function upsertUsers(array $legacyUsers): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($legacyUsers as $legacyUser) {
            $email = strtolower(trim((string) ($legacyUser->email ?? '')));

            if ($email === '') {
                $skipped++;

                continue;
            }

            $existing = User::where('email', $email)->first();

            if ($existing && ! $this->option('fresh-users')) {
                $skipped++;

                continue;
            }

            $user = $existing ?? new User;

            if (! $user->exists) {
                $user->id = (string) Str::ulid();
            }

            $user->fill([
                'name' => (string) ($legacyUser->name ?? $email),
                'email' => $email,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            // Preserve existing password; set a random one for new users
            if (! $user->exists || $this->option('fresh-users')) {
                $rawPassword = property_exists($legacyUser, 'password') && $legacyUser->password
                    ? $legacyUser->password
                    : null;

                // If it looks like a bcrypt/argon hash, assign directly; otherwise hash it
                if ($rawPassword && str_starts_with($rawPassword, '$')) {
                    $user->forceFill(['password' => $rawPassword]);
                } else {
                    $user->password = $rawPassword ?? Str::random(32);
                }
            }

            $user->save();
            $imported++;
        }

        return [$imported, $skipped];
    }
}
