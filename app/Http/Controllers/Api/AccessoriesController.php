<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\Transformers;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccessoriesController extends ApiController
{
    private function query(): Builder
    {
        return Accessory::query()
            ->with(['company', 'category', 'manufacturer', 'supplier', 'location'])
            ->withCount('openCheckouts');
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->query();
        foreach (['company_id', 'category_id', 'manufacturer_id', 'supplier_id', 'location_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }

        return $this->listing(
            $query,
            $request,
            fn (Accessory $a) => Transformers::accessory($a),
            ['name', 'model_number', 'order_number', 'notes', 'category.name', 'manufacturer.name'],
            ['id', 'name', 'qty', 'min_amt', 'purchase_date', 'purchase_cost', 'created_at', 'updated_at'],
            'name',
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Transformers::accessory($this->query()->findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(null));
        $data['company_id'] = $this->resolveCompanyId($data['company_id'] ?? null, $request->user());
        $accessory = new Accessory($data);
        $accessory->created_by = $request->user()->id;
        $accessory->save();
        ActivityLogger::log('create', $accessory);

        return ApiResponse::success(Transformers::accessory($this->query()->findOrFail($accessory->id)), 'Acessório criado com sucesso.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $accessory = $this->query()->findOrFail($id);
        $data = $request->validate($this->rules($accessory));
        if (isset($data['company_id'])) {
            $this->resolveCompanyId((int) $data['company_id'], $request->user());
        }
        if (isset($data['qty']) && $data['qty'] < $accessory->open_checkouts_count) {
            throw ValidationException::withMessages(['qty' => ['A quantidade não pode ser menor do que as unidades entregues.']]);
        }
        $accessory->fill($data)->save();
        if ($changes = ActivityLogger::diff($accessory)) {
            ActivityLogger::log('update', $accessory, null, null, $changes);
        }

        return ApiResponse::success(Transformers::accessory($this->query()->findOrFail($id)), 'Acessório atualizado com sucesso.');
    }

    public function destroy(int $id): JsonResponse
    {
        $accessory = $this->query()->findOrFail($id);
        if ($accessory->open_checkouts_count > 0) {
            return ApiResponse::error('Há unidades entregues. Recebe-as antes de apagar.');
        }
        $accessory->delete();
        ActivityLogger::log('delete', $accessory);

        return ApiResponse::success(null, 'Acessório apagado com sucesso.');
    }

    public function checkedout(int $id): JsonResponse
    {
        $accessory = $this->query()->findOrFail($id);
        $rows = $accessory->openCheckouts()->with('user')->latest()->get();

        return ApiResponse::rows($rows->count(), $rows->map(fn ($c) => Transformers::accessoryCheckout($c))->all());
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $accessory = $this->query()->findOrFail($id);
        $data = $request->validate([
            'assigned_to' => ['required_without:assigned_user', 'nullable', 'integer'],
            'assigned_user' => ['required_without:assigned_to', 'nullable', 'integer'],
            'checkout_qty' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'note' => ['nullable', 'string', 'max:65535'],
        ]);
        $qty = (int) ($data['checkout_qty'] ?? 1);
        $user = User::query()->find($data['assigned_to'] ?? $data['assigned_user']);

        if (! $user || ! $user->activated) {
            throw ValidationException::withMessages(['assigned_to' => ['Utilizador inexistente ou inativo.']]);
        }
        if ($user->company_id && (int) $user->company_id !== (int) $accessory->company_id
            && config('inventario.enforce_same_company_checkout') && ! $request->user()->isAdmin()) {
            throw ValidationException::withMessages(['assigned_to' => ['O utilizador é de outra empresa.']]);
        }

        DB::transaction(function () use ($accessory, $user, $qty, $data, $request) {
            $locked = Accessory::query()->whereKey($accessory->id)->lockForUpdate()->firstOrFail();
            if ($locked->numRemaining() < $qty) {
                throw ValidationException::withMessages(['checkout_qty' => ['Não há unidades suficientes disponíveis.']]);
            }
            $now = now();
            AccessoryCheckout::insert(array_fill(0, $qty, [
                'accessory_id' => $accessory->id,
                'assigned_to' => $user->id,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            ActivityLogger::log('checkout', $accessory, $user, $data['note'] ?? null, $qty > 1 ? ['qty' => $qty] : null);
        });

        return ApiResponse::success(['accessory' => $accessory->id], 'Acessório entregue com sucesso.');
    }

    public function checkin(Request $request, int $checkoutId): JsonResponse
    {
        $checkout = AccessoryCheckout::query()->whereNull('checked_in_at')->findOrFail($checkoutId);
        $accessory = $this->query()->findOrFail($checkout->accessory_id);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:65535']])['note'] ?? null;

        $checkout->checked_in_at = now();
        $checkout->save();
        ActivityLogger::log('checkin from', $accessory, $checkout->user, $note);

        return ApiResponse::success(['accessory' => $accessory->id], 'Acessório recebido com sucesso.');
    }

    private function rules(?Accessory $accessory): array
    {
        $s = $accessory ? 'sometimes' : 'required';

        return [
            'name' => [$s, 'string', 'max:191'],
            'category_id' => [$s, 'integer', Rule::exists('categories', 'id')->where('category_type', 'accessory')],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'model_number' => ['nullable', 'string', 'max:191'],
            'qty' => [$s, 'integer', 'min:0'],
            'min_amt' => ['nullable', 'integer', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'order_number' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
