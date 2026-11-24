<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
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

    public function test_admin_cria_empresa_com_codigo_em_maiusculas(): void
    {
        $this->actingAs($this->admin)->post('/empresas', ['name' => 'Empresa Exemplo Norte', 'code' => 'exn', 'location' => 'Sede Exemplo'])
            ->assertRedirect('/empresas');

        $company = Company::where('code', 'EXN')->firstOrFail();
        $this->assertSame('EXN-00001', $company->nextAssetTag());
        $this->assertDatabaseHas('locations', ['name' => 'Sede Exemplo', 'company_id' => $company->id]);
    }

    public function test_codigo_repetido_e_recusado(): void
    {
        $this->actingAs($this->admin)->post('/empresas', ['name' => 'Outra', 'code' => 'CNT'])->assertSessionHasErrors('code');
    }

    public function test_nao_apaga_empresa_com_equipamento(): void
    {
        $company = Company::where('code', 'CNT')->firstOrFail();
        $this->assertTrue(Asset::withoutGlobalScopes()->where('company_id', $company->id)->exists());

        $this->actingAs($this->admin)->from('/empresas')->delete("/empresas/{$company->id}")->assertSessionHasErrors('company');
        $this->assertNotNull($company->fresh());
    }

    public function test_empresa_ativa_fica_na_sessao(): void
    {
        $company = Company::where('code', 'SSL')->firstOrFail();

        $this->actingAs($this->admin)->from('/')->post('/empresa-ativa', ['company_id' => $company->id])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Serviços Sul');
    }
}
