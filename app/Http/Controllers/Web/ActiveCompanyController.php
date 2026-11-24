<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveCompanyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $id = $request->integer('company_id');

        if ($id && $request->user()->hasCompany($id)) {
            $request->session()->put('company_id', $id);
        } else {
            $request->session()->forget('company_id');
        }

        return back();
    }
}
