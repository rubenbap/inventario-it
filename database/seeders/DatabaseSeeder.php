<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->statusLabels();
        $this->categories();
        $this->customFields();
        $this->admin();
    }

    private function statusLabels(): void
    {
        $labels = [
            ['Pronto', 'deployable', '#16A34A'],
            ['Em preparação', 'pending', '#2563EB'],
            ['Em reparação', 'pending', '#EA580C'],
            ['Avariado', 'undeployable', '#B91C1C'],
            ['Perdido', 'undeployable', '#6B7280'],
            ['Abatido', 'archived', '#374151'],
            ['Vendido', 'archived', '#374151'],
        ];

        foreach ($labels as [$name, $type, $color]) {
            StatusLabel::updateOrCreate(['name' => $name], [...StatusLabel::flagsForType($type), 'color' => $color]);
        }
    }

    private function categories(): void
    {
        $assets = ['Portátil' => 'device', 'Desktop' => 'device', 'Monitor' => 'device', 'Tablet' => 'device', 'Rede' => 'device',
            'Telemóvel' => 'phone', 'Impressora' => 'printer', 'Servidor' => 'server', 'NAS' => 'nas', 'Linha móvel' => 'line'];
        foreach ($assets as $name => $kind) {
            Category::updateOrCreate(['name' => $name, 'category_type' => 'asset'], ['kind' => $kind]);
        }
        foreach (['Periféricos', 'Cabos', 'Docks', 'Áudio'] as $name) {
            Category::updateOrCreate(['name' => $name, 'category_type' => 'accessory'], ['kind' => 'accessory']);
        }
    }

    private function customFields(): void
    {
        $field = fn (string $name, array $attrs = []) => CustomField::firstOrCreate(['name' => $name], $attrs);

        $ip = $field('Endereço IP', ['format' => 'IP']);
        $hostname = $field('Hostname', ['format' => 'regex:/^[A-Za-z0-9][A-Za-z0-9.-]{0,62}$/']);

        $printer = CustomFieldset::firstOrCreate(['name' => 'Impressora']);
        $this->attach($printer, [
            $ip,
            $hostname,
            $field('Tecnologia', ['element' => 'listbox', 'field_values' => "Laser\nJato de tinta"]),
            $field('Cor', ['element' => 'listbox', 'field_values' => "Cor\nMono"]),
            $field('Multifunções', ['element' => 'listbox', 'field_values' => "Sim\nNão"]),
            $field('Formato máximo', ['element' => 'listbox', 'field_values' => "A4\nA3"]),
            $field('Contador de páginas', ['format' => 'NUMERIC']),
            $field('Página de gestão', ['format' => 'URL']),
            $field('Tipo de contrato', ['element' => 'listbox', 'field_values' => "Compra\nAluguer\nManutenção"]),
            $field('Fim do contrato', ['format' => 'DATE']),
        ]);

        $server = CustomFieldset::firstOrCreate(['name' => 'Servidor / NAS']);
        $this->attach($server, [$hostname, $ip]);

        $line = CustomFieldset::firstOrCreate(['name' => 'Linha móvel']);
        $this->attach($line, [
            [$field('Número de telefone', ['format' => 'regex:/^9[1236]\d{7}$/', 'show_in_listview' => true]), true],
            $field('Tarifário', ['element' => 'listbox', 'field_values' => "Biz Standard\nBiz Max", 'show_in_listview' => true]),
            $field('PIN', ['format' => 'regex:/^\d{4,8}$/', 'field_encrypted' => true]),
            $field('PUK', ['format' => 'regex:/^\d{8}$/', 'field_encrypted' => true]),
            $field('Data de ativação', ['format' => 'DATE']),
            $field('Portado de'),
            $field('Acessórios entregues', ['element' => 'checkbox', 'field_values' => "Capa\nPelícula"]),
            $field('Data dos acessórios', ['format' => 'DATE']),
        ]);
    }

    private function attach(CustomFieldset $set, array $fields): void
    {
        foreach (array_values($fields) as $order => $item) {
            [$field, $required] = is_array($item) ? $item : [$item, false];
            $set->fields()->syncWithoutDetaching([$field->id => ['order' => $order, 'required' => $required]]);
        }
    }

    private function admin(): void
    {
        $email = config('inventario.admin.email');
        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = config('inventario.admin.password') ?: Str::password(16, symbols: false);

        User::create([
            'first_name' => config('inventario.admin.first_name', 'Admin'),
            'email' => $email,
            'username' => 'admin',
            'password' => $password,
            'can_login' => true,
            'role' => User::ROLE_ADMIN,
        ]);

        if (! config('inventario.admin.password')) {
            $this->command?->warn("Administrador criado: {$email} / {$password}  (muda a password depois de entrares)");
        }
    }
}
