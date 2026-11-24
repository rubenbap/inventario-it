<?php

namespace App\Services\Import;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomFieldset;
use App\Models\Import;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CheckoutService;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Importer
{
    public const MODULES = [
        'assets' => ['label' => 'Assets', 'fields' => [
            'company' => ['Empresa', 'empresa company'], 'tag' => ['Asset tag', 'tag assettag etiqueta'], 'name' => ['Nome', 'nome name descricao assetname itemname'],
            'brand' => ['Marca', 'marca fabricante manufacturer'], 'model' => ['Modelo', 'modelo model modelname'], 'category' => ['Categoria', 'categoria category tipo'],
            'serial' => ['S/N / IMEI', 'sn serial serialnumber numerodeserie nserie imei'], 'status' => ['Estado', 'estado status statuslabel'], 'location' => ['Localização', 'localizacao local location'],
            'purchase' => ['Data de compra', 'datadecompra datacompra compra purchasedate purchased'], 'cost' => ['Custo', 'custo preco valor purchasecost'], 'order' => ['N.º encomenda', 'nencomenda encomenda ordernumber'],
            'warranty' => ['Garantia (meses)', 'garantia garantiameses warrantymonths'], 'assigned' => ['Atribuído a', 'atribuidoa atribuido utilizador pessoa colaborador fullname checkedoutto assignedto'],
            'email' => ['E-mail da pessoa', 'email emaildapessoa'], 'notes' => ['Notas', 'notas observacoes obs'],
        ]],
        'snipeit' => ['label' => 'Snipe-IT (assets)', 'fields' => [
            'company' => ['Company', 'company empresa'], 'tag' => ['Asset Tag', 'assettag tag'], 'name' => ['Asset Name', 'assetname itemname name'],
            'brand' => ['Manufacturer', 'manufacturer marca'], 'model' => ['Model Name', 'modelname model modelo'], 'modelno' => ['Model Number', 'modelnumber modelno'],
            'category' => ['Category', 'category categoria'], 'serial' => ['Serial', 'serial serialnumber sn'], 'status' => ['Status', 'status statuslabel estado'],
            'location' => ['Location', 'location localizacao'], 'purchase' => ['Purchase Date', 'purchasedate purchased'], 'cost' => ['Purchase Cost', 'purchasecost cost'],
            'order' => ['Order Number', 'ordernumber'], 'warranty' => ['Warranty Months', 'warrantymonths warranty'], 'assigned' => ['Full Name', 'fullname checkedoutto assignedto'],
            'email' => ['Email', 'email'], 'ip' => ['IP Address', 'ipaddress ip'], 'hostname' => ['Hostname', 'hostname'], 'imei' => ['IMEI', 'imei'], 'notes' => ['Notes', 'notes notas'],
        ]],
        'printers' => ['label' => 'Impressoras', 'fields' => [
            'company' => ['Empresa', 'empresa'], 'hostname' => ['Nome (hostname)', 'nome hostname name'], 'brand' => ['Marca', 'marca'], 'model' => ['Modelo', 'modelo'],
            'serial' => ['S/N', 'sn serial numerodeserie'], 'ip' => ['IP', 'ip enderecoip'], 'tecnologia' => ['Tecnologia', 'tecnologia'], 'cor' => ['Cor', 'cor color'],
            'multifuncoes' => ['Multifunções', 'multifuncoes mfp'], 'location' => ['Localização', 'localizacao local sala'],
        ]],
        'servers' => ['label' => 'Servidores e NAS', 'fields' => [
            'company' => ['Empresa', 'empresa'], 'hostname' => ['Nome', 'nome hostname name'], 'type' => ['Tipo', 'tipo type'], 'brand' => ['Marca', 'marca'],
            'model' => ['Modelo', 'modelo'], 'serial' => ['S/N', 'sn serial'], 'ip' => ['IP', 'ip enderecoip'], 'location' => ['Localização', 'localizacao local bastidor'],
        ]],
        'lines' => ['label' => 'Linhas móveis', 'fields' => [
            'company' => ['Empresa', 'empresa'], 'tarifario' => ['Tarifário', 'tarifario tarifarios plano'], 'numero' => ['Nº telefone', 'nrtelefone ntelefone numero telefone numerodetelefone'],
            'nome' => ['Nome', 'nome utilizador colaborador'], 'pinpuk' => ['PIN/PUK', 'pinpuk'], 'pin' => ['PIN', 'pin'], 'puk' => ['PUK', 'puk'],
            'telemovel' => ['Telemóvel', 'telemovel equipamento aparelho modelo'], 'imei' => ['IMEI', 'imei'], 'notas' => ['Data ativação / notas', 'dataativacao ativacao dataactivacao notas observacoes'],
        ]],
        'accessories' => ['label' => 'Acessórios', 'fields' => [
            'company' => ['Empresa', 'empresa company'], 'name' => ['Nome', 'nome name itemname'], 'category' => ['Categoria', 'categoria category'], 'qty' => ['Quantidade', 'quantidade qty total quantity'], 'min' => ['Stock mínimo', 'minimo stockminimo min minqty minamt'],
        ]],
        'users' => ['label' => 'Utilizadores', 'fields' => [
            'company' => ['Empresa', 'empresa company'], 'first' => ['Nome', 'nome primeironome first firstname'], 'last' => ['Apelido', 'apelido sobrenome last lastname'], 'email' => ['E-mail', 'email mail correio'], 'full' => ['Nome completo', 'nomecompleto fullname'],
        ]],
    ];

    public const SNIPE_STATUS = [
        'checkedout' => 'Pronto', 'readytodeploy' => 'Pronto', 'deployed' => 'Pronto', 'deployable' => 'Pronto', 'pending' => 'Em preparação',
        'outforrepair' => 'Em reparação', 'inrepair' => 'Em reparação', 'outfordiagnostics' => 'Em reparação',
        'broken' => 'Avariado', 'lost' => 'Perdido', 'stolen' => 'Perdido', 'loststolen' => 'Perdido',
        'archived' => 'Abatido', 'retired' => 'Abatido', 'disposed' => 'Abatido',
    ];

    public const SNIPE_CATEGORY = [
        '/laptop|notebook|portat/' => ['device', 'Portátil'], '/desktop|workstation|^pcs?$|computador/' => ['device', 'Desktop'],
        '/monitor|display|ecra/' => ['device', 'Monitor'], '/mobile|phone|smartphone|telemovel|telefone/' => ['phone', 'Telemóvel'],
        '/printer|impressora|mfp|scanner/' => ['printer', 'Impressora'], '/^nas$|storage|networkattached/' => ['nas', 'NAS'],
        '/server|servidor/' => ['server', 'Servidor'], '/tablet|ipad/' => ['device', 'Tablet'],
    ];

    public const BRAND_HINTS = [
        '/probook|elitebook|zbook|elitedesk|prodesk|laserjet|officejet|deskjet|pavilion/' => 'HP',
        '/latitude|optiplex|precision|vostro|inspiron|poweredge|xps/' => 'Dell',
        '/thinkpad|thinkcentre|thinkstation|ideapad|thinkvision|thinksystem/' => 'Lenovo',
        '/macbook|imac|macmini|iphone|ipad/' => 'Apple', '/galaxy/' => 'Samsung', '/^diskstation|^ds\d|^rs\d/' => 'Synology',
        '/proliant/' => 'HPE', '/surface/' => 'Microsoft',
    ];

    public const KEY_FIELDS = [
        'assets' => ['tag', 'serial', 'model', 'name'], 'snipeit' => ['tag', 'serial', 'model', 'name', 'imei'],
        'printers' => ['hostname', 'serial', 'model', 'ip'], 'servers' => ['hostname', 'serial', 'model', 'ip'],
        'lines' => ['numero'], 'accessories' => ['name'], 'users' => ['first', 'last', 'email', 'full'],
    ];

    public const BLANK_SERIAL = '/^(n\/?a|na|-+|0+|none|null|sem|s\/n|sn|tbd|x+|\?+|nao tem|desconhecido|unknown)$/i';

    private const DEVICE_WORDS = '/\b(pc|portatil|laptop|desktop|monitor|impressora|printer|servidor|server|nas|switch|router|telemovel|tablet|ipad|iphone|ecra|sala|armazem|stock|reserva|spare|teste|test|backup|g\d|gen)\b/i';

    public const SNIPE_LAYOUT = [
        ['Asset Tag', 'Asset tag', 'Recomendada', 'SN-1001', 'Mantém a tag do Snipe-IT. Se vier vazia, é gerada com o prefixo da empresa.'],
        ['Company', 'Empresa', 'Não', 'Construções Norte', 'Nome ou código. Se faltar, usa a empresa por defeito.'],
        ['Manufacturer', 'Marca', 'Sim', 'Lenovo', 'Criada se não existir.'],
        ['Model Name', 'Modelo', 'Sim', 'ThinkPad T14 Gen 3', 'Criado se não existir.'],
        ['Category', 'Secção', 'Sim', 'Laptops', 'Laptops/Desktops/Monitors vão para Assets, Mobile Phones para Telemóvel, Printers para Impressoras, Servers/NAS para Servidores e NAS.'],
        ['Serial', 'S/N', 'Recomendada', 'PF3ABC01', 'Usado para não duplicar e para atualizar.'],
        ['Status', 'Estado', 'Não', 'Ready to Deploy', 'Ready to Deploy/Deployed passa a Pronto, Pending a Em preparação, Out for Repair a Em reparação, Broken a Avariado, Lost/Stolen a Perdido, Archived a Abatido.'],
        ['Full Name (ou Checked Out To)', 'Atribuído a', 'Não', 'Pedro Lima', 'A pessoa é encontrada pelo nome ou pelo Email; se não existir, é criada.'],
        ['Location', 'Localização', 'Não', 'Sede Porto', 'Criada se não existir. Impressoras e servidores ficam atribuídos a esta localização.'],
        ['Purchase Date', 'Data de compra', 'Não', '2023-04-12', 'Aceita AAAA-MM-DD e DD/MM/AAAA.'],
        ['Purchase Cost', 'Custo', 'Não', '999.00', ''],
        ['Order Number', 'N.º encomenda', 'Não', 'ENC-2023-118', ''],
        ['Warranty Months', 'Garantia (meses)', 'Não', '36', ''],
        ['Asset Name', 'Nome', 'Não', 'PRT-ARMAZEM', 'Nas impressoras e servidores passa a ser o hostname.'],
        ['IP Address (campo personalizado)', 'IP', 'Não', '10.0.1.32', 'Para impressoras e servidores.'],
        ['Model Number, Notes', 'Notas', 'Não', '21AH', 'Guardados nas notas.'],
    ];

    private array $batch = [];
    private array $seen = [];
    private array $messages = [];
    private array $errors = [];
    private Company $default;
    private bool $autoCreate = true;
    private User $actor;

    public function __construct(
        private readonly CustomFieldService $fields,
        private readonly CheckoutService $checkouts,
    ) {}

    public static function detectModule(array $headers): ?string
    {
        $n = array_map([Parsers::class, 'norm'], $headers);
        if (in_array('assettag', $n, true) && count(array_intersect(['modelname', 'model', 'manufacturer', 'category', 'serial'], $n)) >= 2) {
            return 'snipeit';
        }
        $scores = [];
        foreach (self::MODULES as $module => $def) {
            if ($module === 'snipeit') {
                continue;
            }
            $scores[$module] = count(array_filter($n, function ($h) use ($def) {
                foreach ($def['fields'] as [$label, $synonyms]) {
                    if (Parsers::norm($label) === $h || in_array($h, explode(' ', $synonyms), true)) {
                        return true;
                    }
                }

                return false;
            }));
        }
        arsort($scores);
        [$best, $second] = [array_key_first($scores), array_values($scores)[1] ?? 0];

        return $scores[$best] >= 2 && $scores[$best] > $second ? $best : null;
    }

    public static function looksPerson(?string $value): bool
    {
        $t = trim((string) $value);
        if ($t === '' || preg_match('/[\d@_\/-]/', $t)) {
            return false;
        }
        $words = preg_split('/\s+/', $t);

        return count($words) >= 2 && count($words) <= 5
            && collect($words)->every(fn ($w) => preg_match("/^[\\p{L}'.]{2,}$/u", $w) || preg_match('/^(da|de|do|das|dos|e)$/i', $w))
            && ! preg_match(self::DEVICE_WORDS, Str::ascii($t));
    }

    public static function personName(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->map(fn ($w) => preg_match('/^(da|de|do|das|dos|e)$/i', $w) ? Str::lower($w) : ($w === Str::upper($w) || $w === Str::lower($w) ? Str::ucfirst(Str::lower($w)) : $w))
            ->implode(' ');
    }

    private function guessBrand(string $model): ?string
    {
        $n = Parsers::norm($model);
        $known = AssetModel::with('manufacturer:id,name')->get(['id', 'name', 'manufacturer_id'])->first(fn ($m) => Parsers::norm($m->name) === $n);
        if ($known) {
            return $known->manufacturer?->name;
        }
        foreach (self::BRAND_HINTS as $pattern => $brand) {
            if (preg_match($pattern, $n)) {
                return $brand;
            }
        }

        return null;
    }

    public function autoMap(string $module, array $headers): array
    {
        $used = [];

        return array_map(function ($header) use ($module, &$used) {
            $n = Parsers::norm($header);
            $match = null;
            foreach (self::MODULES[$module]['fields'] as $key => [$label, $synonyms]) {
                if (! in_array($key, $used, true) && (Parsers::norm($label) === $n || in_array($n, explode(' ', $synonyms), true))) {
                    $match = $key;
                    break;
                }
            }
            if ($match) {
                $used[] = $match;
            }

            return $match;
        }, $headers);
    }

    public function run(string $module, array $rows, array $map, Company $default, bool $autoCreate, User $actor, bool $commit, ?string $filename = null): array
    {
        $this->default = $default;
        $this->autoCreate = $autoCreate;
        $this->actor = $actor;
        $this->seen = [];
        $this->batch = ['created' => array_fill_keys(['assets', 'users', 'models', 'manufacturers', 'locations', 'accessories', 'companies', 'categories'], []), 'updated' => []];
        $results = [];
        $ok = $errors = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rec = [];
                foreach ($map as $col => $field) {
                    $value = trim((string) ($row[$col] ?? ''));
                    if ($field && $value !== '') {
                        $rec[$field] = $value;
                    }
                }
                if (isset($rec['serial']) && preg_match(self::BLANK_SERIAL, $rec['serial'])) {
                    unset($rec['serial']);
                }
                if (! array_intersect_key($rec, array_flip(self::KEY_FIELDS[$module]))) {
                    continue;
                }

                $this->messages = [];
                $this->errors = [];
                try {
                    [$status, $summary] = DB::transaction(fn () => $this->{$module}($rec));
                    if ($status === 'new' && collect($this->messages)->contains(fn ($m) => $m[0] === 'warn')) {
                        $status = 'warn';
                    }
                    $ok++;
                } catch (RowError $e) {
                    [$status, $summary] = ['error', $e->getMessage() ?: implode(' · ', array_slice($rec, 0, 4))];
                    $errors++;
                }
                $results[] = ['line' => $i + 2, 'status' => $status, 'summary' => $summary, 'messages' => [...array_map(fn ($m) => ['err', $m], $this->errors), ...$this->messages]];
            }

            $import = null;
            if ($commit) {
                $import = Import::create([
                    'user_id' => $actor->id, 'company_id' => $default->id, 'module' => $module, 'filename' => $filename,
                    'ok' => $ok, 'errors' => $errors, 'created' => $this->batch['created'], 'updated' => $this->batch['updated'],
                ]);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['rows' => $results, 'ok' => $ok, 'errors' => $errors, 'import' => $import];
    }

    public function undo(Import $import): void
    {
        DB::transaction(function () use ($import) {
            foreach (array_reverse($import->updated ?? []) as $entry) {
                $class = ['asset' => Asset::class, 'accessory' => Accessory::class, 'user' => User::class][$entry['type']];
                $model = $class::withoutGlobalScopes()->find($entry['id']);
                if ($model) {
                    $model->setRawAttributes($entry['attributes']);
                    $model->save();
                }
            }

            $c = $import->created ?? [];
            Asset::withoutGlobalScopes()->whereIn('id', $c['assets'] ?? [])->get()->each->forceDelete();
            Accessory::withoutGlobalScopes()->whereIn('id', $c['accessories'] ?? [])->get()->each->forceDelete();

            User::withTrashed()->whereIn('id', $c['users'] ?? [])->get()
                ->reject(fn (User $u) => Asset::withoutGlobalScopes()->where('assigned_type', User::class)->where('assigned_id', $u->id)->exists()
                    || AccessoryCheckout::where('assigned_to', $u->id)->exists())
                ->each->forceDelete();
            AssetModel::withTrashed()->whereIn('id', $c['models'] ?? [])->get()
                ->reject(fn ($m) => Asset::withoutGlobalScopes()->where('model_id', $m->id)->exists())->each->forceDelete();
            Manufacturer::withTrashed()->whereIn('id', $c['manufacturers'] ?? [])->get()
                ->reject(fn ($m) => AssetModel::withTrashed()->where('manufacturer_id', $m->id)->exists())->each->forceDelete();
            Location::withoutGlobalScopes()->withTrashed()->whereIn('id', $c['locations'] ?? [])->get()
                ->reject(fn ($l) => Asset::withoutGlobalScopes()->where('location_id', $l->id)->exists())->each->forceDelete();
            Category::whereIn('id', $c['categories'] ?? [])->get()
                ->reject(fn ($cat) => AssetModel::withTrashed()->where('category_id', $cat->id)->exists())->each->forceDelete();
            Company::whereIn('id', $c['companies'] ?? [])->get()
                ->reject(fn ($co) => Asset::withoutGlobalScopes()->withTrashed()->where('company_id', $co->id)->exists()
                    || Accessory::withoutGlobalScopes()->where('company_id', $co->id)->exists()
                    || User::withTrashed()->where('company_id', $co->id)->exists())
                ->each->forceDelete();

            $import->update(['undone_at' => now()]);
            ActivityLogger::log('update', $import, null, 'Importação de '.self::MODULES[$import->module]['label'].' anulada');
        });
    }

    private function assets(array $rec): array
    {
        return $this->equipment($rec, 'assets');
    }

    private function snipeit(array $rec): array
    {
        $category = Parsers::norm($rec['category'] ?? '');
        $hit = null;
        foreach (self::SNIPE_CATEGORY as $pattern => $target) {
            if (preg_match($pattern, $category)) {
                $hit = $target;
                break;
            }
        }

        $nameIsPerson = empty($rec['assigned']) && empty($rec['email']) && self::looksPerson($rec['name'] ?? null);
        if ($nameIsPerson) {
            $this->info("\"{$rec['name']}\" no Asset Name parece uma pessoa: é ela que fica com o equipamento.");
        }
        $base = [
            ...$rec,
            'serial' => $rec['serial'] ?? $rec['imei'] ?? null,
            'assigned' => $rec['assigned'] ?? ($nameIsPerson ? $rec['name'] : null),
            'notes' => implode(' · ', array_filter([isset($rec['modelno']) ? 'Model Number: '.$rec['modelno'] : null, $rec['notes'] ?? null])) ?: null,
        ];
        if (! empty($rec['status']) && ! $this->status($rec['status'])) {
            $this->warn('Estado "'.$rec['status'].'" do Snipe-IT sem correspondência: fica Pronto.');
            unset($base['status']);
        }
        $base = array_filter($base, fn ($v) => $v !== null && $v !== '');

        if (! $hit && ! empty($rec['category']) && $this->autoCreate) {
            $result = $this->equipment($base, 'assets');
        } elseif (! $hit) {
            $this->warn('Categoria "'.($rec['category'] ?? '—').'" desconhecida: entra como Portátil.');
            unset($base['category']);
            $result = $this->equipment($base, 'assets');
        } elseif ($hit[0] === 'printer') {
            $result = $this->equipment([...$base, 'hostname' => $rec['hostname'] ?? $rec['name'] ?? null], 'printers');
        } elseif (in_array($hit[0], ['server', 'nas'], true)) {
            $result = $this->equipment([...$base, 'hostname' => $rec['hostname'] ?? $rec['name'] ?? $rec['tag'] ?? null, 'type' => $hit[0] === 'nas' ? 'NAS' : 'Servidor'], 'servers');
        } else {
            $result = $this->equipment([...$base, 'category' => $hit[1]], 'assets');
        }

        return [$result[0], ($hit[1] ?? 'Portátil').' · '.$result[1]];
    }

    private function status(string $value): ?StatusLabel
    {
        $status = StatusLabel::whereRaw('lower(name) = ?', [Str::lower($value)])->first();
        $alias = self::SNIPE_STATUS[Parsers::norm($value)] ?? null;

        return $status ?? ($alias ? StatusLabel::where('name', $alias)->first() : null);
    }

    private function printers(array $rec): array
    {
        return $this->equipment($rec, 'printers');
    }

    private function servers(array $rec): array
    {
        return $this->equipment($rec, 'servers');
    }

    private function equipment(array $rec, string $module): array
    {
        $company = $this->company($rec);
        $category = match ($module) {
            'printers' => $this->categoryOfKind('printer'),
            'servers' => $this->categoryOfKind(preg_match('/nas/i', $rec['type'] ?? '') ? 'nas' : 'server'),
            default => $this->assetCategory($rec['category'] ?? null),
        };
        $summary = implode(' · ', array_filter([$rec['serial'] ?? $rec['hostname'] ?? null, trim(($rec['brand'] ?? '').' '.($rec['model'] ?? '')), $rec['ip'] ?? null, $rec['assigned'] ?? $rec['location'] ?? null, $company?->code]));

        $existing = null;
        if (! empty($rec['tag'])) {
            $existing = Asset::withoutGlobalScopes()->where('asset_tag', $rec['tag'])->first();
        }
        if (! $existing && ! empty($rec['serial'])) {
            $existing = Asset::withoutGlobalScopes()->whereRaw('lower(serial) = ?', [Str::lower($rec['serial'])])->first();
        }
        if ($existing && $company && $existing->company_id !== $company->id) {
            $this->err("O S/N ou a tag já existe noutra empresa ({$existing->asset_tag}).");
        }
        if ($existing && ! $this->actor->hasCompany($existing->company_id)) {
            $this->err('Esse equipamento pertence a uma empresa a que não tens acesso.');
        }
        if (empty($rec['serial']) && ! $existing?->serial) {
            $this->warn('Sem n.º de série: fica marcado "Por completar" para preencheres depois.');
        }
        foreach (['serial' => 'S/N', 'tag' => 'Tag'] as $key => $label) {
            if (! empty($rec[$key])) {
                $this->unique($key, $rec[$key], "{$label} {$rec[$key]} repetido no ficheiro.");
            }
        }

        $status = ! empty($rec['status']) ? $this->status($rec['status']) : null;
        if (! empty($rec['status']) && ! $status) {
            $this->err("Estado \"{$rec['status']}\" não existe.");
        }
        if (! empty($rec['ip']) && ! filter_var($rec['ip'], FILTER_VALIDATE_IP)) {
            $this->err("IP inválido: {$rec['ip']}.");
        }
        $purchase = ! empty($rec['purchase']) ? Parsers::date($rec['purchase']) : null;
        if (! empty($rec['purchase']) && ! $purchase) {
            $this->warn("Data de compra \"{$rec['purchase']}\" não reconhecida: ignorada.");
        }
        if (empty($rec['model'])) {
            $this->err('Falta o modelo.');
        } elseif (empty($rec['brand'])) {
            $guess = $this->guessBrand($rec['model']);
            $guess ? $this->info("Marca deduzida pelo modelo: {$guess}.") : $this->warn('Sem marca: fica marcado "Por completar" para preencheres depois.');
            $rec['brand'] = $guess ?? Asset::NO_BRAND;
        }
        if (! empty($rec['model']) && Category::whereRaw('lower(name) = ?', [Str::lower($rec['model'])])->exists()) {
            $this->warn("Modelo \"{$rec['model']}\" é genérico: fica marcado \"Por completar\".");
        }
        $this->guard($summary);

        $model = $this->ensureModel($rec['brand'], $rec['model'], $category);
        $this->guard($summary);

        $data = array_filter([
            'model_id' => $model->id,
            'status_id' => ($status ?? StatusLabel::where('deployable', true)->orderBy('id')->first())->id,
            'serial' => $rec['serial'] ?? null,
            'name' => $rec['name'] ?? $rec['hostname'] ?? null,
            'purchase_date' => $purchase,
            'purchase_cost' => isset($rec['cost']) ? (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $rec['cost'])) : null,
            'warranty_months' => isset($rec['warranty']) ? (int) $rec['warranty'] : null,
            'order_number' => $rec['order'] ?? null,
            'notes' => $rec['notes'] ?? null,
            'location_id' => ! empty($rec['location']) ? $this->ensureLocation($rec['location'], $company->id)->id : null,
        ], fn ($v) => $v !== null);

        if ($existing) {
            $this->snapshot($existing);
            $asset = $existing;
            $asset->fill($data);
            $outcome = 'update';
        } else {
            $asset = new Asset($data);
            $asset->company_id = $company->id;
            $asset->asset_tag = $rec['tag'] ?? $company->nextAssetTag();
            $asset->created_by = $this->actor->id;
            $outcome = 'new';
        }
        $this->setCustom($asset, $model, array_filter([
            'Hostname' => $rec['hostname'] ?? null, 'Endereço IP' => $rec['ip'] ?? null,
            'Tecnologia' => isset($rec['tecnologia']) ? Str::ucfirst(Str::lower($rec['tecnologia'])) : null,
            'Cor' => isset($rec['cor']) ? Str::ucfirst(Str::lower($rec['cor'])) : null,
            'Multifunções' => isset($rec['multifuncoes']) ? Str::ucfirst(Str::lower($rec['multifuncoes'])) : null,
        ]));
        $asset->save();
        $existing ? ActivityLogger::log('update', $asset, null, 'Importação') : $this->created('assets', $asset);

        if (! empty($rec['assigned']) || ! empty($rec['email'])) {
            $user = $this->ensureUser($rec['assigned'] ?? null, $company->id, $rec['email'] ?? null);
            $state = StatusLabel::find($asset->status_id);
            if (! $state?->deployable) {
                $this->warn("Estado \"{$state?->name}\": a pessoa fica registada, mas o equipamento não é atribuído.");
            } elseif ($asset->isAssigned() && ! ($asset->assigned_type === User::class && $asset->assigned_id === $user->id)) {
                $this->warn('Já está atribuído a outra pessoa ou sítio: mantém-se.');
            } else {
                $this->assign($asset, $user);
                $this->info("Atribuído a {$user->name}.");
            }
        } elseif ($module !== 'assets' && isset($data['location_id']) && ! $asset->isAssigned()) {
            $this->assign($asset, Location::withoutGlobalScopes()->find($data['location_id']));
        }

        return [$outcome, $summary];
    }

    private function lines(array $rec): array
    {
        $company = $this->company($rec);
        $number = preg_replace('/\D/', '', $rec['numero'] ?? '');
        $summary = implode(' · ', array_filter([trim(chunk_split($number, 3, ' ')), $rec['tarifario'] ?? null, $rec['nome'] ?? 'livre', $rec['telemovel'] ?? null, $company?->code]));

        if (! preg_match('/^9[1236]\d{7}$/', $number)) {
            $this->err('Número "'.($rec['numero'] ?? '').'" inválido: tem de ter 9 dígitos e começar por 91, 92, 93 ou 96.');
        }
        $this->unique('numero', $number, "Número {$number} repetido no ficheiro.");
        $this->guard($summary);

        $lineModel = $this->lineModel();
        $numberColumn = $this->column($lineModel, 'Número de telefone');
        $existing = Asset::withoutGlobalScopes()->where('model_id', $lineModel->id)->where("custom_values->{$numberColumn}", $number)->first();
        if ($existing && ! $this->actor->hasCompany($existing->company_id)) {
            $this->err('Essa linha pertence a uma empresa a que não tens acesso.');
        }

        $pin = $rec['pin'] ?? null;
        $puk = $rec['puk'] ?? null;
        if (! empty($rec['pinpuk'])) {
            $parts = array_map(fn ($p) => preg_replace('/\s/', '', $p), preg_split('/[\/|;]/', $rec['pinpuk']));
            $pin ??= $parts[0] ?? null;
            $puk ??= $parts[1] ?? null;
        }
        $notesExtra = [];
        if ($pin && ! preg_match('/^\d{4,8}$/', $pin)) {
            $this->warn("PIN \"{$pin}\" inválido: vai para as notas.");
            $notesExtra[] = "PIN/PUK original: {$rec['pinpuk']}";
            $pin = null;
        }
        if ($puk && ! preg_match('/^\d{8}$/', $puk)) {
            $this->warn("PUK \"{$puk}\" inválido: vai para as notas.");
            $puk = null;
        }

        $phoneInfo = Parsers::phone($rec['telemovel'] ?? null);
        $imei = preg_replace('/\D/', '', $rec['imei'] ?? '');
        if ($imei !== '' && ! Parsers::luhn($imei)) {
            $this->warn("IMEI {$rec['imei']} não passa na verificação (Luhn). Confirma o número.");
        }
        if ($imei !== '') {
            $this->unique('serial', $imei, "IMEI {$imei} repetido no ficheiro.");
        }
        if ($phoneInfo && $imei === '') {
            $this->warn("Telemóvel \"{$rec['telemovel']}\" sem IMEI: não é criado como equipamento, fica nas notas.");
            $notesExtra[] = "Telemóvel: {$rec['telemovel']}";
        }
        $notes = Parsers::lineNotes($rec['notas'] ?? null);
        if (! empty($notes['unknown'])) {
            $this->warn("Notas \"{$notes['raw']}\" não reconhecidas: ficam como notas.");
            $notesExtra[] = $notes['raw'];
        }
        $this->guard($summary);

        $companyId = $existing?->company_id ?? $company->id;
        $values = array_filter([
            'Número de telefone' => $number, 'Tarifário' => $rec['tarifario'] ?? null, 'PIN' => $pin, 'PUK' => $puk,
            'Data de ativação' => $notes['ativacao'] ?? null, 'Portado de' => $notes['portado'] ?? null,
            'Acessórios entregues' => $notes['acessorios'] ?? null, 'Data dos acessórios' => $notes['acc_date'] ?? null,
        ]);

        if ($existing) {
            $this->snapshot($existing);
            $line = $existing;
            $outcome = 'update';
        } else {
            $line = new Asset([
                'model_id' => $lineModel->id,
                'status_id' => StatusLabel::where('deployable', true)->orderBy('id')->value('id'),
                'name' => trim(chunk_split($number, 3, ' ')),
            ]);
            $line->company_id = $companyId;
            $line->asset_tag = Company::find($companyId)->nextAssetTag();
            $line->created_by = $this->actor->id;
            $outcome = 'new';
        }
        $this->setCustom($line, $lineModel, $values);
        if ($notesExtra) {
            $line->notes = implode(' · ', array_filter([$line->notes, ...$notesExtra]));
        }
        $line->save();
        $existing ? ActivityLogger::log('update', $line, null, 'Importação') : $this->created('assets', $line);

        $owner = ! empty($rec['nome']) ? $this->ensureUser($rec['nome'], $companyId) : null;
        $phone = $imei !== '' ? Asset::withoutGlobalScopes()->where('serial', $imei)->first() : null;
        if ($imei !== '' && ! $phone) {
            $category = $this->categoryOfKind('phone');
            $model = $phoneInfo
                ? $this->ensureModel($phoneInfo['brand'], $phoneInfo['model'], $category)
                : $this->ensureModel('Desconhecida', 'Telemóvel desconhecido', $category, true);
            $this->guard($summary);
            $phone = new Asset([
                'model_id' => $model->id,
                'status_id' => StatusLabel::where('deployable', true)->orderBy('id')->value('id'),
                'serial' => $imei,
                'purchase_date' => $phoneInfo['date'] ?? $notes['acc_date'] ?? null,
                'notes' => ($phoneInfo['new'] ?? false) || ($notes['acc_new'] ?? false) ? 'Entregue novo' : null,
            ]);
            $phone->company_id = $companyId;
            $phone->asset_tag = Company::find($companyId)->nextAssetTag();
            $phone->created_by = $this->actor->id;
            $phone->save();
            $this->created('assets', $phone);
        }

        if ($phone) {
            if ($owner && $phone->assigned_type !== User::class) {
                $this->assign($phone, $owner);
            } elseif ($owner && $phone->assigned_id !== $owner->id) {
                $this->warn("O telemóvel {$imei} está com outra pessoa: não é reatribuído.");
            }
            $this->assign($line, $phone);
        } elseif ($owner) {
            $this->assign($line, $owner);
        }

        return [$outcome, $summary];
    }

    private function accessories(array $rec): array
    {
        $company = $this->company($rec);
        $qty = (int) ($rec['qty'] ?? 0);
        $summary = implode(' · ', array_filter([$rec['name'] ?? null, $rec['category'] ?? null, "{$qty} un.", $company?->code]));
        if (empty($rec['name'])) {
            $this->err('Falta o nome.');
        }
        $this->guard($summary);

        $category = Category::firstOrCreate(['name' => $rec['category'] ?? 'Outros', 'category_type' => 'accessory'], ['kind' => 'accessory']);
        $existing = Accessory::withoutGlobalScopes()->where('company_id', $company->id)->whereRaw('lower(name) = ?', [Str::lower($rec['name'])])->first();

        if ($existing) {
            if ($qty < $existing->openCheckouts()->count()) {
                $this->err('A quantidade não pode ser menor do que as unidades entregues.');
                $this->guard($summary);
            }
            $this->batch['updated'][] = ['type' => 'accessory', 'id' => $existing->id, 'attributes' => $existing->getRawOriginal()];
            $existing->fill(array_filter(['qty' => $qty, 'min_amt' => isset($rec['min']) ? (int) $rec['min'] : null, 'category_id' => $category->id], fn ($v) => $v !== null))->save();
            ActivityLogger::log('update', $existing, null, 'Importação');

            return ['update', $summary];
        }

        $accessory = new Accessory(['name' => $rec['name'], 'category_id' => $category->id, 'qty' => $qty, 'min_amt' => isset($rec['min']) ? (int) $rec['min'] : null]);
        $accessory->company_id = $company->id;
        $accessory->created_by = $this->actor->id;
        $accessory->save();
        $this->created('accessories', $accessory);

        return ['new', $summary];
    }

    private function users(array $rec): array
    {
        $company = $this->company($rec);
        $first = $rec['first'] ?? null;
        $last = $rec['last'] ?? null;
        if (! $first && ! empty($rec['full'])) {
            [$first, $last] = array_pad(explode(' ', trim($rec['full']), 2), 2, null);
        }
        $summary = implode(' · ', array_filter([trim("{$first} {$last}"), $rec['email'] ?? null, $company?->code]));
        if (! $first) {
            $this->err('Falta o nome.');
        }
        if (! empty($rec['email']) && ! filter_var($rec['email'], FILTER_VALIDATE_EMAIL)) {
            $this->err("E-mail \"{$rec['email']}\" inválido.");
        }
        $this->guard($summary);

        $existing = ! empty($rec['email'])
            ? User::whereRaw('lower(email) = ?', [Str::lower($rec['email'])])->first()
            : $this->findUser(trim("{$first} {$last}"), $company->id);

        if ($existing) {
            $this->batch['updated'][] = ['type' => 'user', 'id' => $existing->id, 'attributes' => $existing->getRawOriginal()];
            $existing->fill(array_filter(['first_name' => $first, 'last_name' => $last, 'email' => $rec['email'] ?? null, 'company_id' => $company->id]))->save();
            ActivityLogger::log('update', $existing, null, 'Importação');

            return ['update', $summary];
        }

        $user = User::create(['first_name' => $first, 'last_name' => $last, 'email' => $rec['email'] ?? null, 'company_id' => $company->id]);
        $this->created('users', $user);

        return ['new', $summary];
    }

    private function company(array $rec): ?Company
    {
        if (empty($rec['company'])) {
            return $this->default;
        }
        $value = Str::lower($rec['company']);
        $company = Company::query()->whereRaw('lower(code) = ?', [$value])->orWhereRaw('lower(name) = ?', [$value])->first();
        if (! $company && $this->autoCreate && $this->actor->isAdmin()) {
            $company = Company::create(['name' => trim($rec['company']), 'code' => Company::codeFor($rec['company']), 'active' => true]);
            $this->created('companies', $company);
            $this->actor->forgetCompanyIds();
            $this->info("Criada a empresa {$company->name} (tags {$company->code}-00001…).");
        }
        if (! $company) {
            $this->err("Empresa \"{$rec['company']}\" não existe. Cria-a em Empresas ou corrige o nome.");
        } elseif (! $this->actor->hasCompany($company->id)) {
            $this->err("Não tens acesso à empresa {$company->name}.");
        }

        return $company && $this->actor->hasCompany($company->id) ? $company : null;
    }

    private function assetCategory(?string $name): Category
    {
        if ($name) {
            $category = Category::where('category_type', 'asset')->whereIn('kind', ['device', 'phone'])->whereRaw('lower(name) = ?', [Str::lower($name)])->first();
            if ($category) {
                return $category;
            }
            if (! $this->autoCreate) {
                $this->err("Categoria \"{$name}\" não existe.");
            } else {
                $category = Category::create(['name' => Str::ucfirst(trim($name)), 'category_type' => 'asset', 'kind' => 'device']);
                $this->created('categories', $category, false);
                $this->info("Criada a categoria {$category->name}.");

                return $category;
            }
        }

        return $this->categoryOfKind('device');
    }

    private function categoryOfKind(string $kind): Category
    {
        return Category::where('category_type', 'asset')->where('kind', $kind)->orderBy('id')->first()
            ?? Category::create(['name' => Str::ucfirst($kind), 'category_type' => 'asset', 'kind' => $kind]);
    }

    private function ensureModel(string $brand, string $name, Category $category, bool $force = false): AssetModel
    {
        $manufacturer = Manufacturer::whereRaw('lower(name) = ?', [Str::lower(trim($brand))])->first();
        $model = $manufacturer ? $this->findModel($manufacturer, $name) : null;
        if ($model) {
            return $model;
        }
        if (! $this->autoCreate && ! $force) {
            $this->err("O modelo {$brand} {$name} não existe.");
            throw new RowError('');
        }
        if (! $manufacturer) {
            $manufacturer = Manufacturer::create(['name' => trim($brand)]);
            $this->created('manufacturers', $manufacturer, false);
        }
        $fieldset = isset(Category::KIND_FIELDSET[$category->kind]) ? CustomFieldset::where('name', Category::KIND_FIELDSET[$category->kind])->value('id') : null;
        $model = AssetModel::create(['name' => trim($name), 'manufacturer_id' => $manufacturer->id, 'category_id' => $category->id, 'fieldset_id' => $fieldset]);
        $this->created('models', $model, false);
        $this->info("Criado o modelo {$manufacturer->name} {$model->name}.");

        return $model;
    }

    private function findModel(Manufacturer $manufacturer, string $name): ?AssetModel
    {
        $n = Parsers::norm($name);
        $bare = preg_replace('/^galaxy/', '', $n);

        return AssetModel::where('manufacturer_id', $manufacturer->id)->get()
            ->first(fn ($m) => Parsers::norm($m->name) === $n || preg_replace('/^galaxy/', '', Parsers::norm($m->name)) === $bare);
    }

    private function lineModel(): AssetModel
    {
        $category = $this->categoryOfKind('line');

        return AssetModel::where('category_id', $category->id)->orderBy('id')->first()
            ?? $this->ensureModel('Operadora Móvel', 'Cartão SIM', $category, true);
    }

    private function ensureLocation(string $name, int $companyId): Location
    {
        $location = Location::withoutGlobalScopes()->whereRaw('lower(name) = ?', [Str::lower(trim($name))])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->first();
        if ($location) {
            return $location;
        }
        if (! $this->autoCreate) {
            $this->err("Localização \"{$name}\" não existe.");
            throw new RowError('');
        }
        $location = Location::create(['name' => trim($name), 'company_id' => $companyId]);
        $this->created('locations', $location, false);
        $this->info("Criada a localização {$location->name}.");

        return $location;
    }

    private function findUser(string $text, ?int $companyId): ?User
    {
        $value = Str::lower(trim($text));
        $users = User::query()->whereRaw('lower(email) = ?', [$value])
            ->orWhereRaw("lower(trim(first_name || ' ' || coalesce(last_name, ''))) = ?", [$value])->get();

        return $users->firstWhere('company_id', $companyId) ?? $users->first();
    }

    private function ensureUser(?string $name, int $companyId, ?string $email = null): User
    {
        if (! $email && str_contains((string) $name, '@')) {
            [$email, $name] = [$name, null];
        }
        $user = ($email ? User::whereRaw('lower(email) = ?', [Str::lower(trim($email))])->first() : null)
            ?? ($name ? $this->findUser($name, $companyId) : null);
        if ($user) {
            if ($email && ! $user->email) {
                $user->update(['email' => trim($email)]);
            }

            return $user;
        }
        if (! $this->autoCreate) {
            $this->err('Pessoa "'.($name ?: $email).'" não existe.');
            throw new RowError('');
        }
        $full = self::personName($name ?: Str::of(Str::before($email, '@'))->replaceMatches('/[._-]+/', ' ')->toString());
        [$first, $last] = array_pad(explode(' ', $full, 2), 2, null);
        $user = User::create(['first_name' => $first, 'last_name' => $last, 'email' => $email ? trim($email) : null, 'company_id' => $companyId]);
        $this->created('users', $user);
        $this->info("Criada a pessoa {$user->name} (sem login).");

        return $user;
    }

    private function column(AssetModel $model, string $fieldName): string
    {
        $model->loadMissing('fieldset.fields');

        return $this->fields->fieldsFor($model)->firstWhere('name', $fieldName)?->db_column ?? Str::slug($fieldName, '_');
    }

    private function setCustom(Asset $asset, AssetModel $model, array $byName): void
    {
        $model->loadMissing('fieldset.fields');
        $values = [];
        foreach ($this->fields->fieldsFor($model) as $field) {
            if (! array_key_exists($field->name, $byName)) {
                continue;
            }
            $value = $byName[$field->name];
            if (in_array($field->element, ['listbox', 'radio'], true) && ! in_array($value, $field->options(), true)) {
                $field->field_values = trim($field->field_values."\n".$value);
                $field->save();
                $this->info("Acrescentada a opção \"{$value}\" a {$field->name}.");
            }
            $values[$field->db_column] = $value;
        }
        $asset->custom_values = $this->fields->merge($model, $values, $asset->custom_values);
    }

    private function assign(Asset $asset, ?Model $target): void
    {
        if (! $target || ($asset->assigned_type === $target::class && $asset->assigned_id === $target->getKey())) {
            return;
        }
        $this->snapshot($asset);
        try {
            if ($asset->isAssigned()) {
                $this->checkouts->checkin($asset, ['note' => 'Importação']);
            }
            $type = match ($target::class) {
                User::class => 'user',
                Location::class => 'location',
                default => 'asset',
            };
            $this->checkouts->checkout($asset->fresh(), ['checkout_to_type' => $type, 'assigned_'.$type => $target->getKey(), 'note' => 'Importação'], $this->actor);
        } catch (ValidationException $e) {
            $this->warn("{$asset->asset_tag} não foi atribuído: ".collect($e->errors())->flatten()->first());
        }
    }

    private function snapshot(Asset $asset): void
    {
        if (in_array($asset->id, $this->batch['created']['assets'], true)
            || collect($this->batch['updated'])->contains(fn ($u) => $u['type'] === 'asset' && $u['id'] === $asset->id)) {
            return;
        }
        $this->batch['updated'][] = ['type' => 'asset', 'id' => $asset->id, 'attributes' => $asset->getRawOriginal()];
    }

    private function created(string $type, Model $model, bool $log = true): void
    {
        $this->batch['created'][$type][] = $model->getKey();
        if ($log) {
            ActivityLogger::log('create', $model, null, 'Importação');
        }
    }

    private function unique(string $key, string $value, string $message): void
    {
        $k = $key.':'.Parsers::norm($value);
        if (isset($this->seen[$k])) {
            $this->err($message);
        }
        $this->seen[$k] = true;
    }

    private function guard(string $summary): void
    {
        if ($this->errors) {
            throw new RowError($summary);
        }
    }

    private function err(string $message): void
    {
        $this->errors[] = $message;
    }

    private function warn(string $message): void
    {
        $this->messages[] = ['warn', $message];
    }

    private function info(string $message): void
    {
        $this->messages[] = ['info', $message];
    }
}
