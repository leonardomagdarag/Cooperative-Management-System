<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageController extends Controller
{
    /**
     * Serve the React SPA for all public pages.
     * The React app handles client-side routing.
     */
    private function serveReactApp(): Response
    {
        return response()->view('react');
    }

    public function home()
    {
        return $this->serveReactApp();
    }

    public function about()
    {
        return $this->serveReactApp();
    }

    public function news()
    {
        return $this->serveReactApp();
    }

    public function stories()
    {
        return $this->serveReactApp();
    }

    public function login()
    {
        return $this->serveReactApp();
    }

    public function register()
    {
        return $this->serveReactApp();
    }

    public function submitContactMessage(Request $request): JsonResponse
    {
        // This is handled by the API route now
        return response()->json(['message' => 'Please use the API endpoint.'], 404);
    }
}
