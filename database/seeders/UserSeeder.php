<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRoleEnumerate;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $default_foto_url = 'uploads/default.png';

        $idRolConstruccApp = Role::where('nombre', UserRoleEnumerate::CONSTUCC_APP->value)->first()?->id;
        // 
        $idRolAdmin = Role::where('nombre', UserRoleEnumerate::ADMINISTRADOR->value)->first()?->id;
        $idRolSuperAdmin = Role::where('nombre', UserRoleEnumerate::ADMINISTRADOR->value)->first()?->id;
        $idRolCliente = Role::where('nombre', UserRoleEnumerate::CLIENTE->value)->first()?->id;

        if (!$idRolAdmin || !$idRolSuperAdmin || !$idRolCliente) {
            $this->command->error('Uno o más roles no fueron encontrados. Seeder abortado.');
            return;
        }

        $ususarioConstrucc = [
            'email' => 'constucc@constucc.com.mx',
            'name' => config('app.name'),
            'role_id' => $idRolConstruccApp,
        ];

        // 🛡️ Siempre se crean (producción y debug)
        $adminUsers = [
            $ususarioConstrucc,
            [
                'email' => 'juliocsv@sjs.com.mx',
                'name' => 'Superadmin (JCSV)',
                'role_id' => $idRolSuperAdmin,
            ],
            [
                'email' => 'dir.tecnico@sjs.com.mx',
                'name' => 'Admin (OSACO)',
                'role_id' => $idRolAdmin,
            ],
            [
                'email' => 'jssr@admin.admin',
                'name' => 'Desarrollador (JSSR)',
                'role_id' => $idRolAdmin,
            ],
            [
                'email' => 'sistemas_sjs@hotmail.com',
                'name' => 'Auxiliar de desarrollo (GMB)',
                'role_id' => $idRolAdmin,
            ]
        ];

        foreach ($adminUsers as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'foto_perfil_url' => $default_foto_url,
                    'password' => Hash::make('123456'),
                    'role_id' => $user['role_id'],
                    'email_verified_at' => now(),
                ]
            );
        }

        // 🧪 Solo en entorno de pruebas o desarrollo
        if (config('app.debug')) {
            // Usuario de prueba
            User::firstOrCreate(
                ['email' => 'user@user.com'],
                [
                    'name' => 'Usuario de prueba',
                    'foto_perfil_url' => $default_foto_url,
                    'password' => Hash::make('123456'),
                    'role_id' => $idRolCliente,
                    'email_verified_at' => now(),
                ]
            );

            // Clientes de prueba
            $clientes = [
                [
                    'nombre' => 'Juan Carlos Pérez',
                    'email' => 'juan.perez@construccion.com',
                ],
                [
                    'nombre' => 'María Elena González',
                    'email' => 'maria.gonzalez@gmail.com',
                ],
                [
                    'nombre' => 'Roberto Silva Castro',
                    'email' => 'roberto.silva@ingenieria.mx',
                ],
                [
                    'nombre' => 'Ana Patricia Morales',
                    'email' => 'ana.morales@hotmail.com',
                ],
                [
                    'nombre' => 'Fernando Ramírez López',
                    'email' => 'fernando.ramirez@yahoo.com',
                ],
            ];

            foreach ($clientes as $cliente) {
                User::firstOrCreate(
                    ['email' => $cliente['email']],
                    [
                        'name' => $cliente['nombre'],
                        'password' => Hash::make('password123'),
                        'role_id' => $idRolCliente,
                        'email_verified_at' => now(),
                    ]
                );
            }

            $this->command->info('Usuarios de prueba y clientes insertados en modo debug.');
        } else {
            $this->command->info('Producción: solo se insertaron usuarios administrativos.');
        }
    }
}
