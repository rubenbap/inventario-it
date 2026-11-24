<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HardwareApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.admin.password' => 'password-de-teste']);
        $this->seed(DemoSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_lista_no_formato_do_snipe_it(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/hardware?limit=2')
            ->assertOk()
            ->assertJsonStructure(['total', 'rows' => [['id', 'asset_tag', 'model' => ['id', 'name'], 'status_label' => ['status_meta'], 'company', 'custom_fields']]])
            ->assertJsonCount(2, 'rows');
    }

    public function test_cria_asset_com_tag_automatica_por_empresa(): void
    {
        Sanctum::actingAs($this->admin());
        $company = Company::where('code', 'CNT')->first();
        $before = $company->asset_tag_counter;

        $this->postJson('/api/v1/hardware', [
            'company_id' => $company->id,
            'model_id' => AssetModel::where('name', 'ThinkPad T14 Gen 4')->value('id'),
            'status_id' => StatusLabel::where('name', 'Pronto')->value('id'),
            'serial' => 'NOVO-SN-001',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('payload.asset_tag', 'CNT-'.str_pad((string) ($before + 1), 5, '0', STR_PAD_LEFT))
            ->assertJsonPath('payload.category.name', 'Portátil');
    }

    public function test_erro_de_validacao_vem_com_http_200_como_no_snipe_it(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/hardware', ['serial' => 'PF4X1A2B'])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('payload', null)
            ->assertJsonStructure(['messages' => ['model_id', 'status_id', 'serial']]);
    }

    public function test_checkout_para_localizacao_e_checkin(): void
    {
        Sanctum::actingAs($this->admin());
        $asset = Asset::ready()->first();
        $location = Location::first();

        $this->postJson("/api/v1/hardware/{$asset->id}/checkout", [
            'checkout_to_type' => 'location',
            'assigned_location' => $location->id,
        ])->assertJsonPath('status', 'success');

        $this->getJson("/api/v1/hardware/{$asset->id}")
            ->assertJsonPath('assigned_to.type', 'location')
            ->assertJsonPath('status_label.status_meta', 'deployed');

        $this->postJson("/api/v1/hardware/{$asset->id}/checkout", [
            'checkout_to_type' => 'location', 'assigned_location' => $location->id,
        ])->assertJsonPath('status', 'error');

        $this->postJson("/api/v1/hardware/{$asset->id}/checkin", ['note' => 'devolvido'])
            ->assertJsonPath('status', 'success');
        $this->assertNull($asset->fresh()->assigned_id);
    }

    public function test_so_consulta_ve_todas_as_empresas_sem_pin(): void
    {
        Sanctum::actingAs(User::where('role', User::ROLE_LEITURA)->firstOrFail());
        $hld = Company::where('code', 'HLD')->first();
        $this->assertGreaterThan(0, $this->getJson('/api/v1/hardware?company_id='.$hld->id)->json('total'));

        $line = Asset::where('name', '912 345 601')->firstOrFail();
        $this->getJson("/api/v1/hardware/{$line->id}")->assertJsonPath('custom_fields.PIN.value', '********');
        $this->getJson("/api/v1/hardware/{$line->id}/pin")->assertForbidden();
    }

    public function test_pin_puk_cifrados_escondidos_na_lista_e_visiveis_no_detalhe(): void
    {
        Sanctum::actingAs($this->admin());
        $line = Asset::where('name', '912 345 601')->firstOrFail();

        $this->assertStringNotContainsString('4821', json_encode($line->custom_values));

        $list = $this->getJson('/api/v1/hardware?search=912 345 601')->json('rows.0.custom_fields');
        $this->assertSame('********', $list['PIN']['value']);

        $this->getJson("/api/v1/hardware/{$line->id}")->assertJsonPath('custom_fields.PIN.value', '4821');
        $this->assertDatabaseHas('action_logs', ['action_type' => 'view encrypted', 'item_id' => $line->id]);
    }

    public function test_leitura_nao_pode_escrever(): void
    {
        $user = User::create([
            'first_name' => 'Só', 'last_name' => 'Leitura', 'email' => 'leitura@example.com',
            'password' => 'password-123456', 'can_login' => true, 'role' => User::ROLE_LEITURA,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/hardware', [])->assertForbidden()->assertJsonPath('status', 'error');
    }

    public function test_pin_puk_pela_api_fica_no_historico(): void
    {
        Sanctum::actingAs($this->admin());
        $line = Asset::where('name', '912 345 601')->firstOrFail();

        $this->getJson("/api/v1/hardware/{$line->id}/pin")->assertOk()->assertJsonPath('pin', '4821');
        $this->assertDatabaseHas('action_logs', ['item_id' => $line->id, 'action_type' => 'view encrypted', 'note' => 'PIN/PUK pedido pela API']);

        $laptop = Asset::whereNull('custom_values')->firstOrFail();
        $this->getJson("/api/v1/hardware/{$laptop->id}/pin")->assertNotFound();
    }

    public function test_resumo_por_empresa(): void
    {
        Sanctum::actingAs($this->admin());
        $json = $this->getJson('/api/v1/reports/summary')->assertOk()->json();

        $this->assertSame(Company::count(), count($json['companies']));
        $this->assertSame(collect($json['companies'])->sum('lines'), $json['totals']['lines']);
        $cnt = collect($json['companies'])->firstWhere('code', 'CNT');
        $this->assertSame(Asset::where('company_id', $cnt['id'])->whereHas('model.category', fn ($q) => $q->where('kind', 'line'))->count(), $cnt['lines']);
    }

    public function test_linha_movel_vai_com_o_telemovel(): void
    {
        Sanctum::actingAs($this->admin());
        $line = Asset::where('name', '912 345 601')->firstOrFail();

        $this->getJson("/api/v1/hardware/{$line->id}")
            ->assertJsonPath('assigned_to.type', 'asset')
            ->assertJsonPath('custom_fields.Tarifário.value', 'Biz Max');
    }
}
