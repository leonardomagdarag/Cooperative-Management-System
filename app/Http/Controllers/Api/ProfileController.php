<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'loans',
            'beneficiaries',
            'trustedDevices',
        ]);

        $accounts = $user->accounts;
        $totalBalance = $accounts->sum('balance');
        $loans = $user->loans()->latest()->take(5)->get();
        $activeLoans = $user->loans()->whereIn('loan_status', ['approved', 'disbursed', 'active'])->count();
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
                'profile_picture' => $personalData->profile_picture ? asset('storage/' . $personalData->profile_picture) : null,
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
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'account_number' => $a->account_number,
                'account_type' => $a->account_type,
                'balance' => $a->balance,
                'status' => $a->status,
            ]),
            'total_balance' => $totalBalance,
            'loans' => $loans->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type,
                'principal_amount' => $l->principal_amount,
                'loan_status' => $l->loan_status,
            ]),
            'active_loans_count' => $activeLoans,
            'beneficiaries' => $user->beneficiaries->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'relationship' => $b->relationship,
                'percentage' => $b->percentage,
            ]),
            'recent_logs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'created_at' => $log->created_at->toISOString(),
            ]),
            'trusted_devices' => $trustedDevices->map(fn ($d) => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'ip_address' => $d->ip_address,
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
        ]);

        $emailChanged = $user->email !== $validated['email'];

        DB::transaction(function () use ($request, $user, $validated, $emailChanged) {
            $personalData = $user->personalData ?: new PersonalData();
            $address = $personalData->address ?: new Address();
            $contact = $personalData->contact ?: new Contact();

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
                'title' => $validated['title'] ?? null,
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

            $user->fill([
                'email' => $validated['email'],
                'personal_data_id' => $personalData->id,
            ]);
            $user->save();

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

        return response()->json(['message' => 'Profile updated successfully.']);
    }
}
