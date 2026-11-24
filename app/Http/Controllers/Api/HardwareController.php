<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\AssetTransformer;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Company;
use App\Services\ActivityLogger;
use App\Services\CheckoutService;
use App\Services\CustomFieldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HardwareController extends ApiController
{
    public function __construct(
        private readonly CustomFieldService $fields,
        private readonly CheckoutService $checkouts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->with(AssetTransformer::WITH);

        foreach (['company_id', 'model_id', 'status_id', 'location_id', 'supplier_id', 'rtd_location_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->integer($f));
            }
        }
        if ($request->filled('category_id')) {
            $query->inCategory($request->integer('category_id'));
        }
        if ($request->filled('manufacturer_id')) {
            $query->ofManufacturer($request->integer('manufacturer_id'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_id', $request->integer('assigned_to'))
                ->where('assigned_type', Asset::assignedTypeFor((string) $request->input('assigned_type', 'user')));
        }

        if ($request->boolean('incomplete')) {
            $query->incomplete();
        }

        match (strtolower((string) $request->input('status'))) {
            'rtd' => $query->ready(),
            'deployed' => $query->whereNotNull('assigned_id'),
            'pending' => $query->ofStatusType('pending'),
            'undeployable' => $query->ofStatusType('undeployable'),
            'archived' => $query->ofStatusType('archived'),
            'deleted' => $query->onlyTrashed(),
            default => $request->filled('status_id') ? null : $query->notArchived(),
        };

        if ($request->filled('search')) {
            $this->search($query, (string) $request->input('search'));
        }

        return $this->listing(
            $query,
            $request,
            fn (Asset $a) => AssetTransformer::one($a),
            [],
            ['id', 'name', 'asset_tag', 'serial', 'purchase_date', 'purchase_cost', 'created_at', 'updated_at', 'expected_checkin', 'last_checkout', 'warranty_expires'],
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $asset = Asset::with(AssetTransformer::WITH)->findOrFail($id);

        return response()->json($this->revealed($asset, $request));
    }

    public function pin(int $id): JsonResponse
    {
        $asset = Asset::with('model')->findOrFail($id);
        $secrets = $this->fields->secrets($asset);
        if (! $secrets) {
            return ApiResponse::error('Este asset não tem PIN/PUK.', 404);
        }
        ActivityLogger::log('view encrypted', $asset, null, 'PIN/PUK pedido pela API');
        $find = fn (string $needle) => collect($secrets)->first(fn ($v, $k) => str_contains(Str::lower($k), $needle));

        return response()->json([
            'id' => $asset->id,
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'pin' => $find('pin'),
            'puk' => $find('puk'),
            'fields' => $secrets,
        ]);
    }

    public function byTag(Request $request, string $tag): JsonResponse
    {
        $asset = Asset::with(AssetTransformer::WITH)->where('asset_tag', $tag)->firstOrFail();

        return response()->json($this->revealed($asset, $request));
    }

    public function bySerial(Request $request, string $serial): JsonResponse
    {
        $rows = Asset::with(AssetTransformer::WITH)->where('serial', $serial)->get();

        return ApiResponse::rows($rows->count(), $rows->map(fn ($a) => $this->revealed($a, $request))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->baseRules(null));
        $model = AssetModel::with('fieldset.fields')->findOrFail($data['model_id']);
        Validator::make($request->all(), $this->fields->rulesFor($model))->validate();

        $data['company_id'] = $this->resolveCompanyId($data['company_id'] ?? null, $user);
        $company = Company::findOrFail($data['company_id']);

        $asset = DB::transaction(function () use ($request, $data, $model, $company, $user) {
            $asset = new Asset(collect($data)->except(['assigned_user', 'assigned_location', 'assigned_asset', 'checkout_to_type'])->all());
            $asset->asset_tag = $data['asset_tag'] ?? $company->nextAssetTag();
            $asset->custom_values = $this->fields->merge($model, $request->all());
            $asset->created_by = $user->id;
            $asset->location_id ??= $asset->rtd_location_id;
            $asset->save();
            ActivityLogger::log('create', $asset);

            if (collect(['assigned_user', 'assigned_location', 'assigned_asset'])->contains(fn ($k) => ! empty($data[$k]))) {
                $this->checkouts->checkout($asset, $data, $user);
            }

            return $asset;
        });

        return ApiResponse::success(AssetTransformer::one($asset->fresh(AssetTransformer::WITH)), 'Asset criado com sucesso.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $asset = Asset::with('model.fieldset.fields')->findOrFail($id);
        $data = $request->validate($this->baseRules($asset));
        $data = collect($data)->except(['assigned_user', 'assigned_location', 'assigned_asset', 'checkout_to_type'])->all();

        $model = isset($data['model_id']) ? AssetModel::with('fieldset.fields')->findOrFail($data['model_id']) : $asset->model;
        Validator::make($request->all(), $this->fields->rulesFor($model, partial: true))->validate();

        $transfer = isset($data['company_id']) && (int) $data['company_id'] !== (int) $asset->company_id;
        if ($transfer) {
            if ($asset->isAssigned()) {
                throw ValidationException::withMessages(['company_id' => ['Faz check-in antes de mudar o asset de empresa.']]);
            }
            $this->resolveCompanyId((int) $data['company_id'], $user);
        }

        DB::transaction(function () use ($asset, $data, $model, $request, $transfer) {
            $oldCustom = $asset->custom_values ?? [];
            $oldCompany = $asset->company_id;
            $asset->fill($data);
            $asset->custom_values = $this->fields->merge($model, $request->all(), $oldCustom);
            $asset->save();

            $changes = ActivityLogger::diff($asset);
            $changedFields = $this->changedCustomFields($model, $oldCustom, $asset->custom_values ?? []);
            if ($changedFields) {
                $changes['custom_fields'] = $changedFields;
            }
            if ($changes) {
                ActivityLogger::log('update', $asset, null, null, $changes);
            }
            if ($transfer) {
                ActivityLogger::log('transfer', $asset, null, $request->input('note'), [
                    'company_id' => ['old' => $oldCompany, 'new' => $asset->company_id],
                ]);
            }
        });

        return ApiResponse::success(AssetTransformer::one($asset->fresh(AssetTransformer::WITH)), 'Asset atualizado com sucesso.');
    }

    public function destroy(int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        if ($asset->isAssigned()) {
            return ApiResponse::error('Este asset está atribuído. Faz check-in antes de o apagar.');
        }
        $asset->delete();
        ActivityLogger::log('delete', $asset);

        return ApiResponse::success(null, 'Asset apagado com sucesso.');
    }

    public function restore(int $id): JsonResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->restore();
        ActivityLogger::log('restore', $asset);

        return ApiResponse::success(AssetTransformer::one($asset->fresh(AssetTransformer::WITH)), 'Asset recuperado com sucesso.');
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $asset = Asset::with('status')->findOrFail($id);
        $data = $request->validate([
            'checkout_to_type' => ['nullable', Rule::in(['user', 'location', 'asset'])],
            'assigned_user' => ['nullable', 'integer'],
            'assigned_location' => ['nullable', 'integer'],
            'assigned_asset' => ['nullable', 'integer'],
            'checkout_at' => ['nullable', 'date'],
            'expected_checkin' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:65535'],
            'name' => ['nullable', 'string', 'max:191'],
            'status_id' => ['nullable', 'integer', 'exists:status_labels,id'],
        ]);

        $asset = $this->checkouts->checkout($asset, $data, $request->user());

        return ApiResponse::success(['asset' => $asset->asset_tag], 'Check-out feito com sucesso.');
    }

    public function checkin(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:65535'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status_id' => ['nullable', 'integer', 'exists:status_labels,id'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);

        $asset = $this->checkouts->checkin($asset, $data);

        return ApiResponse::success(['asset' => $asset->asset_tag], 'Check-in feito com sucesso.');
    }

    private function baseRules(?Asset $asset): array
    {
        $s = $asset ? 'sometimes' : 'required';

        return [
            'model_id' => [$s, 'integer', 'exists:models,id'],
            'status_id' => [$s, 'integer', 'exists:status_labels,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'asset_tag' => ['nullable', 'string', 'max:50', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'serial' => ['nullable', 'string', 'max:191', Rule::unique('assets', 'serial')->ignore($asset?->id)],
            'name' => ['nullable', 'string', 'max:191'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'rtd_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'expected_checkin' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'checkout_to_type' => ['nullable', Rule::in(['user', 'location', 'asset'])],
            'assigned_user' => ['nullable', 'integer'],
            'assigned_location' => ['nullable', 'integer'],
            'assigned_asset' => ['nullable', 'integer'],
        ];
    }

    private function revealed(Asset $asset, Request $request): array
    {
        $user = $request->user();
        if ($user?->canViewEncrypted() && $this->fields->hasEncryptedValues($asset)) {
            ActivityLogger::log('view encrypted', $asset);
        }

        return AssetTransformer::one($asset, reveal: true);
    }

    private function changedCustomFields(?AssetModel $model, array $old, array $new): array
    {
        return $this->fields->fieldsFor($model)
            ->filter(fn ($f) => ($old[$f->db_column] ?? null) !== ($new[$f->db_column] ?? null))
            ->pluck('name')->values()->all();
    }

    private function search(Builder $query, string $search): void
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $op = $pgsql ? 'ilike' : 'like';
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        $models = AssetModel::query()->select('id')->where(fn (Builder $m) => $m
            ->where('name', $op, $term)
            ->orWhere('model_number', $op, $term)
            ->orWhereIn('manufacturer_id', Manufacturer::query()->select('id')->where('name', $op, $term))
            ->orWhereIn('category_id', Category::query()->select('id')->where('name', $op, $term)));

        $query->where(function (Builder $q) use ($op, $term, $models, $pgsql) {
            foreach (['asset_tag', 'serial', 'name', 'order_number', 'notes'] as $column) {
                $q->orWhere('assets.'.$column, $op, $term);
            }
            $q->orWhereIn('assets.model_id', $models);
            if ($pgsql) {
                $q->orWhereRaw('assets.custom_values::text ilike ?', [$term]);
            }
        });
    }
}
