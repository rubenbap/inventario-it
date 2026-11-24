<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.admin.password' => 'password-de-teste']);
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        Company::create(['name' => 'Empresa A', 'code' => 'EMA']);
    }

    public function test_admin_cria_outro_admin_pela_pagina_de_acessos(): void
    {
        $this->actingAs($this->admin)->post('/acessos', [
            'first_name' => 'Carlos', 'email' => 'carlos@example.com', 'role' => 'admin',
            'password' => 'password-forte-1', 'password_confirmation' => 'password-forte-1',
        ])->assertRedirect('/acessos');

        $carlos = User::where('email', 'carlos@example.com')->firstOrFail();
        $this->assertTrue($carlos->isAdmin());

        $this->post('/logout');
        $this->post('/login', ['login' => 'carlos@example.com', 'password' => 'password-forte-1'])->assertRedirect('/');
    }

    public function test_so_existem_dois_niveis_de_acesso(): void
    {
        $this->actingAs($this->admin)->post('/acessos', [
            'first_name' => 'Joana', 'email' => 'joana@example.com', 'role' => 'tecnico',
            'password' => 'password-forte-1', 'password_confirmation' => 'password-forte-1',
        ])->assertSessionHasErrors('role');

        $this->post('/acessos', [
            'first_name' => 'Joana', 'email' => 'joana@example.com', 'role' => 'leitura',
            'password' => 'password-forte-1', 'password_confirmation' => 'password-forte-1',
        ])->assertRedirect('/acessos');
        $this->assertSame(Company::count(), count(User::where('email', 'joana@example.com')->first()->companyIds()));
    }

    public function test_nao_se_pode_retirar_o_ultimo_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/users/{$this->admin->id}", ['role' => 'leitura'])
            ->assertJsonPath('status', 'error');
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_so_consulta_nao_gere_acessos(): void
    {
        $consulta = User::create([
            'first_name' => 'T', 'email' => 't@example.com', 'password' => 'password-123456',
            'can_login' => true, 'role' => User::ROLE_LEITURA,
        ]);

        $this->actingAs($consulta)->get('/acessos')->assertForbidden();
    }
}
