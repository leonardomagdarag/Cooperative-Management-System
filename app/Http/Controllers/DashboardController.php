<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // The dashboard is rendered by the React SPA. This route is protected
        // server-side, then hands the request to the SPA shell for both initial
        // navigation and browser reloads.
        return response()->view('react');
    }
}
