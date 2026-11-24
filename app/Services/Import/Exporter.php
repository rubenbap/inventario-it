<?php

namespace App\Services\Import;

use App\Models\Accessory;
use App\Models\ActionLog;
use App\Models\Asset;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CustomFieldService;
use Illuminate\Support\Facades\Crypt;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Exporter
{
    public const MODULES = [
        'assets' => 'Assets', 'printers' => 'Impressoras', 'servers' => 'Servidores e NAS', 'lines' => 'Linhas móveis',
        'accessories' => 'Acessórios', 'users' => 'Utilizadores', 'history' => 'Histórico',
    ];

    private const KINDS = ['assets' => ['device', 'phone'], 'printers' => ['printer'], 'servers' => ['server', 'nas'], 'lines' => ['line']];

    public function __construct(private readonly CustomFieldService $fields) {}

    public function download(string $module, string $format, ?int $companyId, User $user, bool $secrets): StreamedResponse
    {
        $secrets = $secrets && $module === 'lines' && $user->isAdmin();
        if ($secrets) {
            ActivityLogger::log('view encrypted', $user, null, 'Exportou linhas móveis com PIN/PUK');
        }
        $name = 'inventario-'.$module.'-'.now()->format('Y-m-d');
        $rows = $this->rows($module, $companyId, $user, $secrets);

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($rows) {
                $writer = new XlsxWriter;
                $writer->openToFile('php://output');
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
                $writer->close();
            }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => (string) $v, $row), ';', '"', '');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(string $module): StreamedResponse
    {
        $headers = array_map(fn ($f) => $f[0], Importer::MODULES[$module]['fields']);
        $delimiter = $module === 'snipeit' ? ',' : ';';

        return response()->streamDownload(function () use ($headers, $delimiter) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($headers), $delimiter, '"', '');
            fclose($out);
        }, 'modelo-'.$module.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rows(string $module, ?int $companyId, User $user, bool $secrets = false): \Generator
    {
        $filter = fn ($q) => $companyId ? $q->where('company_id', $companyId) : $q;

        if (isset(self::KINDS[$module])) {
            $query = $filter(Asset::query())
                ->with(['company:id,code', 'model.manufacturer', 'model.category', 'model.fieldset.fields', 'status', 'location', 'assigned'])
                ->whereHas('model.category', fn ($q) => $q->whereIn('kind', self::KINDS[$module]))
                ->orderBy('company_id')->orderBy('asset_tag');
            yield from $this->{$module.'Rows'}($query, $secrets);

            return;
        }

        if ($module === 'accessories') {
            yield ['Empresa', 'Nome', 'Categoria', 'Quantidade', 'Entregues', 'Disponíveis', 'Stock mínimo'];
            foreach ($filter(Accessory::query())->with(['company:id,code', 'category'])->withCount('openCheckouts')->orderBy('name')->lazy() as $a) {
                yield [$a->company?->code, $a->name, $a->category?->name, $a->qty, $a->open_checkouts_count, $a->qty - $a->open_checkouts_count, $a->min_amt];
            }

            return;
        }

        if ($module === 'users') {
            yield ['Empresa', 'Nome', 'Apelido', 'E-mail', 'N.º funcionário', 'Login', 'Estado'];
            $query = User::query()->with('company:id,code')->orderBy('first_name')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('company_id', $user->companyIds()));
            foreach ($query->lazy() as $u) {
                yield [$u->company?->code, $u->first_name, $u->last_name, $u->email, $u->employee_num, $u->can_login ? $u->role : 'sem login', $u->activated ? 'Ativo' : 'Inativo'];
            }

            return;
        }

        yield ['Data', 'Ação', 'Tipo', 'Item', 'Destino', 'Por', 'Nota'];
        $query = ActionLog::query()->with(['admin:id,first_name,last_name', 'item', 'target'])->latest()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('company_id', $user->companyIds()));
        foreach ($query->lazy() as $l) {
            yield [
                $l->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i'), $l->action_type, class_basename($l->item_type),
                $l->item?->asset_tag ?? $l->item?->name, $l->target?->asset_tag ?? $l->target?->name, $l->admin?->name, $l->note,
            ];
        }
    }

    private function assetsRows($query, bool $secrets): \Generator
    {
        yield ['Empresa', 'Asset tag', 'Nome', 'Marca', 'Modelo', 'Categoria', 'S/N / IMEI', 'Estado', 'Atribuído a', 'Localização', 'Data de compra', 'Custo', 'Garantia (meses)', 'Fim da garantia', 'Notas'];
        foreach ($query->lazy() as $a) {
            yield [
                $a->company?->code, $a->asset_tag, $a->name, $a->model?->manufacturer?->name, $a->model?->name, $a->model?->category?->name, $a->serial,
                $a->isAssigned() ? 'Atribuído' : $a->status?->name, $this->target($a), $a->location?->name, $a->purchase_date?->format('d/m/Y'),
                $a->purchase_cost, $a->warranty_months, $a->warranty_expires?->format('d/m/Y'), $a->notes,
            ];
        }
    }

    private function printersRows($query, bool $secrets): \Generator
    {
        yield ['Empresa', 'Asset tag', 'Nome', 'Marca', 'Modelo', 'S/N', 'IP', 'Tecnologia', 'Cor', 'Multifunções', 'Localização', 'Estado'];
        foreach ($query->lazy() as $a) {
            $c = $this->custom($a, false);
            yield [$a->company?->code, $a->asset_tag, $c['Hostname'] ?? $a->name, $a->model?->manufacturer?->name, $a->model?->name, $a->serial, $c['Endereço IP'] ?? null, $c['Tecnologia'] ?? null, $c['Cor'] ?? null, $c['Multifunções'] ?? null, $a->location?->name, $a->status?->name];
        }
    }

    private function serversRows($query, bool $secrets): \Generator
    {
        yield ['Empresa', 'Nome', 'Tipo', 'Marca', 'Modelo', 'S/N', 'IP', 'Localização'];
        foreach ($query->lazy() as $a) {
            $c = $this->custom($a, false);
            yield [$a->company?->code, $c['Hostname'] ?? $a->name, $a->model?->category?->kind === 'nas' ? 'NAS' : 'Servidor', $a->model?->manufacturer?->name, $a->model?->name, $a->serial, $c['Endereço IP'] ?? null, $a->location?->name];
        }
    }

    private function linesRows($query, bool $secrets): \Generator
    {
        yield ['Empresa', 'Tarifário', 'Nº telefone', 'Nome', ...($secrets ? ['PIN', 'PUK'] : []), 'Telemóvel', 'IMEI', 'Data ativação', 'Portada de', 'Acessórios'];
        foreach ($query->lazy() as $a) {
            $c = $this->custom($a, $secrets);
            $phone = $a->assigned instanceof Asset ? $a->assigned->loadMissing('model.manufacturer', 'assigned') : null;
            $owner = $phone ? $phone->assigned : $a->assigned;
            yield [
                $a->company?->code, $c['Tarifário'] ?? null, $c['Número de telefone'] ?? null, $owner instanceof User ? $owner->name : null,
                ...($secrets ? [$c['PIN'] ?? null, $c['PUK'] ?? null] : []),
                $phone ? trim($phone->model?->manufacturer?->name.' '.$phone->model?->name) : null, $phone?->serial,
                isset($c['Data de ativação']) ? date('d/m/Y', strtotime($c['Data de ativação'])) : null, $c['Portado de'] ?? null, $c['Acessórios entregues'] ?? null,
            ];
        }
    }

    private function custom(Asset $asset, bool $decrypt): array
    {
        $values = $asset->custom_values ?? [];
        $out = [];
        foreach ($this->fields->fieldsFor($asset->model) as $field) {
            $raw = $values[$field->db_column] ?? null;
            if ($raw === null || ($field->field_encrypted && ! $decrypt)) {
                continue;
            }
            $out[$field->name] = $field->field_encrypted ? rescue(fn () => Crypt::decryptString($raw), null, false) : $raw;
        }

        return $out;
    }

    private function target(Asset $asset): ?string
    {
        $t = $asset->assigned;

        return $t ? ($t instanceof Asset ? $t->asset_tag : $t->name) : null;
    }
}
