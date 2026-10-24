<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Cria o Tenant oficial NUME Creative e o usuário admin,
     * sem nenhum dado fictício de demonstração.
     * Gera uma senha aleatória e a imprime no console (não fica hardcoded no código).
     */
    public function run(): void
    {
        $numeTenant = Tenant::firstOrCreate(
            ['slug' => 'nume'],
            [
                'name' => 'NUME Creative',
                'description' => 'Produtora estratégica oficial da plataforma',
                'status' => 'active',
                'primary_contact_email' => 'admin@numecreative.com',
                'company_cnpj' => '00.000.000/0001-00',
                'subscription_plan' => 'enterprise',
                'subscribed_at' => now(),
                'subscription_expires_at' => now()->addYears(10),
            ]
        );

        $existingAdmin = User::where('email', 'admin@numecreative.com')->first();

        if ($existingAdmin) {
            $this->command->warn('Usuário admin@numecreative.com já existe. Nenhuma alteração foi feita. Use "php artisan tinker" se precisar resetar a senha manualmente.');

            return;
        }

        $password = Str::password(16);

        User::create([
            'tenant_id' => $numeTenant->id,
            'name' => 'Admin NUME',
            'email' => 'admin@numecreative.com',
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin criado: admin@numecreative.com');
        $this->command->info('Senha gerada (anote agora, não será exibida novamente): '.$password);
    }
}
