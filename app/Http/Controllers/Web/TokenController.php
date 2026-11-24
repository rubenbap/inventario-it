<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TokenController extends Controller
{
    public const ACCESS = ['read' => ['read'], 'write' => ['read', 'write']];
    public const EXPIRY = ['30' => '30 dias', '90' => '90 dias', '365' => '1 ano', 'never' => 'Sem fim'];

    public function index(Request $request): View
    {
        return view('tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'newToken' => session('new_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access' => ['required', Rule::in(array_keys(self::ACCESS))],
            'expires' => ['required', Rule::in(array_keys(self::EXPIRY))],
        ]);
        $expiresAt = $data['expires'] === 'never' ? null : now()->addDays((int) $data['expires']);
        $token = $request->user()->createToken($data['name'], self::ACCESS[$data['access']], $expiresAt);
        ActivityLogger::log('create', $request->user(), null, 'Token da API "'.$data['name'].'" ('.($data['access'] === 'read' ? 'só leitura' : 'leitura e escrita').', '.self::EXPIRY[$data['expires']].')');

        return redirect()->route('tokens')->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return redirect()->route('tokens')->with('status', 'Token revogado.');
    }
}
