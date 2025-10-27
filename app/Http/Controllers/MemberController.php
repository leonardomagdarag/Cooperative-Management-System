<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/login');
        }

        // Pass data to the view
        return view('dashboard', compact('user'));
    }
}
