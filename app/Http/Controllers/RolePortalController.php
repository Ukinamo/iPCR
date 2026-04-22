<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolePortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->forget('login_portal_role');

        return Inertia::render('Auth/SelectRole');
    }
}
