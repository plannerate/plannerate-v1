<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Callcocam\LaravelRaptor\Models\Tenant;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        Tenant::query()->where('domain', 'plannerate.com.br')->update([
            'domain' => 'proplanner.plannerate.test' 
        ]);

        Log::info('Tenant updated', [
            'domain' => Tenant::query()->where('domain', 'proplanner.plannerate.test')->first()->toArray()
        ]);

        if($tenant = Tenant::query()->where('domain', 'proplanner.plannerate.test')->first()){
             Address::query()->update([
                'tenant_id' => $tenant->id
             ]);

             User::query()->update([
                'tenant_id' => $tenant->id
             ]);
        }
        
    }
}
