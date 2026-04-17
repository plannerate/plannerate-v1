<?php

namespace App\Console\Commands;

use App\Models\User;
use Callcocam\LaravelRaptor\Enums\RoleStatus;
use Callcocam\LaravelRaptor\Enums\UserStatus;
use Callcocam\LaravelRaptor\Support\Shinobi\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateLandlordUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landlord:create-user
                            {--email= : Email do usuário}
                            {--name= : Nome do usuário}
                            {--password= : Senha do usuário}
                            {--role= : Slug da role a ser atribuída}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria um usuário landlord com permissão de Super Admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('╔════════════════════════════════════════════════════════════════╗');
        $this->line('║          👤 Criar Usuário Landlord - Super Admin                ║');
        $this->line('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Obtém o host para sugerir email
        // Em CLI, tenta obter do APP_URL ou usa um padrão
        $host = parse_url(config('app.url', 'http://plannerate.test'), PHP_URL_HOST) ?: 'plannerate.test';
        $suggestedEmail = $this->option('email') ?: "landlord@{$host}";

        // Verifica se o usuário já existe
        $existingUser = User::where('email', $suggestedEmail)->first();
        if ($existingUser) {
            $this->warn("⚠️  Usuário com email '{$suggestedEmail}' já existe!");
            
            if (!$this->confirm('Deseja continuar e atualizar este usuário?', false)) {
                $this->info('Operação cancelada.');
                return self::FAILURE;
            }

            $user = $existingUser;
        } else {
            // Coleta informações do usuário
            $name = $this->option('name') ?: $this->ask('Qual o nome do usuário?', 'Landlord Admin');
            $email = $this->option('email') ?: $this->ask('Qual o email do usuário?', $suggestedEmail);
            $password = $this->option('password') ?: $this->secret('Qual a senha do usuário?') ?: 'password';

            // Valida email único
            if (User::where('email', $email)->exists()) {
                $this->error("Usuário com email '{$email}' já existe!");
                return self::FAILURE;
            }

            // Cria o usuário landlord (tenant_id = null)
            $user = User::create([
                'tenant_id' => null, // Landlord não tem tenant
                'name' => $name,
                'email' => $email,
                'slug' => Str::slug($name),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => UserStatus::Published->value,
            ]);

            $this->info("✓ Usuário '{$name}' criado com sucesso!");
        }

        // Gerencia roles
        $role = $this->manageRoles($user);

        if ($role) {
            // Associa a role ao usuário
            $user->roles()->sync([$role->id]);
            $this->info("✓ Usuário associado à role '{$role->name}'!");
        }

        $this->newLine();
        $this->info('✅ Usuário landlord configurado com sucesso!');
        $this->newLine();
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Nome', $user->name],
                ['Email', $user->email],
                ['Role', $role?->name ?? 'Nenhuma'],
                ['Tenant ID', $user->tenant_id ?? 'null (Landlord)'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Gerencia roles: lista existentes, permite selecionar ou criar nova
     */
    protected function manageRoles(User $user): ?Role
    {
        $roleClass = config('raptor.shinobi.models.role', Role::class);
        $roles = $roleClass::whereNull('tenant_id')->get(); // Roles do landlord

        // Se foi passada via opção, usa ela
        if ($this->option('role')) {
            $role = $roleClass::where('slug', $this->option('role'))->whereNull('tenant_id')->first();
            if ($role) {
                return $role;
            }
            $this->warn("Role '{$this->option('role')}' não encontrada para landlord.");
        }

        // Verifica se existe Super Admin
        $superAdmin = $roleClass::where('slug', 'super-admin')->whereNull('tenant_id')->first();

        if ($roles->isEmpty() && !$superAdmin) {
            $this->info('Nenhuma role encontrada para landlord.');
            
            if ($this->confirm('Deseja criar a role Super Admin?', true)) {
                return $this->createSuperAdminRole($roleClass);
            }

            if ($this->confirm('Deseja criar uma nova role?', false)) {
                return $this->createCustomRole($roleClass);
            }

            return null;
        }

        // Lista roles existentes
        if ($roles->isNotEmpty()) {
            $this->info('Roles existentes para landlord:');
            $this->table(
                ['ID', 'Nome', 'Slug', 'Descrição', 'Special'],
                $roles->map(fn($r) => [
                    $r->id,
                    $r->name,
                    $r->slug,
                    $r->description ?? '-',
                    $r->special ? 'Sim' : 'Não',
                ])
            );
            $this->newLine();
        }

        // Se não tem Super Admin, sugere criar
        if (!$superAdmin) {
            if ($this->confirm('Role Super Admin não encontrada. Deseja criá-la?', true)) {
                return $this->createSuperAdminRole($roleClass);
            }
        }

        // Opções para o usuário
        $choices = [];
        if ($superAdmin) {
            $choices['super-admin'] = "Super Admin (recomendado)";
        }
        
        foreach ($roles as $role) {
            if ($role->slug !== 'super-admin') {
                $choices[$role->slug] = $role->name;
            }
        }
        
        $choices['new'] = 'Criar nova role';
        $choices['skip'] = 'Pular (sem role)';

        $selected = $this->choice(
            'Qual role deseja atribuir ao usuário?',
            $choices,
            $superAdmin ? 'super-admin' : null
        );

        if ($selected === 'new') {
            return $this->createCustomRole($roleClass);
        }

        if ($selected === 'skip') {
            return null;
        }

        return $roleClass::where('slug', $selected)->whereNull('tenant_id')->first();
    }

    /**
     * Cria a role Super Admin
     */
    protected function createSuperAdminRole(string $roleClass): Role
    {
        $this->info('Criando role Super Admin...');

        $role = $roleClass::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Acesso total ao sistema',
            'special' => true,
            'status' => RoleStatus::Active->value,
            'tenant_id' => null, // Role do landlord
        ]);

        $this->info("✓ Role 'Super Admin' criada com sucesso!");
        
        return $role;
    }

    /**
     * Cria uma role customizada
     */
    protected function createCustomRole(string $roleClass): Role
    {
        $name = $this->ask('Qual o nome da role?', 'Admin');
        $slug = $this->ask('Qual o slug da role?', Str::slug($name));
        $description = $this->ask('Descrição da role?', "Role para {$name}");
        $special = $this->confirm('Esta role tem acesso total (all-access/special)?', false);

        if ($roleClass::where('slug', $slug)->whereNull('tenant_id')->exists()) {
            $this->error("Role com slug '{$slug}' já existe para landlord.");
            return $this->createCustomRole($roleClass);
        }

        $role = $roleClass::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'special' => $special,
            'status' => RoleStatus::Active->value,
            'tenant_id' => null, // Role do landlord
        ]);

        $this->info("✓ Role '{$name}' criada com sucesso!");
        
        return $role;
    }
}

