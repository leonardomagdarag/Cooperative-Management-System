<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficerController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        // add the name of the admin to the user
        $first_name = optional($user->personalData)->first_name;
        $last_name = optional($user->personalData)->last_name;
        $user->name = trim($first_name . ' ' . $last_name);

        // Render the officer dashboard view
        //using default dashboard for now
        return view('dashboard', compact('user'));
    }
}
