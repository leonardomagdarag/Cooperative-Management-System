<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Mail\ContactMessageMail;
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

    public function forgotPassword()
    {
        return $this->serveReactApp();
    }

    public function resetPassword()
    {
        return $this->serveReactApp();
    }

    public function submitContactMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
        ]);

        SendEmailJob::dispatch(
            config('mail.from.address'),
            new ContactMessageMail($validated['name'], $validated['email'], $validated['message'])
        );

        return response()->json(['message' => 'Message sent successfully.']);
    }
}
