<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Models\Beneficiary;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->memberUsers()
            ->with(['personalData.address', 'personalData.contact', 'accounts']);

        // Search
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('user_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('personalData', function ($pd) use ($search) {
                        $pd->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('mid_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('personalData.contact', function ($c) use ($search) {
                        $c->where('phone_no', 'like', "%{$search}%");
                    });
            });
        }

        // Sort
        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'user_number', 'email', 'account_status'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->leftJoin('personal_data', 'users.personal_data_id', '=', 'personal_data.id')
                ->orderBy('personal_data.first_name', $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($u) => [
                'id' => $u->id,
                'user_number' => $u->user_number,
                'email' => $u->email,
                'account_status' => $u->account_status,
                'is_officer' => $u->is_officer,
                'primary_role' => $u->primary_role,
                'name' => trim(($u->personalData->first_name ?? '') . ' ' . ($u->personalData->last_name ?? '')),
                'phone' => $u->personalData->contact?->phone_no,
                'accounts' => $u->accounts->map(fn ($a) => ['id' => $a->id, 'type' => $a->account_type, 'balance' => $a->balance]),
                'created_at' => $u->created_at->toISOString(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load([
            'personalData.address', 'personalData.contact', 'personalData.employment',
            'personalData.memberId', 'accounts', 'loans', 'beneficiaries',
        ]);

        $pd = $user->personalData;

        return response()->json([
            'id' => $user->id,
            'user_number' => $user->user_number,
            'email' => $user->email,
            'account_status' => $user->account_status,
            'is_officer' => $user->is_officer,
            'primary_role' => $user->primary_role,
            'created_at' => $user->created_at->toISOString(),
            'personal_data' => $pd ? [
                'first_name' => $pd->first_name, 'mid_name' => $pd->mid_name,
                'last_name' => $pd->last_name, 'suffix' => $pd->suffix,
                'birth_date' => $pd->birth_date?->format('Y-m-d'), 'birthplace' => $pd->birthplace,
                'gender' => $pd->gender, 'civil_status' => $pd->civil_status,
                'religion' => $pd->religion, 'title' => $pd->title,
                'profile_picture' => $pd->profile_picture ? asset('storage/' . $pd->profile_picture) : null,
            ] : null,
            'address' => $pd?->address ? [
                'country' => $pd->address->country, 'region' => $pd->address->region,
                'province' => $pd->address->province, 'city' => $pd->address->city_municipality,
                'barangay' => $pd->address->barangay, 'street' => $pd->address->street,
                'postal_code' => $pd->address->zip_code,
            ] : null,
            'contact' => $pd?->contact ? [
                'phone_no' => $pd->contact->phone_no, 'email' => $pd->contact->email,
                'facebook' => $pd->contact->facebook,
            ] : null,
            'employment' => $pd?->employment ? [
                'employment_type' => $pd->employment->employment_type,
                'company' => $pd->employment->company, 'position' => $pd->employment->position,
                'monthly_income' => $pd->employment->monthly_income,
            ] : null,
            'member_ids' => $pd?->memberId ? [
                'national_id' => $pd->memberId->national_id, 'tin' => $pd->memberId->tin,
                'sss' => $pd->memberId->sss, 'philhealth' => $pd->memberId->philhealth,
                'barangay_id' => $pd->memberId->barangay_id,
            ] : null,
            'accounts' => $user->accounts->map(fn ($a) => [
                'id' => $a->id, 'account_number' => $a->account_number,
                'account_type' => $a->account_type, 'balance' => $a->balance,
            ]),
            'beneficiaries' => $user->beneficiaries->map(fn ($b) => [
                'id' => $b->id, 'first_name' => $b->first_name, 'last_name' => $b->last_name,
                'relationship' => $b->relationship, 'percentage' => $b->percentage ?? null,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.create'), 403);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mid_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'required|date',
            'birthplace' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other',
            'civil_status' => 'required|in:single,married,widowed,separated',
            'religion' => 'nullable|string|max:64',
            'title' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:11',
            'facebook_link' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:64',
            'tin' => 'nullable|string|max:64',
            'sss' => 'nullable|string|max:64',
            'philhealth' => 'nullable|string|max:64',
            'barangay_id' => 'nullable|string|max:64',
            'employment_type' => 'nullable|string|max:50',
            'employer_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
            'password' => ['required', 'string', 'min:8', 'max:128', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
            'password_confirmation' => 'required|string|same:password',
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.first_name' => 'required|string|max:100',
            'beneficiaries.*.last_name' => 'required|string|max:100',
            'beneficiaries.*.relationship' => 'required|string|max:50',
        ]);

        DB::transaction(function () use ($validated) {
            $contact = Contact::create([
                'phone_no' => $validated['phone_number'] ?? null,
                'email' => $validated['email'],
                'facebook' => $validated['facebook_link'] ?? null,
            ]);

            $address = null;
            if (! empty($validated['street']) || ! empty($validated['city'])) {
                $address = Address::create([
                    'country' => $validated['country'] ?? null, 'region' => $validated['region'] ?? null,
                    'province' => $validated['province'] ?? null, 'city_municipality' => $validated['city'] ?? null,
                    'barangay' => $validated['barangay'] ?? null, 'street' => $validated['street'] ?? null,
                    'zip_code' => $validated['postal_code'] ?? null,
                ]);
            }

            $employment = null;
            if (! empty($validated['employment_type'])) {
                $employment = Employment::create([
                    'employment_type' => $validated['employment_type'], 'company' => $validated['employer_name'] ?? null,
                    'position' => $validated['position'] ?? null, 'monthly_income' => $validated['monthly_income'] ?? null,
                ]);
            }

            $memberId = null;
            if (! empty($validated['national_id']) || ! empty($validated['tin'])) {
                $memberId = MemberId::create([
                    'national_id' => $validated['national_id'] ?? null, 'tin' => $validated['tin'] ?? null,
                    'sss' => $validated['sss'] ?? null, 'philhealth' => $validated['philhealth'] ?? null,
                    'barangay_id' => $validated['barangay_id'] ?? null,
                ]);
            }

            $personalData = PersonalData::create([
                'first_name' => $validated['first_name'], 'last_name' => $validated['last_name'],
                'mid_name' => $validated['mid_name'] ?? null, 'suffix' => $validated['suffix'] ?? null,
                'birth_date' => $validated['birth_date'], 'birthplace' => $validated['birthplace'] ?? null,
                'gender' => $validated['gender'], 'civil_status' => $validated['civil_status'],
                'religion' => $validated['religion'] ?? null, 'title' => $validated['title'] ?? 'Member',
                'address_id' => $address?->id, 'contact_id' => $contact->id,
                'employment_id' => $employment?->id, 'member_id' => $memberId?->id,
            ]);

            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'personal_data_id' => $personalData->id,
                'account_status' => 'active',
            ]);

            $user->assignRole('member');

            // Create default accounts
            Account::create(['user_id' => $user->id, 'account_type' => 'savings', 'balance' => 0, 'status' => 'active']);
            Account::create(['user_id' => $user->id, 'account_type' => 'share-capital', 'balance' => 0, 'status' => 'active']);

            // Create beneficiaries
            if (! empty($validated['beneficiaries'])) {
                foreach ($validated['beneficiaries'] as $b) {
                    Beneficiary::create([
                        'user_id' => $user->id, 'first_name' => $b['first_name'],
                        'last_name' => $b['last_name'], 'relationship' => $b['relationship'],
                    ]);
                }
            }

            app(AuditLogService::class)->log('member.created', 'New member created.', 'member', $user, Auth::user());
        });

        return response()->json(['message' => 'Member created successfully.'], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.update'), 403);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mid_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'civil_status' => 'nullable|in:single,married,widowed,separated',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => 'nullable|string|max:11',
            'facebook_link' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        DB::transaction(function () use ($user, $validated) {
            $pd = $user->personalData;

            if ($pd?->contact) {
                $pd->contact->update(['phone_no' => $validated['phone_number'] ?? null, 'email' => $validated['email'], 'facebook' => $validated['facebook_link'] ?? null]);
            }

            if ($pd) {
                $pd->update([
                    'first_name' => $validated['first_name'], 'last_name' => $validated['last_name'],
                    'mid_name' => $validated['mid_name'] ?? null, 'suffix' => $validated['suffix'] ?? null,
                    'birth_date' => $validated['birth_date'] ?? null, 'gender' => $validated['gender'] ?? null,
                    'civil_status' => $validated['civil_status'] ?? null,
                ]);
            }

            $updateData = ['email' => $validated['email']];
            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }
            $user->update($updateData);

            app(AuditLogService::class)->log('member.updated', 'Member profile updated.', 'member', $user, Auth::user());
        });

        return response()->json(['message' => 'Member updated successfully.']);
    }

    public function destroy(User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.delete'), 403);

        app(AuditLogService::class)->log('member.deleted', 'Member deleted.', 'member', $user, Auth::user());
        $user->delete();

        return response()->json(['message' => 'Member deleted.']);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();
        if ($search === '') return response()->json(['data' => []]);

        $members = User::query()->memberUsers()->with('personalData')
            ->where(function ($q) use ($search) {
                $q->where('user_number', 'like', "%{$search}%")
                    ->orWhereHas('personalData', fn ($pd) => $pd->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            })
            ->limit(10)->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => trim(($m->personalData->first_name ?? '') . ' ' . ($m->personalData->last_name ?? '')), 'user_number' => $m->user_number]);

        return response()->json(['data' => $members]);
    }
}
