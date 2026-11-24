<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function __construct(private readonly UserAccess $access) {}

    public function index(Request $request): View
    {
        $role = $request->input('role');

        return view('access.index', [
            'users' => User::query()->with('company:id,code')
                ->where('can_login', true)
                ->when(in_array($role, User::ROLES, true), fn ($q) => $q->where('role', $role))
                ->orderByRaw("case role when 'admin' then 0 else 1 end")
                ->orderBy('first_name')->get(),
            'role' => $role,
        ]);
    }

    public function create(): View
    {
        return view('access.form', ['user' => new User(['role' => User::ROLE_ADMIN])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(null));

        $invite = empty($data['password']);
        $user = DB::transaction(function () use ($data, $invite) {
            $user = User::create([
                ...$data,
                'password' => $invite ? Str::random(40) : $data['password'],
                'can_login' => true,
                'activated' => true,
            ]);
            ActivityLogger::log('create', $user, null, 'Login: '.User::ROLE_LABELS[$data['role']]);

            return $user;
        });

        if ($invite) {
            Password::broker()->sendResetLink(['email' => $user->email]);

            return redirect()->route('access.index')->with('status', "Login criado. {$user->name} recebeu um e-mail em {$user->email} para definir a password.");
        }

        return redirect()->route('access.index')->with('status', "Login criado. {$user->name} já pode entrar com {$user->email}.");
    }

    public function sendLink(int $id): RedirectResponse
    {
        $user = User::where('can_login', true)->findOrFail($id);
        Password::broker()->sendResetLink(['email' => $user->email]);
        ActivityLogger::log('update', $user, null, 'Enviado link de nova password');

        return back()->with('status', "Link de nova password enviado para {$user->email}.");
    }

    public function edit(int $id): View
    {
        return view('access.form', ['user' => User::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $data = $request->validate($this->rules($user));
        $data['can_login'] = $request->boolean('can_login');
        $data['activated'] = $request->boolean('activated');
        $this->access->assertCanChange($user, $data, $request->user());

        DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->except('password')->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $this->access->afterChange($user);

            $changes = collect(ActivityLogger::diff($user))->except(['password', 'remember_token'])->all();
            if (! empty($data['password'])) {
                $changes['password'] = ['old' => null, 'new' => 'alterada'];
            }
            if ($changes) {
                ActivityLogger::log('update', $user, null, 'Acesso alterado', $changes);
            }
        });

        return redirect()->route('access.index')->with('status', 'Acesso atualizado.');
    }

    private function rules(?User $user): array
    {
        return [
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:10', 'max:191', 'confirmed'],
            'role' => ['required', Rule::in(User::ROLES)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
