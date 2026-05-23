<?php

namespace App\Traits;

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait ManagesOwnProfile
{
    public array $profileForm = [
        'title' => '',
        'first_name' => '',
        'mid_name' => '',
        'last_name' => '',
        'suffix' => '',
        'birth_date' => '',
        'birthplace' => '',
        'gender' => '',
        'education' => '',
        'civil_status' => '',
        'religion' => '',
        'email' => '',
        'phone_no' => '',
        'facebook' => '',
        'country' => '',
        'region' => '',
        'province' => '',
        'city_municipality' => '',
        'barangay' => '',
        'street' => '',
        'zip_code' => '',
    ];

    protected function ownProfileUser(): User
    {
        return Auth::user()->loadMissing([
            'personalData.address',
            'personalData.contact',
        ]);
    }

    protected function fillOwnProfileForm(): void
    {
        $user = $this->ownProfileUser();
        $personalData = $user->personalData;
        $address = $personalData?->address;
        $contact = $personalData?->contact;

        $this->profileForm = [
            'title' => (string) ($personalData?->title ?? ''),
            'first_name' => (string) ($personalData?->first_name ?? ''),
            'mid_name' => (string) ($personalData?->mid_name ?? ''),
            'last_name' => (string) ($personalData?->last_name ?? ''),
            'suffix' => (string) ($personalData?->suffix ?? ''),
            'birth_date' => $personalData?->birth_date?->format('Y-m-d') ?? '',
            'birthplace' => (string) ($personalData?->birthplace ?? ''),
            'gender' => (string) ($personalData?->gender ?? ''),
            'education' => (string) ($personalData?->education ?? ''),
            'civil_status' => (string) ($personalData?->civil_status ?? ''),
            'religion' => (string) ($personalData?->religion ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone_no' => (string) ($contact?->phone_no ?? ''),
            'facebook' => (string) ($contact?->facebook ?? ''),
            'country' => (string) ($address?->country ?? ''),
            'region' => (string) ($address?->region ?? ''),
            'province' => (string) ($address?->province ?? ''),
            'city_municipality' => (string) ($address?->city_municipality ?? ''),
            'barangay' => (string) ($address?->barangay ?? ''),
            'street' => (string) ($address?->street ?? ''),
            'zip_code' => (string) ($address?->zip_code ?? ''),
        ];
    }

    protected function saveOwnProfile(string $auditEvent, string $auditDescription): bool
    {
        $this->profileForm['email'] = User::normalizeEmail($this->profileForm['email'] ?? null);

        $user = $this->ownProfileUser();
        $validated = $this->validate($this->ownProfileRules($user));
        $data = $validated['profileForm'];

        $emailChanged = $user->email !== $data['email'];

        DB::transaction(function () use ($user, $data, $emailChanged): void {
            $personalData = $user->personalData ?: new PersonalData();
            $address = $personalData->address ?: new Address();
            $contact = $personalData->contact ?: new Contact();

            $address->fill([
                'country' => $data['country'],
                'region' => $data['region'],
                'province' => $data['province'],
                'city_municipality' => $data['city_municipality'],
                'barangay' => $data['barangay'],
                'street' => $data['street'],
                'zip_code' => $data['zip_code'],
            ]);
            $address->save();

            $contactPhoneChanged = (string) ($contact->phone_no ?? '') !== (string) $data['phone_no'];

            $contact->fill([
                'phone_no' => $data['phone_no'],
                'email' => $data['email'],
                'facebook' => $data['facebook'],
            ]);

            if ($emailChanged) {
                $contact->email_verified_at = null;
            }

            if ($contactPhoneChanged) {
                $contact->phone_verified_at = null;
            }

            $contact->save();

            $personalData->fill([
                'title' => $data['title'],
                'first_name' => $data['first_name'],
                'mid_name' => $data['mid_name'],
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'],
                'birth_date' => $data['birth_date'],
                'birthplace' => $data['birthplace'],
                'gender' => $data['gender'],
                'education' => $data['education'],
                'civil_status' => $data['civil_status'],
                'religion' => $data['religion'],
                'address_id' => $address->id,
                'contact_id' => $contact->id,
            ]);
            $personalData->save();

            $user->fill([
                'email' => $data['email'],
                'personal_data_id' => $personalData->id,
            ]);
            $user->save();
        });

        app(AuditLogService::class)->log(
            $auditEvent,
            $auditDescription,
            'profile',
            $user,
            $user,
            ['email_changed' => $emailChanged],
        );

        $this->fillOwnProfileForm();

        return $emailChanged;
    }

    protected function ownProfileRules(User $user): array
    {
        return [
            'profileForm.title' => ['nullable', 'string', 'max:100'],
            'profileForm.first_name' => ['required', 'string', 'max:100'],
            'profileForm.mid_name' => ['nullable', 'string', 'max:100'],
            'profileForm.last_name' => ['required', 'string', 'max:100'],
            'profileForm.suffix' => ['nullable', 'string', 'max:20'],
            'profileForm.birth_date' => ['nullable', 'date'],
            'profileForm.birthplace' => ['nullable', 'string', 'max:150'],
            'profileForm.gender' => ['nullable', 'string', 'max:20'],
            'profileForm.education' => ['nullable', 'string', 'max:150'],
            'profileForm.civil_status' => ['nullable', 'string', 'max:50'],
            'profileForm.religion' => ['nullable', 'string', 'max:100'],
            'profileForm.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profileForm.phone_no' => ['nullable', 'string', 'max:20'],
            'profileForm.facebook' => ['nullable', 'string', 'max:255'],
            'profileForm.country' => ['nullable', 'string', 'max:100'],
            'profileForm.region' => ['nullable', 'string', 'max:100'],
            'profileForm.province' => ['nullable', 'string', 'max:100'],
            'profileForm.city_municipality' => ['nullable', 'string', 'max:100'],
            'profileForm.barangay' => ['nullable', 'string', 'max:100'],
            'profileForm.street' => ['nullable', 'string', 'max:150'],
            'profileForm.zip_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
