<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.admin.password' => 'password-de-teste']);
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        Notification::fake();
    }

    public function test_pede_link_define_nova_password_e_entra(): void
    {
        $token = null;
        $this->post('/password/esqueci', ['email' => strtoupper($this->admin->email)])->assertSessionHas('status');
        Notification::assertSentTo($this->admin, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/password/nova', [
            'token' => $token, 'email' => $this->admin->email,
            'password' => 'nova-password-10', 'password_confirmation' => 'nova-password-10',
        ])->assertRedirect('/login');

        $this->post('/login', ['login' => $this->admin->email, 'password' => 'nova-password-10'])->assertRedirect('/');
    }

    public function test_email_sem_login_recebe_a_mesma_resposta_e_nada_e_enviado(): void
    {
        $semLogin = User::create(['first_name' => 'Ana', 'email' => 'ana@example.com']);
        $this->post('/password/esqueci', ['email' => 'ana@example.com'])->assertSessionHas('status');
        $this->post('/password/esqueci', ['email' => 'ninguem@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();
        $this->assertFalse($semLogin->fresh()->can_login);
    }

    public function test_link_invalido_nao_muda_a_password(): void
    {
        $this->post('/password/nova', [
            'token' => 'inventado', 'email' => $this->admin->email,
            'password' => 'nova-password-10', 'password_confirmation' => 'nova-password-10',
        ])->assertSessionHasErrors('email');
        $this->post('/login', ['login' => $this->admin->email, 'password' => 'password-de-teste'])->assertRedirect('/');
    }

    public function test_admin_cria_login_sem_password_e_a_pessoa_recebe_convite(): void
    {
        $this->actingAs($this->admin)->post('/acessos', ['first_name' => 'Rita', 'email' => 'rita@example.com', 'role' => 'leitura', 'company_ids' => [\App\Models\Company::firstOrFail()->id]])->assertRedirect('/acessos');
        Notification::assertSentTo(User::where('email', 'rita@example.com')->firstOrFail(), ResetPassword::class);
    }
}
