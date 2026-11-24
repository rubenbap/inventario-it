<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.admin.password' => 'password-de-teste']);
        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    public function test_gera_token_so_de_leitura_e_de_escrita(): void
    {
        $this->actingAs($this->admin)->post('/tokens', ['name' => 'Power BI', 'access' => 'read', 'expires' => '365'])->assertRedirect('/tokens');
        $read = session('new_token');
        $this->post('/tokens', ['name' => 'Script', 'access' => 'write', 'expires' => 'never'])->assertRedirect('/tokens');
        $write = session('new_token');
        auth()->logout();

        $this->assertSame(['read'], $this->admin->tokens()->where('name', 'Power BI')->first()->abilities);
        $this->assertNotNull($this->admin->tokens()->where('name', 'Power BI')->first()->expires_at);
        $this->assertNull($this->admin->tokens()->where('name', 'Script')->first()->expires_at);

        $line = Asset::where('name', '912 345 601')->firstOrFail();

        $this->getJson('/api/v1/hardware', $this->bearer($read))->assertOk();
        $this->getJson('/api/v1/reports/summary', $this->bearer($read))->assertOk();
        $this->getJson("/api/v1/hardware/{$line->id}", $this->bearer($read))->assertJsonPath('custom_fields.PIN.value', '********');
        $this->getJson("/api/v1/hardware/{$line->id}/pin", $this->bearer($read))->assertForbidden();
        $this->postJson('/api/v1/hardware', [], $this->bearer($read))->assertForbidden();
        $this->deleteJson("/api/v1/hardware/{$line->id}", [], $this->bearer($read))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/hardware/{$line->id}/pin", $this->bearer($write))->assertOk()->assertJsonPath('pin', '4821');
    }

    public function test_token_expirado_deixa_de_entrar(): void
    {
        $token = $this->admin->createToken('Velho', ['read'], now()->subDay())->plainTextToken;

        $this->getJson('/api/v1/hardware', $this->bearer($token))->assertUnauthorized();
    }

    public function test_so_consulta_nao_gera_tokens(): void
    {
        $consulta = User::where('role', User::ROLE_LEITURA)->firstOrFail();
        $this->actingAs($consulta)->post('/tokens', ['name' => 'X', 'access' => 'read', 'expires' => '30'])->assertForbidden();
    }
}
