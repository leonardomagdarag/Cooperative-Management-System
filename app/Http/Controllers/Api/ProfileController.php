<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Announcement;
use App\Models\Beneficiary;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $user = Auth::user()->load([
            'personalData.address',
            'personalData.contact',
            'personalData.employment',
            'personalData.memberId',
            'accounts',
            'loans.payments',
            'beneficiaries',
            'trustedDevices',
        ]);

        $accounts = $user->accounts;
        $totalBalance = $accounts->sum('balance');
        $loans = $user->loans()->latest()->take(10)->get();
        $activeLoans = $user->loans()->whereIn('loan_status', ['approved', 'disbursed', 'active'])->count();

        $totalLoanBalance = $user->loans()
            ->whereIn('loan_status', ['approved', 'active'])
            ->get()
            ->sum(fn ($l) => $l->remainingBalance());

        $savingsAccount = $accounts->where('account_type', 'savings')->first();
        $shareCapitalAccount = $accounts->where('account_type', 'share-capital')->first();

        $announcements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'created_at' => $a->created_at->toISOString(),
                'created_at_diff' => $a->created_at->diffForHumans(),
                'creator_name' => trim(
                    ($a->creator->personalData->first_name ?? '').' '.
                    ($a->creator->personalData->last_name ?? '')
                ) ?: 'Unknown',
            ]);
        $recentLogs = Activity::query()
            ->where('causer_type', $user::class)
            ->where('causer_id', $user->id)
            ->latest()
            ->take(8)
            ->get();
        $trustedDevices = $user->trustedDevices()->latest()->take(5)->get();

        $personalData = $user->personalData;
        $address = $personalData?->address;
        $contact = $personalData?->contact;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'user_number' => $user->user_number,
                'email' => $user->email,
                'primary_role' => $user->primary_role,
                'is_officer' => $user->is_officer,
                'account_status' => $user->account_status,
                'profile_completion_required' => $user->requiresProfileCompletion(),
                'created_at' => $user->created_at->toISOString(),
            ],
            'personal_data' => $personalData ? [
                'title' => $personalData->title,
                'first_name' => $personalData->first_name,
                'mid_name' => $personalData->mid_name,
                'last_name' => $personalData->last_name,
                'suffix' => $personalData->suffix,
                'birth_date' => $personalData->birth_date?->format('Y-m-d'),
                'birthplace' => $personalData->birthplace,
                'gender' => $personalData->gender,
                'education' => $personalData->education,
                'civil_status' => $personalData->civil_status,
                'religion' => $personalData->religion,
                'profile_picture' => $personalData->profile_picture ? asset('storage/'.$personalData->profile_picture) : null,
            ] : null,
            'address' => $address ? [
                'country' => $address->country,
                'region' => $address->region,
                'province' => $address->province,
                'city_municipality' => $address->city_municipality,
                'barangay' => $address->barangay,
                'street' => $address->street,
                'zip_code' => $address->zip_code,
            ] : null,
            'contact' => $contact ? [
                'phone_no' => $contact->phone_no,
                'email' => $contact->email,
                'facebook' => $contact->facebook,
            ] : null,
            'employment' => $personalData?->employment ? [
                'employment_type' => $personalData->employment->employment_type,
                'company' => $personalData->employment->company,
                'position' => $personalData->employment->position,
                'monthly_income' => $personalData->employment->monthly_income,
                'other_income_source' => $personalData->employment->other_income_source,
                'other_income_amount' => $personalData->employment->other_income_amount,
            ] : null,
            'member_id' => $personalData?->memberId ? [
                'national_id' => $personalData->memberId->national_id,
                'tin' => $personalData->memberId->tin,
                'sss' => $personalData->memberId->sss,
                'philhealth' => $personalData->memberId->philhealth,
                'barangay_id' => $personalData->memberId->barangay_id,
            ] : null,
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'account_number' => $a->account_number,
                'account_type' => $a->account_type,
                'balance' => $a->balance,
                'status' => $a->status,
            ]),
            'total_balance' => $totalBalance,
            'loans' => $loans->map(function ($l) {
                $totalPaid = $l->payments->sum('amount');
                $progress = $l->loan_amount > 0 ? round(($totalPaid / $l->loan_amount) * 100, 1) : 0;

                return [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'loan_type' => $l->loan_type,
                    'principal_amount' => $l->principal_amount ?? $l->loan_amount,
                    'loan_amount' => $l->loan_amount,
                    'loan_status' => $l->loan_status,
                    'remaining_balance' => $l->remainingBalance(),
                    'total_paid' => $totalPaid,
                    'progress' => $progress,
                    'due_date' => $l->due_date?->format('M d, Y'),
                    'due_date_raw' => $l->due_date?->toISOString(),
                    'interest_rate' => (float) ($l->interest_rate ?? 0),
                    'term_months' => (int) ($l->term_months ?? 0),
                ];
            }),
            'total_loan_balance' => $totalLoanBalance,
            'savings_account' => $savingsAccount ? [
                'id' => $savingsAccount->id,
                'account_number' => $savingsAccount->account_number,
                'balance' => $savingsAccount->balance,
            ] : null,
            'share_capital_account' => $shareCapitalAccount ? [
                'id' => $shareCapitalAccount->id,
                'account_number' => $shareCapitalAccount->account_number,
                'balance' => $shareCapitalAccount->balance,
            ] : null,
            'announcements' => $announcements,
            'active_loans_count' => $activeLoans,
            'beneficiaries' => $user->beneficiaries->map(fn ($b) => [
                'id' => $b->id,
                'name' => trim(($b->first_name ?? '').' '.($b->last_name ?? '')),
                'first_name' => $b->first_name,
                'last_name' => $b->last_name,
                'relationship' => $b->relationship,
                'percentage' => $b->percentage ?? null,
            ]),
            'recent_logs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'created_at' => $log->created_at->toISOString(),
            ]),
            'trusted_devices' => $trustedDevices->map(fn ($d) => [
                'id' => $d->id,
                'device_label' => $d->device_label,
                'last_ip_address' => $d->last_ip_address,
                'last_used_at' => $d->last_used_at?->toISOString(),
                'is_active' => $d->is_active,
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = Auth::user()->loadMissing([
            'personalData.address',
            'personalData.contact',
        ]);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'mid_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'education' => ['nullable', 'string', 'max:150'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_otp_code' => ['nullable', 'string', 'digits:6'],
            'phone_no' => ['nullable', 'string', 'max:20'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city_municipality' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'tin' => ['nullable', 'string', 'max:64'],
            'sss' => ['nullable', 'string', 'max:64'],
            'philhealth' => ['nullable', 'string', 'max:64'],
            'barangay_id' => ['nullable', 'string', 'max:64'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'employer_name' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'beneficiaries' => ['nullable', 'array', 'max:5'],
            'beneficiaries.*.first_name' => ['required', 'string', 'max:100'],
            'beneficiaries.*.last_name' => ['required', 'string', 'max:100'],
            'beneficiaries.*.relationship' => ['required', 'string', 'max:50'],
        ]);

        $emailChanged = Str::lower(trim($user->email)) !== Str::lower(trim($validated['email']));

        if ($emailChanged) {
            $securityOtp = app(SecurityOtpService::class);
            $verified = ! empty($validated['email_otp_code'])
                && $securityOtp->verifyChallenge(
                    $user,
                    $securityOtp->emailChangePurpose(),
                    $validated['email_otp_code'],
                    $this->emailChangeContext($validated['email']),
                );

            if (! $verified) {
                return response()->json([
                    'message' => 'A valid verification code sent to your current email is required to change it.',
                    'errors' => ['email_otp_code' => ['Invalid or expired verification code.']],
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $user, $validated, $emailChanged) {
            $personalData = $user->personalData ?: new PersonalData;
            $address = $personalData->address ?: new Address;
            $contact = $personalData->contact ?: new Contact;

            $address->fill([
                'country' => $validated['country'] ?? null,
                'region' => $validated['region'] ?? null,
                'province' => $validated['province'] ?? null,
                'city_municipality' => $validated['city_municipality'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'street' => $validated['street'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
            ]);
            $address->save();

            $contactPhoneChanged = (string) ($contact->phone_no ?? '') !== (string) ($validated['phone_no'] ?? '');

            $contact->fill([
                'phone_no' => $validated['phone_no'] ?? null,
                'email' => $validated['email'],
                'facebook' => $validated['facebook'] ?? null,
            ]);

            if ($emailChanged) {
                $contact->email_verified_at = null;
            }
            if ($contactPhoneChanged) {
                $contact->phone_verified_at = null;
            }
            $contact->save();

            $personalData->fill([
                'title' => ! empty($validated['title']) ? $validated['title'] : ($personalData->title ?: 'Member'),
                'first_name' => $validated['first_name'],
                'mid_name' => $validated['mid_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'birthplace' => $validated['birthplace'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'education' => $validated['education'] ?? null,
                'civil_status' => $validated['civil_status'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'address_id' => $address->id,
                'contact_id' => $contact->id,
            ]);
            $personalData->save();

            $employment = $personalData->employment;
            if (! empty($validated['employment_type'])) {
                if ($employment) {
                    $employment->update([
                        'employment_type' => $validated['employment_type'],
                        'company' => $validated['employer_name'] ?? null,
                        'position' => $validated['position'] ?? null,
                        'monthly_income' => $validated['monthly_income'] ?? null,
                    ]);
                } else {
                    $employment = Employment::create([
                        'employment_type' => $validated['employment_type'],
                        'company' => $validated['employer_name'] ?? null,
                        'position' => $validated['position'] ?? null,
                        'monthly_income' => $validated['monthly_income'] ?? null,
                    ]);
                    $personalData->setAttribute('employment_id', $employment->id);
                }
            }

            $hasGovtId = ! empty($validated['national_id']) || ! empty($validated['tin'])
                || ! empty($validated['sss']) || ! empty($validated['philhealth'])
                || ! empty($validated['barangay_id']);

            $memberId = $personalData->memberId;
            if ($hasGovtId) {
                $memberIdData = [
                    'national_id' => $validated['national_id'] ?? null,
                    'tin' => $validated['tin'] ?? null,
                    'sss' => $validated['sss'] ?? null,
                    'philhealth' => $validated['philhealth'] ?? null,
                    'barangay_id' => $validated['barangay_id'] ?? null,
                ];
                if ($memberId) {
                    $memberId->update($memberIdData);
                } else {
                    $memberId = MemberId::create($memberIdData);
                    $personalData->setAttribute('member_id', $memberId->id);
                }
            }

            $personalData->save();

            $user->fill([
                'email' => $validated['email'],
                'personal_data_id' => $personalData->id,
            ]);
            if ($emailChanged) {
                $user->forceFill(['email_verified_at' => null]);
            }
            $user->save();

            if (array_key_exists('beneficiaries', $validated)) {
                $user->beneficiaries()->delete();
                foreach ($validated['beneficiaries'] as $b) {
                    Beneficiary::create([
                        'user_id' => $user->id,
                        'first_name' => $b['first_name'],
                        'last_name' => $b['last_name'],
                        'relationship' => $b['relationship'],
                    ]);
                }
            }

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                if ($personalData->profile_picture) {
                    Storage::disk('public')->delete($personalData->profile_picture);
                }
                $personalData->update([
                    'profile_picture' => $request->file('profile_photo')->store('profile-photos', 'public'),
                ]);
            }
        });

        app(AuditLogService::class)->log(
            'member.profile.update',
            'Member profile updated from settings.',
            'profile',
            $user,
            $user,
            ['email_changed' => $emailChanged],
        );

        $this->clearProfileCompletionIfComplete($user);

        if ($emailChanged) {
            app(NotificationService::class)->sendEmailVerification($user);
        }

        return response()->json(['message' => 'Profile updated successfully.']);
    }

    /**
     * Once the user's profile no longer has any missing required sections,
     * clear the first-login profile-completion requirement.
     */
    private function clearProfileCompletionIfComplete(User $user): void
    {
        if (! $user->requiresProfileCompletion()) {
            return;
        }

        $user->loadMissing([
            'personalData.address',
            'personalData.contact',
            'personalData.memberId',
            'beneficiaries',
        ]);

        $pd = $user->personalData;
        if (! $pd) {
            return;
        }

        $memberId = $pd->memberId;
        $hasGovtId = $memberId && (filled($memberId->national_id) || filled($memberId->tin)
            || filled($memberId->sss) || filled($memberId->philhealth) || filled($memberId->barangay_id));

        $complete = filled($pd->civil_status)
            && filled($pd->religion)
            && filled($pd->birthplace)
            && filled($pd->address?->street)
            && filled($pd->address?->city_municipality)
            && filled($pd->contact?->phone_no)
            && $hasGovtId
            && $user->beneficiaries()->count() > 0;

        if ($complete) {
            $user->update([
                'profile_completion_required' => false,
                'profile_completed_at' => now(),
            ]);
        }
    }

    public function completion(): JsonResponse
    {
        $user = Auth::user()->loadMissing([
            'personalData.address',
            'personalData.contact',
            'personalData.memberId',
            'beneficiaries',
        ]);

        $pd = $user->personalData;
        $missing = [];

        if (! $pd) {
            $missing = ['personal', 'address', 'contact', 'ids', 'employment', 'beneficiaries', 'agreement'];
        } else {
            if (empty($pd->civil_status) || empty($pd->religion) || empty($pd->birthplace)) {
                $missing[] = 'personal';
            }

            if (empty($pd->address?->street) || empty($pd->address?->city_municipality)) {
                $missing[] = 'address';
            }

            if (empty($pd->contact?->phone_no) || empty($pd->contact?->email)) {
                $missing[] = 'contact';
            }

            $memberId = $pd->memberId;
            if (! $memberId || (empty($memberId->national_id) && empty($memberId->tin)
                && empty($memberId->sss) && empty($memberId->philhealth) && empty($memberId->barangay_id))) {
                $missing[] = 'ids';
            }

            if (empty($pd->employment?->employment_type)) {
                $missing[] = 'employment';
            }

            if ($user->beneficiaries()->count() === 0) {
                $missing[] = 'beneficiaries';
            }
        }

        return response()->json([
            'required' => $user->requiresProfileCompletion(),
            'missing' => $missing,
        ]);
    }

    public function sendEmailChangeOtp(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (Str::lower(trim($user->email)) === Str::lower(trim($validated['email']))) {
            return response()->json(['message' => 'Enter a new email address before requesting a verification code.'], 422);
        }

        $securityOtp = app(SecurityOtpService::class);
        $challenge = $securityOtp->issueChallenge(
            $user,
            $securityOtp->emailChangePurpose(),
            $this->emailChangeContext($validated['email']),
        );
        $securityOtp->sendOtpMail($user, $securityOtp->emailChangePurpose(), $challenge['otp_code']);

        return response()->json([
            'message' => 'A verification code was sent to your current email address.',
            'expires_at' => $challenge['challenge']->expires_at->toISOString(),
        ]);
    }

    private function emailChangeContext(string $email): array
    {
        return ['email' => Str::lower(trim($email))];
    }
}
