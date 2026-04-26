<?php

namespace App\Http\Controllers;

use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    public function news()
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

    public function register()
    {
        return view('public/register');
    }

    public function submitContactMessage(Request $request, TurnstileService $turnstileService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
            'cf-turnstile-response' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($turnstileService, $request) {
                    if (!$turnstileService->verify((string) $value, $request->ip())) {
                        $fail('Captcha verification failed. Please try again.');
                    }
                },
            ],
        ]);

        $recipient = config('mail.from.address');

        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput()->with('error', 'Contact message could not be sent right now. Please try again later.');
        }

        try {
            Mail::raw(
                "New contact message from website\n\n"
                    . "Name: {$validated['name']}\n"
                    . "Email: {$validated['email']}\n\n"
                    . "Message:\n{$validated['message']}",
                function ($mail) use ($recipient, $validated): void {
                    $mail->to($recipient)
                        ->replyTo($validated['email'], $validated['name'])
                        ->subject('Website Contact Message');
                }
            );

            return redirect()
                ->to(route('home_page') . '#Contact')
                ->with('success', 'Your message has been sent successfully. We will get back to you soon.');
        } catch (\Throwable $e) {
            Log::error('Contact message sending failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Failed to send your message at the moment. Please try again later.');
        }
    }
}
