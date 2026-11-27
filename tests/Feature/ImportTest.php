<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Import;
use App\Models\User;
use App\Services\Import\Importer;
use App\Services\Import\Parsers;
use App\Services\Import\TabularReader;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private const EXCEL = "Tarifários\tNrº Telefone\tNome\tPIN/PUK\tTelemóvel\tIMEI\tData Ativação\n"
        ."Biz Max\t913000101\tPaulo Mendes\t1435 / 01271758\tSamsung A13\t354360411847635\tPortado da Empresa Antiga - 01/08/24\n"
        ."Biz Standard\t913000102\tBeatriz Faria\t\tSAMSUNG A15 - NOVO\t354747953943287\tCapa + Pelicula - NOVO 13/01/2025\n"
        ."Biz Standard\t913000106\tNuno Brito\t\t\t\t\n"
        ."Biz Standard\t9130001\tErro\t\t\t\t";

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.admin.password' => 'password-de-teste']);
        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        Auth::login($this->admin);
    }

    private function runImport(bool $commit): array
    {
        $parsed = app(TabularReader::class)->fromText(self::EXCEL);
        $importer = app(Importer::class);
        $map = $importer->autoMap('lines', $parsed['headers']);

        return $importer->run('lines', $parsed['rows'], $map, Company::where('code', 'CNT')->first(), true, $this->admin, $commit, 'linhas.xlsx');
    }

    public function test_reconhece_as_colunas_do_excel_das_linhas(): void
    {
        $parsed = app(TabularReader::class)->fromText(self::EXCEL);

        $this->assertSame(['tarifario', 'numero', 'nome', 'pinpuk', 'telemovel', 'imei', 'notas'], app(Importer::class)->autoMap('lines', $parsed['headers']));
    }

    public function test_pre_visualizacao_nao_grava_nada(): void
    {
        $before = Asset::withoutGlobalScopes()->count();
        $result = $this->runImport(false);

        $this->assertSame(3, $result['ok']);
        $this->assertSame(1, $result['errors']);
        $this->assertSame($before, Asset::withoutGlobalScopes()->count());
    }

    public function test_importa_linhas_telemoveis_e_pessoas_e_anula(): void
    {
        $before = Asset::withoutGlobalScopes()->count();
        $result = $this->runImport(true);

        $this->assertSame(3 + 2, Asset::withoutGlobalScopes()->count() - $before);
        $phone = Asset::withoutGlobalScopes()->where('serial', '354360411847635')->firstOrFail();
        $this->assertSame('Galaxy A13', $phone->model->name);
        $this->assertSame('Paulo Mendes', $phone->assigned->name);
        $this->assertSame(Asset::class, Asset::withoutGlobalScopes()->where('name', '913 000 101')->first()->assigned_type);

        app(Importer::class)->undo(Import::findOrFail($result['import']->id));
        $this->assertSame($before, Asset::withoutGlobalScopes()->count());
        $this->assertNull(User::where('first_name', 'Paulo')->first());
    }

    public function test_ficheiro_do_snipe_it_vai_para_a_seccao_certa(): void
    {
        $csv = "Company,Asset Tag,Asset Name,Manufacturer,Model Name,Category,Serial,Status,Location,Full Name,IP Address\n"
            ."CNT,SN-1,,Lenovo,ThinkPad T14 Gen 3,Laptops,PF3ABC01,Deployed,Sede Porto,Pedro Lima,\n"
            ."CNT,SN-2,PRT-ARM,HP,LaserJet Pro M404n,Printers,PHB777,Ready to Deploy,Sede Porto,,10.0.1.32\n"
            ."CNT,SN-3,,HP,ProBook,Laptops,X1,Broken,,,\n";
        $parsed = app(TabularReader::class)->fromText($csv);
        $importer = app(Importer::class);
        $result = $importer->run('snipeit', $parsed['rows'], $importer->autoMap('snipeit', $parsed['headers']), Company::where('code', 'CNT')->first(), true, $this->admin, true);

        $this->assertSame(3, $result['ok']);
        $printer = Asset::withoutGlobalScopes()->where('asset_tag', 'SN-2')->firstOrFail();
        $this->assertSame('printer', $printer->model->category->kind);
        $this->assertSame('Pedro Lima', Asset::withoutGlobalScopes()->where('asset_tag', 'SN-1')->first()->assigned->name);
        $this->assertSame('Avariado', Asset::withoutGlobalScopes()->where('asset_tag', 'SN-3')->first()->status->name);
    }

    public function test_snipe_it_cria_empresa_pessoa_e_marca_incompletos(): void
    {
        $csv = "Asset Tag\tAsset Name\tCompany\tSerial\tModel\tCategory\tStatus\tChecked Out To\n"
            ."LAP1\tAna Menezes\tEmpresa Nova Lda\t5CD000001\tProbook 450 G8\tPC\tChecked out\t\n"
            ."MON1\tSala Reuniões\tEmpresa Nova Lda\tN/A\tMonitor\tMonitor\tReady to Deploy\t\n"
            ."\t\t\t\t\t\tChecked out\t\n";
        $parsed = app(TabularReader::class)->fromText($csv);
        $this->assertSame('snipeit', Importer::detectModule($parsed['headers']));
        $importer = app(Importer::class);
        $result = $importer->run('snipeit', $parsed['rows'], $importer->autoMap('snipeit', $parsed['headers']), Company::where('code', 'CNT')->first(), true, $this->admin, true);

        $this->assertSame(2, $result['ok']);
        $company = Company::where('name', 'Empresa Nova Lda')->firstOrFail();
        $this->assertSame('ENL', $company->code);
        $laptop = Asset::withoutGlobalScopes()->where('asset_tag', 'LAP1')->firstOrFail();
        $this->assertSame('HP', $laptop->model->manufacturer->name);
        $this->assertSame('Ana Menezes', $laptop->assigned->name);
        $this->assertSame([], $laptop->missingFields());
        $monitor = Asset::withoutGlobalScopes()->where('asset_tag', 'MON1')->firstOrFail();
        $this->assertNull($monitor->assigned_id);
        $this->assertSame(['manufacturer', 'model', 'serial'], $monitor->missingFields());
        $this->assertSame(1, Asset::withoutGlobalScopes()->incomplete()->count());

        $importer->undo($result['import']);
        $this->assertNull(Company::withTrashed()->where('name', 'Empresa Nova Lda')->first());
    }

    public function test_parsers_das_notas_e_telemoveis(): void
    {
        $this->assertSame(['brand' => 'Samsung', 'model' => 'Galaxy A15', 'new' => true, 'date' => null], Parsers::phone('SAMSUNG A15 - NOVO'));
        $notes = Parsers::lineNotes('Capa + palicula NOVOS - 08/07/2025');
        $this->assertSame('Capa, Película', $notes['acessorios']);
        $this->assertSame('2025-07-08', $notes['acc_date']);
        $this->assertSame('Empresa Antiga', Parsers::lineNotes('Portado da Empresa Antiga - 01/08/24')['portado']);
    }
}
