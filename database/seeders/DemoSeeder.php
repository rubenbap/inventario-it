<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomFieldset;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomFieldService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    private CustomFieldService $fields;
    private CheckoutService $checkouts;
    private User $admin;

    public function run(CustomFieldService $fields, CheckoutService $checkouts): void
    {
        $this->call(DatabaseSeeder::class);
        $this->fields = $fields;
        $this->checkouts = $checkouts;
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        DB::transaction(function () {
            $companies = $this->companies();
            $loc = $this->locations($companies);
            $sup = $this->suppliers();
            $models = $this->models();
            $people = $this->people($companies, $loc);
            $this->assets($companies, $loc, $sup, $models, $people);
            $this->accessories($companies, $loc);
        });
    }

    private function companies(): array
    {
        $out = [];
        foreach ([['CNT', 'Construções Norte'], ['SSL', 'Serviços Sul'], ['HLD', 'Holding do Grupo']] as [$code, $name]) {
            $out[$code] = Company::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return $out;
    }

    private function locations(array $c): array
    {
        $make = fn ($name, $company) => Location::firstOrCreate(['name' => $name], ['company_id' => $company?->id]);

        return [
            'porto' => $make('Sede Porto', null),
            'armazem' => $make('Sede Porto · Armazém IT', $c['CNT']),
            'rackA' => $make('Sede Porto · Bastidor A', null),
            'rackB' => $make('Sede Porto · Bastidor B', null),
            'maia' => $make('Estaleiro Maia', $c['CNT']),
            'lisboa' => $make('Escritório Lisboa', $c['SSL']),
            'lisboaTec' => $make('Escritório Lisboa · Sala técnica', $c['SSL']),
        ];
    }

    private function suppliers(): array
    {
        return [
            'info' => Supplier::firstOrCreate(['name' => 'Inforlândia']),
            'dell' => Supplier::firstOrCreate(['name' => 'Dell Portugal']),
            'rede' => Supplier::firstOrCreate(['name' => 'Rede Pro']),
            'oper' => Supplier::firstOrCreate(['name' => 'Operadora Móvel']),
        ];
    }

    private function models(): array
    {
        $cat = fn ($n) => Category::where('name', $n)->where('category_type', 'asset')->firstOrFail();
        $set = fn ($n) => CustomFieldset::where('name', $n)->first();
        $make = function ($brand, $name, $category, $fieldset = null) use ($cat, $set) {
            $m = Manufacturer::firstOrCreate(['name' => $brand]);

            return AssetModel::firstOrCreate(
                ['manufacturer_id' => $m->id, 'name' => $name],
                ['category_id' => $cat($category)->id, 'fieldset_id' => $fieldset ? $set($fieldset)?->id : null],
            );
        };

        return [
            't14' => $make('Lenovo', 'ThinkPad T14 Gen 4', 'Portátil'),
            'lat5440' => $make('Dell', 'Latitude 5440', 'Portátil'),
            'p2723' => $make('Dell', 'P2723D', 'Monitor'),
            'mba' => $make('Apple', 'MacBook Air M3', 'Portátil'),
            'a15' => $make('Samsung', 'Galaxy A15', 'Telemóvel'),
            'a35' => $make('Samsung', 'Galaxy A35 5G', 'Telemóvel'),
            'oppo' => $make('OPPO', 'A40', 'Telemóvel'),
            'm404' => $make('HP', 'LaserJet M404dn', 'Impressora', 'Impressora'),
            'mfp' => $make('HP', 'Color LaserJet Pro MFP 4302', 'Impressora', 'Impressora'),
            'r650' => $make('Dell', 'PowerEdge R650', 'Servidor', 'Servidor / NAS'),
            'ml350' => $make('HPE', 'ProLiant ML350 Gen10', 'Servidor', 'Servidor / NAS'),
            'rs2423' => $make('Synology', 'RS2423+', 'NAS', 'Servidor / NAS'),
            'sim' => $make('Operadora Móvel', 'Cartão SIM', 'Linha móvel', 'Linha móvel'),
        ];
    }

    private function people(array $c, array $loc): array
    {
        $person = fn ($first, $last, $company, $location = null) => User::firstOrCreate(
            ['first_name' => $first, 'last_name' => $last],
            ['company_id' => $company->id, 'location_id' => $location?->id],
        );

        User::firstOrCreate(['email' => 'consulta@example.com'], [
            'first_name' => 'Joana', 'last_name' => 'Consulta', 'username' => 'consulta',
            'password' => 'demo-consulta-2026', 'can_login' => true, 'role' => User::ROLE_LEITURA,
            'company_id' => $c['CNT']->id,
        ]);

        return [
            'marta' => $person('Marta', 'Sousa', $c['CNT'], $loc['porto']),
            'pedro' => $person('Pedro', 'Lima', $c['CNT'], $loc['maia']),
            'ana' => $person('Ana', 'Ribeiro', $c['CNT'], $loc['porto']),
            'rui' => $person('Rui', 'Costa', $c['SSL'], $loc['lisboa']),
            'sofia' => $person('Sofia', 'Martins', $c['HLD'], $loc['porto']),
        ];
    }

    private function assets(array $c, array $loc, array $sup, array $m, array $p): void
    {
        if (Asset::withoutGlobalScopes()->exists()) {
            return;
        }
        $ready = StatusLabel::where('name', 'Pronto')->firstOrFail();

        $make = function (Company $company, AssetModel $model, array $attrs = [], array $custom = []) use ($ready) {
            $model->load('fieldset.fields');
            $asset = new Asset([
                'company_id' => $company->id,
                'model_id' => $model->id,
                'status_id' => $ready->id,
                ...$attrs,
            ]);
            $asset->asset_tag = $company->nextAssetTag();
            $asset->custom_values = $this->fields->merge($model, $custom);
            $asset->created_by = $this->admin->id;
            $asset->save();

            return $asset;
        };
        $out = fn (Asset $a, $target, string $type = 'user') => $this->checkouts->checkout(
            $a->load('status'), ['checkout_to_type' => $type, 'assigned_'.$type => $target->id], $this->admin,
        );

        $buy = ['purchase_date' => '2025-03-10', 'supplier_id' => $sup['info']->id, 'warranty_months' => 36, 'rtd_location_id' => $loc['armazem']->id];
        $out($make($c['CNT'], $m['t14'], [...$buy, 'serial' => 'PF4X1A2B', 'purchase_cost' => 1089]), $p['marta']);
        $out($make($c['CNT'], $m['lat5440'], [...$buy, 'serial' => 'CN0R7T9K', 'supplier_id' => $sup['dell']->id, 'purchase_cost' => 980]), $p['pedro']);
        $make($c['CNT'], $m['t14'], [...$buy, 'serial' => 'PF4X1A2C', 'purchase_cost' => 1089]);
        $make($c['CNT'], $m['p2723'], [...$buy, 'serial' => 'CN0P27A1', 'supplier_id' => $sup['dell']->id, 'purchase_cost' => 279]);
        $out($make($c['HLD'], $m['mba'], ['serial' => 'C02FK1ZQ', 'purchase_date' => '2025-09-01', 'warranty_months' => 12]), $p['sofia']);

        $printerFields = fn (AssetModel $model, $ip, $host, $color, $mf) => $this->byName($model, [
            'Endereço IP' => $ip, 'Hostname' => $host, 'Tecnologia' => 'Laser', 'Cor' => $color, 'Multifunções' => $mf,
            'Formato máximo' => 'A4', 'Tipo de contrato' => 'Aluguer', 'Fim do contrato' => '2027-06-30',
        ]);
        $out($make($c['CNT'], $m['m404'], ['serial' => 'PHBQK12345'], $printerFields($m['m404'], '10.0.1.30', 'PRT-PORTO01', 'Mono', 'Não')), $loc['porto'], 'location');
        $out($make($c['SSL'], $m['mfp'], ['serial' => 'CNCRQ67890'], $printerFields($m['mfp'], '10.1.1.30', 'PRT-LIS01', 'Cor', 'Sim')), $loc['lisboa'], 'location');

        $srv = fn ($model, $host, $ip) => $this->byName($model, ['Hostname' => $host, 'Endereço IP' => $ip]);
        $out($make($c['CNT'], $m['r650'], ['serial' => 'SVCTAG01', 'name' => 'SRV-DC01'], $srv($m['r650'], 'SRV-DC01', '10.0.1.10')), $loc['rackA'], 'location');
        $out($make($c['CNT'], $m['rs2423'], ['serial' => 'SYN2423A', 'name' => 'NAS-BKP01'], $srv($m['rs2423'], 'NAS-BKP01', '10.0.1.50')), $loc['rackA'], 'location');
        $out($make($c['SSL'], $m['ml350'], ['serial' => 'CZJ0001X', 'name' => 'SRV-LIS01'], $srv($m['ml350'], 'SRV-LIS01', '10.1.1.10')), $loc['lisboaTec'], 'location');

        $phone = $make($c['CNT'], $m['a15'], ['serial' => '354806116745921', 'name' => 'Telemóvel Marta']);
        $out($phone, $p['marta']);
        $line = $make($c['CNT'], $m['sim'], ['name' => '912 345 601'], $this->byName($m['sim'], [
            'Número de telefone' => '912345601', 'Tarifário' => 'Biz Max', 'PIN' => '4821', 'PUK' => '30918274',
            'Data de ativação' => '2024-08-01', 'Acessórios entregues' => 'Capa, Película',
        ]));
        $out($line, $phone, 'asset');

        $phone2 = $make($c['SSL'], $m['oppo'], ['serial' => '868450063326710', 'name' => 'Telemóvel Rui']);
        $out($phone2, $p['rui']);
        $line2 = $make($c['SSL'], $m['sim'], ['name' => '912 345 604'], $this->byName($m['sim'], [
            'Número de telefone' => '912345604', 'Tarifário' => 'Biz Standard', 'PIN' => '1956', 'PUK' => '66402187',
            'Data de ativação' => '2024-12-10',
        ]));
        $out($line2, $phone2, 'asset');

        $make($c['CNT'], $m['sim'], ['name' => '912 345 607'], $this->byName($m['sim'], [
            'Número de telefone' => '912345607', 'Tarifário' => 'Biz Standard',
        ]));
    }

    private function byName(AssetModel $model, array $values): array
    {
        $model->load('fieldset.fields');
        $out = [];
        foreach ($this->fields->fieldsFor($model) as $field) {
            if (array_key_exists($field->name, $values)) {
                $out[$field->db_column] = $values[$field->name];
            }
        }

        return $out;
    }

    private function accessories(array $c, array $loc): void
    {
        $cat = fn ($n) => Category::where('name', $n)->where('category_type', 'accessory')->firstOrFail();

        Accessory::firstOrCreate(['name' => 'Rato Logitech M185', 'company_id' => $c['CNT']->id],
            ['category_id' => $cat('Periféricos')->id, 'qty' => 10, 'min_amt' => 3, 'location_id' => $loc['armazem']->id]);
        Accessory::firstOrCreate(['name' => 'Dock USB-C Dell WD19', 'company_id' => $c['CNT']->id],
            ['category_id' => $cat('Docks')->id, 'qty' => 6, 'min_amt' => 5, 'location_id' => $loc['armazem']->id]);
        Accessory::firstOrCreate(['name' => 'Cabo HDMI 2 m', 'company_id' => $c['SSL']->id],
            ['category_id' => $cat('Cabos')->id, 'qty' => 12, 'min_amt' => 10]);
    }
}
