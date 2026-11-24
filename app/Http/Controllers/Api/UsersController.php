<?php

namespace App\Http\Controllers\Api;

use App\Http\Support\ApiResponse;
use App\Http\Transformers\AssetTransformer;
use App\Http\Transformers\Transformers;
use App\Models\Asset;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsersController extends ApiController
{
    public function __construct(private readonly UserAccess $access) {}

    private function query(Request $request): Builder
    {
        $query = User::query()
            ->with(['company', 'department', 'location', 'manager'])
            ->withCount(['assets', 'accessoryCheckouts' => fn ($q) => $q->whereNull('checked_in_at')]);

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);
        foreach (['company_id', 'department_id', 'location_id', 'manager_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }
        if ($request->has('activated')) {
            $query->where('activated', $request->boolean('activated'));
        }

        return $this->listing(
            $query,
            $request,
            fn (User $u) => Transformers::user($u),
            ['first_name', 'last_name', 'email', 'username', 'employee_num', 'jobtitle'],
            ['id', 'first_name', 'last_name', 'email', 'username', 'employee_num', 'created_at', 'updated_at'],
            'first_name',
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(Transformers::user($this->query($request)->findOrFail($id)));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(Transformers::user($this->query($request)->findOrFail($request->user()->id)));
    }

    public function store(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $data = $request->validate($this->rules(null));
        $this->guardAdminFields($data, $viewer);
        if (! empty($data['can_login']) && empty($data['password'])) {
            throw ValidationException::withMessages(['password' => ['Um utilizador com login precisa de password.']]);
        }
        $data['company_id'] = $this->resolveCompanyId($data['company_id'] ?? null, $viewer, false);

        $user = User::create($data);
        ActivityLogger::log('create', $user);

        return ApiResponse::success(Transformers::user($this->query($request)->findOrFail($user->id)), 'Utilizador criado com sucesso.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $viewer = $request->user();
        $user = $this->query($request)->findOrFail($id);
        $data = $request->validate($this->rules($user));
        $this->guardAdminFields($data, $viewer);
        $this->access->assertCanChange($user, $data, $viewer);
        if (array_key_exists('company_id', $data)) {
            $data['company_id'] = $this->resolveCompanyId($data['company_id'], $viewer, false);
        }
        if (array_key_exists('activated', $data) && ! $data['activated'] && $this->hasItems($user)) {
            throw ValidationException::withMessages(['activated' => ['Este utilizador ainda tem equipamentos atribuídos.']]);
        }

        $user->fill(collect($data)->except('password')->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $this->access->afterChange($user);

        $changes = collect(ActivityLogger::diff($user))->except(['password', 'remember_token'])->all();
        if ($changes) {
            ActivityLogger::log('update', $user, null, null, $changes);
        }

        return ApiResponse::success(Transformers::user($this->query($request)->findOrFail($id)), 'Utilizador atualizado com sucesso.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->query($request)->findOrFail($id);
        $this->access->assertCanDelete($user, $request->user());
        if ($this->hasItems($user)) {
            return ApiResponse::error('Este utilizador ainda tem equipamentos atribuídos. Faz check-in primeiro.');
        }
        $user->tokens()->delete();
        $user->delete();
        ActivityLogger::log('delete', $user);

        return ApiResponse::success(null, 'Utilizador apagado com sucesso.');
    }

    public function assets(Request $request, int $id): JsonResponse
    {
        $user = $this->query($request)->findOrFail($id);
        $rows = Asset::with(AssetTransformer::WITH)
            ->where('assigned_type', User::class)->where('assigned_id', $user->id)->get();

        return ApiResponse::rows($rows->count(), $rows->map(fn ($a) => AssetTransformer::one($a))->all());
    }

    public function accessories(Request $request, int $id): JsonResponse
    {
        $user = $this->query($request)->findOrFail($id);
        $rows = $user->accessoryCheckouts()->whereNull('checked_in_at')
            ->whereHas('accessory')->with('accessory.category')->get();

        return ApiResponse::rows($rows->count(), $rows->map(fn ($c) => [
            'id' => $c->accessory->id,
            'name' => e($c->accessory->name),
            'category' => $c->accessory->category ? ['id' => $c->accessory->category->id, 'name' => e($c->accessory->category->name)] : null,
            'checkout_id' => $c->id,
            'note' => $c->note ? e($c->note) : null,
        ])->all());
    }

    private function rules(?User $user): array
    {
        $s = $user ? 'sometimes' : 'required';

        return [
            'first_name' => [$s, 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:191', Rule::unique('users', 'username')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:10', 'max:191'],
            'employee_num' => ['nullable', 'string', 'max:50'],
            'jobtitle' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'activated' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],

            'can_login' => ['sometimes', 'boolean'],
            'role' => ['nullable', Rule::in(User::ROLES)],
        ];
    }

    private function guardAdminFields(array $data, User $viewer): void
    {
        if ($viewer->isAdmin()) {
            return;
        }
        foreach (['can_login', 'role', 'password'] as $field) {
            if (array_key_exists($field, $data)) {
                throw ValidationException::withMessages([$field => ['Só um admin pode alterar o login e as permissões.']]);
            }
        }
    }

    private function hasItems(User $user): bool
    {
        return Asset::withoutGlobalScopes()->where('assigned_type', User::class)->where('assigned_id', $user->id)->exists()
            || $user->accessoryCheckouts()->whereNull('checked_in_at')->exists();
    }
}
