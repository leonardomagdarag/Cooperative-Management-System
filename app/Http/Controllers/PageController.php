<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function home()
    {
        return view('public/home');
    }

    public function about()
    {
        return view('public/about');
    }

    public function events()
    {
        return view('public/events');
    }

    public function stories()
    {
        return view('public/stories');
    }

    public function login()
    {
        return view('public/login');
    }
}
