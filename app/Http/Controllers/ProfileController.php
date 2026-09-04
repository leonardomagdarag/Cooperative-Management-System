<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService
    ) {}

    public function edit(): Response
    {
        return response('Profile', 200);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user()->loadMissing('personalData.contact');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $email = User::normalizeEmail($validated['email']);
        [$firstName, $lastName] = $this->splitName($validated['name']);
        $previousEmail = (string) $user->email;
        $previousName = trim(implode(' ', array_filter([
            (string) ($user->personalData?->first_name ?? ''),
            (string) ($user->personalData?->last_name ?? ''),
        ])));
        $emailChanged = $previousEmail !== $email;
        $nameChanged = $previousName !== trim($validated['name']);

        DB::transaction(function () use ($user, $email, $firstName, $lastName, $emailChanged): void {
            $personalData = $user->personalData ?? new PersonalData;
            $contact = $personalData->contact ?? new Contact;

            $contact->email = $email;
            if ($emailChanged) {
                $contact->email_verified_at = null;
            }
            $contact->save();

            $personalData->fill([
                'title' => $personalData->title ?: 'Member',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'contact_id' => $contact->id,
            ]);
            $personalData->save();

            $user->fill([
                'personal_data_id' => $personalData->id,
                'email' => $email,
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        if ($emailChanged) {
            $this->notificationService->sendEmailVerification($user);
        }

        $this->auditLogService->log(
            'profile.updated',
            'User updated profile details.',
            'profile',
            $user,
            $user,
            [
                'email_changed' => $emailChanged,
                'name_changed' => $nameChanged,
            ]
        );

        return redirect('/profile');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $this->auditLogService->log(
            'profile.deleted',
            'User deleted own profile account.',
            'profile',
            $user,
            $user,
            [
                'email' => $user->email,
            ]
        );

        Auth::logout();
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = array_shift($parts) ?? 'User';
        $lastName = trim(implode(' ', $parts));

        if ($lastName === '') {
            $lastName = $firstName;
        }

        return [$firstName, $lastName];
    }
}
