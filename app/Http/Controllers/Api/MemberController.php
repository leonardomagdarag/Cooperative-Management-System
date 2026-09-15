<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailJob;
use App\Mail\MemberWelcomeMail;
use App\Models\Account;
use App\Models\Address;
use App\Models\Beneficiary;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Support\BranchScope;
use App\Support\PersonPasswordPolicy;
use App\Support\SearchFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.view'), 403);

        $query = User::query()
            ->with(['branch', 'personalData.address', 'personalData.contact', 'accounts', 'memberApplication']);

        if ($request->boolean('is_officer')) {
            $query->officerUsers();
        } else {
            $query->memberUsers();
        }

        // Branch filter: admins (and unrestricted users) may narrow by a
        // specific branch; branch-assigned officers are auto-scoped.
        BranchScope::applyToUsers($query, Auth::user(), $request->input('branch_id'));

        // Status filter (pending = inactive self-registered applicants awaiting review;
        // archived = soft-deleted accounts)
        $status = $request->string('status')->toString();
        if (in_array($status, ['all', 'active', 'inactive', 'pending', 'terminated', 'archived'], true)) {
            if ($status === 'archived') {
                $query->onlyTrashed();
            } elseif ($status === 'pending') {
                $query->where('account_status', 'inactive')
                    ->whereHas('memberApplication', fn ($q) => $q->where('status', 'pending'));
            } elseif ($status !== 'all') {
                $query->where('account_status', $status);
            }
        }

        // Search
        if ($search = $request->string('search')->toString()) {
            $search = SearchFilter::escapeLike($search);
            $tokens = array_filter(preg_split('/\s+/', $search));
            $query->where(function ($q) use ($search, $tokens) {
                $q->where('user_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('personalData', function ($pd) use ($search, $tokens) {
                        $pd->where(function ($sub) use ($search) {
                            $sub->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('mid_name', 'like', "%{$search}%");
                        });
                        if (count($tokens) > 1) {
                            $pd->orWhere(function ($tq) use ($tokens) {
                                foreach ($tokens as $token) {
                                    $tq->where(function ($sub) use ($token) {
                                        $sub->where('first_name', 'like', "%{$token}%")
                                            ->orWhere('last_name', 'like', "%{$token}%")
                                            ->orWhere('mid_name', 'like', "%{$token}%");
                                    });
                                }
                            });
                        }
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
                'name' => trim(($u->personalData->first_name ?? '').' '.($u->personalData->last_name ?? '')),
                'title' => $u->personalData?->title,
                'phone' => $u->personalData->contact?->phone_no,
                'branch_id' => $u->branch_id,
                'branch_name' => $u->branch?->name,
                'accounts' => $u->accounts->map(fn ($a) => ['id' => $a->id, 'type' => $a->account_type, 'balance' => $a->balance]),
                'has_pending_application' => (bool) ($u?->memberApplication?->status === 'pending'),
                'created_at' => $u->created_at->toISOString(),
                'archived_at' => $u->deleted_at?->toISOString(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'pending_count' => BranchScope::applyToUsers(
                User::query()
                    ->memberUsers()
                    ->where('account_status', 'inactive')
                    ->whereHas('memberApplication', fn ($q) => $q->where('status', 'pending')),
                Auth::user(),
                $request->input('branch_id')
            )->count(),
        ]);
    }

    public function show(int $user): JsonResponse
    {
        $viewer = Auth::user();
        abort_unless($viewer?->can('members.view') || $viewer?->is($user), 403);

        if ($viewer && ! BranchScope::isUnrestricted($viewer)) {
            $target = User::withTrashed()->find($user);
            abort_unless($target && (int) $target->branch_id === (int) $viewer->branch_id, 403);
        }

        $user = User::withTrashed()
            ->with([
                'branch',
                'personalData.address', 'personalData.contact', 'personalData.employment',
                'personalData.memberId', 'accounts', 'loans', 'beneficiaries', 'memberApplication',
            ])
            ->findOrFail($user);

        $pd = $user->personalData;

        $recentLogs = Activity::query()
            ->where(function ($q) use ($user) {
                $q->where(fn ($q) => $q->where('causer_type', $user::class)->where('causer_id', $user->id))
                    ->orWhere(fn ($q) => $q->where('subject_type', $user::class)->where('subject_id', $user->id));
            })
            ->latest()
            ->take(8)
            ->get();

        return response()->json([
            'id' => $user->id,
            'user_number' => $user->user_number,
            'email' => $user->email,
            'account_status' => $user->account_status,
            'is_officer' => $user->is_officer,
            'primary_role' => $user->primary_role,
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch?->name,
            'special_loan_eligible' => (bool) $user->special_loan_eligible,
            'created_at' => $user->created_at->toISOString(),
            'archived_at' => $user->deleted_at?->toISOString(),
            'has_pending_application' => (bool) ($user->memberApplication?->status === 'pending'),
            'personal_data' => $pd ? [
                'first_name' => $pd->first_name, 'mid_name' => $pd->mid_name,
                'last_name' => $pd->last_name, 'suffix' => $pd->suffix,
                'birth_date' => $pd->birth_date?->format('Y-m-d'), 'birthplace' => $pd->birthplace,
                'gender' => $pd->gender, 'civil_status' => $pd->civil_status,
                'religion' => $pd->religion, 'title' => $pd->title,
                'education' => $pd->education,
                'profile_picture' => $pd->profile_picture ? asset('storage/'.$pd->profile_picture) : null,
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
                'other_income_source' => $pd->employment->other_income_source,
                'other_income_amount' => $pd->employment->other_income_amount,
            ] : null,
            'member_ids' => $pd?->memberId ? [
                'national_id' => $pd->memberId->national_id, 'tin' => $pd->memberId->tin,
                'sss' => $pd->memberId->sss, 'philhealth' => $pd->memberId->philhealth,
                'barangay_id' => $pd->memberId->barangay_id,
                'national_id_photo' => $pd->memberId->national_id_photo ? asset('storage/'.$pd->memberId->national_id_photo) : null,
                'tin_photo' => $pd->memberId->tin_photo ? asset('storage/'.$pd->memberId->tin_photo) : null,
                'sss_photo' => $pd->memberId->sss_photo ? asset('storage/'.$pd->memberId->sss_photo) : null,
                'philhealth_photo' => $pd->memberId->philhealth_photo ? asset('storage/'.$pd->memberId->philhealth_photo) : null,
                'barangay_id_photo' => $pd->memberId->barangay_id_photo ? asset('storage/'.$pd->memberId->barangay_id_photo) : null,
                'agreement_url' => $pd->memberId->proof_of_registration_path ? asset('storage/'.$pd->memberId->proof_of_registration_path) : null,
                'other_document' => $pd->memberId->other_document_path ? asset('storage/'.$pd->memberId->other_document_path) : null,
            ] : null,
            'accounts' => $user->accounts->map(fn ($a) => [
                'id' => $a->id, 'account_number' => $a->account_number,
                'account_type' => $a->account_type, 'balance' => $a->balance,
            ]),
            'beneficiaries' => $user->beneficiaries->map(fn ($b) => [
                'id' => $b->id, 'first_name' => $b->first_name, 'mid_name' => $b->mid_name,
                'last_name' => $b->last_name, 'relationship' => $b->relationship,
                'birth_date' => $b->birth_date?->toDateString(),
                'phone_no' => $b->phone_no,
                'street' => $b->street, 'barangay' => $b->barangay, 'city' => $b->city,
                'province' => $b->province, 'postal_code' => $b->postal_code,
            ]),
            'loans' => $user->loans->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type,
                'loan_status' => $l->loan_status,
                'loan_amount' => $l->loan_amount,
                'interest_rate' => $l->interest_rate,
                'term_months' => $l->term_months,
                'payment_frequency' => $l->payment_frequency,
                'applied_at' => $l->applied_at?->toISOString(),
                'approved_at' => $l->approved_at?->toISOString(),
                'due_date' => $l->due_date?->toDateString(),
                'remaining_balance' => $l->remainingBalance(),
            ]),
            'recent_logs' => $recentLogs->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'created_at' => $log->created_at->toISOString(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.create'), 403);

        $this->assertCanAssignRole($request->input('role'), 'officers.create');

        $this->normalizeBeneficiaries($request);

        $minimumAge = max((int) config('settings.requirement.age', 18), 18);
        $oldestAllowedBirthDate = now()->subYears($minimumAge)->toDateString();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mid_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'required|date|before:today|before_or_equal:'.$oldestAllowedBirthDate,
            'birthplace' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other',
            'civil_status' => 'required|in:single,married,widowed,separated',
            'religion' => 'nullable|string|max:64',
            'title' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email|unique:contacts,email',
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
            'password' => PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens([
                $request->input('first_name'),
                $request->input('mid_name'),
                $request->input('last_name'),
            ])),
            'password_confirmation' => 'required|string|same:password',
            'role' => 'nullable|string|in:admin,manager,accountant,secretary,loan',
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.first_name' => 'required|string|max:100',
            'beneficiaries.*.last_name' => 'required|string|max:100',
            'beneficiaries.*.relationship' => 'required|string|max:50',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'national_id_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'tin_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'sss_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'philhealth_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'barangay_id_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'agreement_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $profilePhotoPath = $request->file('profile_photo')?->store('profile-photos', 'public');
        $idPhotoPaths = [];
        foreach (['national_id_photo', 'tin_photo', 'sss_photo', 'philhealth_photo', 'barangay_id_photo'] as $field) {
            $idPhotoPaths[$field] = $request->file($field)?->store('id-photos', 'public');
        }

        $user = DB::transaction(function () use ($validated, $request, $profilePhotoPath, $idPhotoPaths) {
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
            if (! empty($validated['national_id']) || ! empty($validated['tin'])
                || collect($idPhotoPaths)->filter()->isNotEmpty()) {
                $memberId = MemberId::create([
                    'national_id' => $validated['national_id'] ?? null, 'tin' => $validated['tin'] ?? null,
                    'sss' => $validated['sss'] ?? null, 'philhealth' => $validated['philhealth'] ?? null,
                    'barangay_id' => $validated['barangay_id'] ?? null,
                    'national_id_photo' => $idPhotoPaths['national_id_photo'] ?? null,
                    'tin_photo' => $idPhotoPaths['tin_photo'] ?? null,
                    'sss_photo' => $idPhotoPaths['sss_photo'] ?? null,
                    'philhealth_photo' => $idPhotoPaths['philhealth_photo'] ?? null,
                    'barangay_id_photo' => $idPhotoPaths['barangay_id_photo'] ?? null,
                ]);
            }

            $personalData = PersonalData::create([
                'first_name' => $validated['first_name'], 'last_name' => $validated['last_name'],
                'mid_name' => $validated['mid_name'] ?? null, 'suffix' => $validated['suffix'] ?? null,
                'birth_date' => $validated['birth_date'], 'birthplace' => $validated['birthplace'] ?? null,
                'gender' => $validated['gender'], 'civil_status' => $validated['civil_status'],
                'religion' => $validated['religion'] ?? null, 'title' => $validated['title'] ?? 'Member',
                'profile_picture' => $profilePhotoPath,
                'address_id' => $address?->id, 'contact_id' => $contact->id,
                'employment_id' => $employment?->id, 'member_id' => $memberId?->id,
            ]);

            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'personal_data_id' => $personalData->id,
                'branch_id' => $this->resolveMemberBranch($validated),
                'account_status' => 'active',
                'profile_completion_required' => false,
            ]);

            if ($request->file('agreement_file')) {
                $agreementPath = $request->file('agreement_file')->store('member-agreements/'.$user->id, 'public');
                if ($memberId) {
                    $memberId->update(['proof_of_registration_path' => $agreementPath]);
                }
            }

            $user->assignRole('member');

            if (! empty($validated['role'])) {
                $user->syncRoles([$validated['role']]);
                $user->update(['is_officer' => true]);
            }

            // Create default accounts
            Account::create(['user_id' => $user->id, 'account_type' => 'savings', 'balance' => 0]);
            Account::create(['user_id' => $user->id, 'account_type' => 'share-capital', 'balance' => 0]);

            // Create beneficiaries
            if (! empty($validated['beneficiaries'])) {
                foreach ($validated['beneficiaries'] as $b) {
                    Beneficiary::create([
                        'user_id' => $user->id, 'first_name' => $b['first_name'],
                        'last_name' => $b['last_name'], 'relationship' => $b['relationship'],
                    ]);
                }
            }

            $isOfficer = ! empty($validated['role']);

            app(AuditLogService::class)->log(
                $isOfficer ? 'officer.created' : 'member.created',
                $isOfficer ? 'New officer created.' : 'New member created.',
                $isOfficer ? 'officer' : 'member',
                $user,
                Auth::user()
            );

            return $user;
        });

        app(NotificationService::class)->sendEmailVerification($user);

        if (config('mail.default') !== 'log') {
            $name = trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? ''));
            SendEmailJob::dispatch(
                $validated['email'],
                new MemberWelcomeMail($name, $validated['email'], url('/login')),
                'member.welcome'
            );
        }

        return response()->json(['message' => 'Member created successfully.'], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.update'), 403);

        $this->assertCanAssignRole($request->input('role'), 'officers.update');

        $this->normalizeBeneficiaries($request);

        $minimumAge = max((int) config('settings.requirement.age', 18), 18);
        $oldestAllowedBirthDate = now()->subYears($minimumAge)->toDateString();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'mid_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date|before:today|before_or_equal:'.$oldestAllowedBirthDate,
            'birthplace' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'civil_status' => 'nullable|in:single,married,widowed,separated',
            'religion' => 'nullable|string|max:64',
            'title' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => 'nullable|string|max:11',
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
            'role' => 'nullable|string|in:admin,manager,accountant,secretary,loan',
            'password' => PersonPasswordPolicy::passwordRules(true, PersonPasswordPolicy::normalizedNameTokens([
                $request->input('first_name'),
                $request->input('mid_name'),
                $request->input('last_name'),
            ])),
            'otp_code' => ['nullable', 'required_with:password', 'string', 'digits:6'],
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.first_name' => 'required|string|max:100',
            'beneficiaries.*.last_name' => 'required|string|max:100',
            'beneficiaries.*.relationship' => 'required|string|max:50',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'national_id_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'tin_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'sss_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'philhealth_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'barangay_id_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'agreement_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if (! empty($validated['password'])) {
            $actor = Auth::user();
            $securityOtp = app(SecurityOtpService::class);
            $verified = $securityOtp->verifyChallenge(
                $actor,
                $securityOtp->memberPasswordChangePurpose(),
                $validated['otp_code'],
                ['member_id' => $user->id],
            );

            if (! $verified) {
                return response()->json(['message' => 'Invalid or expired verification code.'], 422);
            }
        }

        $profilePhotoPath = null;
        if ($request->file('profile_photo')) {
            if ($user->personalData?->profile_picture) {
                Storage::disk('public')->delete($user->personalData->profile_picture);
            }
            $profilePhotoPath = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $idPhotoFields = ['national_id_photo', 'tin_photo', 'sss_photo', 'philhealth_photo', 'barangay_id_photo'];
        $idPhotoPaths = [];
        foreach ($idPhotoFields as $field) {
            if ($request->file($field)) {
                $idPhotoPaths[$field] = $request->file($field)->store('id-photos', 'public');
            }
        }

        $agreementPath = null;
        if ($request->file('agreement_file')) {
            $agreementPath = $request->file('agreement_file')->store('member-agreements/'.$user->id, 'public');
        }

        DB::transaction(function () use ($user, $validated, $profilePhotoPath, $idPhotoPaths, $agreementPath) {
            $pd = $user->personalData;

            if ($pd?->contact) {
                $pd->contact->update([
                    'phone_no' => $validated['phone_number'] ?? null,
                    'email' => $validated['email'],
                    'facebook' => $validated['facebook_link'] ?? null,
                ]);
            }

            $addressId = $pd?->address_id;
            if ($pd?->address) {
                $pd->address->update($this->addressData($validated));
            } elseif ($this->hasAnyAddressField($validated)) {
                $addressId = Address::create($this->addressData($validated))->id;
            }

            $employmentId = $pd?->employment_id;
            if ($pd?->employment) {
                $pd->employment->update($this->employmentData($validated));
            } elseif (! empty($validated['employment_type'])) {
                $employmentId = Employment::create($this->employmentData($validated))->id;
            }

            $memberIdId = $pd?->member_id;
            if ($pd?->memberId) {
                foreach ($idPhotoPaths as $field => $path) {
                    $old = $pd->memberId->{$field};
                    if ($old) {
                        Storage::disk('public')->delete($old);
                    }
                }
                $pd->memberId->update(array_merge($this->memberIdTextData($validated), $idPhotoPaths));
            } elseif ($this->hasAnyMemberIdField($validated, $idPhotoPaths)) {
                $memberIdId = MemberId::create(array_merge($this->memberIdTextData($validated), $idPhotoPaths))->id;
            }

            if ($agreementPath && $memberIdId) {
                MemberId::whereKey($memberIdId)->update(['proof_of_registration_path' => $agreementPath]);
            }

            if ($pd) {
                $updateData = array_merge([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'mid_name' => $validated['mid_name'] ?? null,
                    'suffix' => $validated['suffix'] ?? null,
                    'birth_date' => $validated['birth_date'] ?? null,
                    'birthplace' => $validated['birthplace'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'civil_status' => $validated['civil_status'] ?? null,
                    'religion' => $validated['religion'] ?? null,
                    'title' => $validated['title'] ?? 'Member',
                    'address_id' => $addressId,
                    'employment_id' => $employmentId,
                    'member_id' => $memberIdId,
                ], $profilePhotoPath ? ['profile_picture' => $profilePhotoPath] : []);

                $pd->update($updateData);
            }

            $userData = ['email' => $validated['email']];
            if (array_key_exists('branch_id', $validated)) {
                $userData['branch_id'] = $this->resolveMemberBranch($validated);
            }
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            if (! empty($validated['role'])) {
                $userData['role'] = $validated['role'];
                $userData['is_officer'] = true;
                $user->syncRoles([$validated['role']]);
            }
            $user->update($userData);

            if (array_key_exists('beneficiaries', $validated)) {
                $user->beneficiaries()->delete();
                foreach ($validated['beneficiaries'] as $b) {
                    $user->beneficiaries()->create([
                        'first_name' => $b['first_name'],
                        'last_name' => $b['last_name'],
                        'relationship' => $b['relationship'],
                    ]);
                }
            }

            $isOfficer = ! empty($validated['role']);

            app(AuditLogService::class)->log(
                $isOfficer ? 'officer.updated' : 'member.updated',
                $isOfficer ? 'Officer profile updated.' : 'Member profile updated.',
                $isOfficer ? 'officer' : 'member',
                $user,
                Auth::user()
            );
        });

        $emailChanged = $user->email !== $validated['email'];
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            app(NotificationService::class)->sendEmailVerification($user->refresh());
        }

        return response()->json(['message' => 'Member updated successfully.']);
    }

    public function sendPasswordChangeOtp(User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.update'), 403);

        $actor = Auth::user();
        $securityOtp = app(SecurityOtpService::class);
        $challenge = $securityOtp->issueChallenge(
            $actor,
            $securityOtp->memberPasswordChangePurpose(),
            ['member_id' => $user->id],
        );
        $securityOtp->sendOtpMail($actor, $securityOtp->memberPasswordChangePurpose(), $challenge['otp_code']);

        return response()->json([
            'message' => 'A verification code was sent to your email.',
            'expires_at' => $challenge['challenge']->expires_at->toISOString(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.delete'), 403);

        $isOfficer = (bool) ($user->is_officer ?? false);

        app(AuditLogService::class)->log(
            $isOfficer ? 'officer.deleted' : 'member.deleted',
            $isOfficer ? 'Officer removed.' : 'Member deleted.',
            $isOfficer ? 'officer' : 'member',
            $user,
            Auth::user()
        );
        $user->delete();

        return response()->json(['message' => 'Member deleted.']);
    }

    public function restore(int $user): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $member = User::onlyTrashed()->with(['memberApplication', 'personalData.contact'])->findOrFail($user);

        DB::transaction(function () use ($member) {
            if (str_starts_with((string) $member->user_number, 'ARCH-')) {
                $number = $this->nextUserNumber();
                $member->user_number = $number;
            }

            // Undo the email scramble from a declined application so the
            // member keeps their original address after being restored.
            $originalEmail = $member->memberApplication?->original_email;
            if ($originalEmail) {
                $taken = User::where('email', User::normalizeEmail($originalEmail))
                    ->where('id', '!=', $member->id)
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'email' => 'The member\'s original email is already in use by another account.',
                    ]);
                }

                $member->email = $originalEmail;

                $contact = $member->personalData?->contact;
                if ($contact && str_starts_with((string) $contact->email, 'archived_')) {
                    $contact->email = $originalEmail;
                    $contact->save();
                }
            }

            $member->restore();

            app(AuditLogService::class)->log(
                'member.restored',
                'Archived member restored.',
                'member',
                $member,
                Auth::user()
            );
        });

        return response()->json(['message' => 'Member restored.']);
    }

    public function forceDestroy(int $user): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $member = User::onlyTrashed()->with('personalData')->findOrFail($user);

        DB::transaction(function () use ($member) {
            $member->roles()->detach();

            $member->accounts()->delete();
            $member->loans()->delete();
            $member->beneficiaries()->delete();
            $member->memberApplication?->delete();

            $pd = $member->personalData;
            if ($pd) {
                if ($pd->profile_picture) {
                    Storage::disk('public')->delete($pd->profile_picture);
                }

                if ($pd->memberId) {
                    foreach ([
                        $pd->memberId->national_id_photo,
                        $pd->memberId->tin_photo,
                        $pd->memberId->sss_photo,
                        $pd->memberId->philhealth_photo,
                        $pd->memberId->barangay_id_photo,
                        $pd->memberId->proof_of_registration_path,
                        $pd->memberId->other_document_path,
                    ] as $photoPath) {
                        if ($photoPath) {
                            Storage::disk('public')->delete($photoPath);
                        }
                    }
                }

                Storage::disk('public')->deleteDirectory('member-agreements/'.$member->id);

                // Detach the snapshot rows first so their deletions are FK-safe.
                $pd->update([
                    'address_id' => null,
                    'contact_id' => null,
                    'employment_id' => null,
                    'member_id' => null,
                ]);

                $pd->address?->delete();
                $pd->contact?->delete();
                $pd->employment?->delete();
                $pd->memberId?->delete();
            }

            app(AuditLogService::class)->log(
                'member.permanently-deleted',
                'Member permanently deleted.',
                'member',
                $member,
                Auth::user()
            );

            $member->forceDelete();

            $pd?->forceDelete();
        });

        return response()->json(['message' => 'Member permanently deleted.']);
    }

    private function nextUserNumber(): string
    {
        $latest = User::withTrashed()
            ->orderByRaw('CAST(SUBSTRING(user_number, 4) AS UNSIGNED) DESC')
            ->first();

        if ($latest && preg_match('/USR(\d+)/', (string) $latest->user_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'USR'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function approve(User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.approve'), 403);

        if ($user->account_status === 'active') {
            return response()->json(['message' => 'Member is already active.'], 422);
        }

        $application = $user->memberApplication()->where('status', 'pending')->first();

        DB::transaction(function () use ($user, $application) {
            if ($application) {
                $application->update([
                    'status' => 'approved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            }

            $user->update([
                'account_status' => 'active',
                'branch_id' => $application?->branch_id ?? $user->branch_id,
            ]);

            if (! $user->accounts()->where('account_type', 'savings')->exists()) {
                Account::create(['user_id' => $user->id, 'account_type' => 'savings', 'balance' => 500.00]);
            }

            if (! $user->accounts()->where('account_type', 'share-capital')->exists()) {
                Account::create(['user_id' => $user->id, 'account_type' => 'share-capital', 'balance' => 500.00]);
            }

            app(AuditLogService::class)->log(
                'member.approved',
                'Membership application approved.',
                'member',
                $user,
                Auth::user()
            );
        });

        app(NotificationService::class)->sendMemberActivationEmail($user);

        if (! $user->email_verified_at) {
            // Public-registered members are approved here, but the verification
            // link is only mailed on creation for admin-created members. Send it
            // now so the `verified` gate after OTP does not strand the member.
            app(NotificationService::class)->sendEmailVerification($user->refresh());
        }

        return response()->json(['message' => 'Application approved and activation email sent.']);
    }

    public function decline(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.approve'), 403);

        if ($user->account_status === 'active') {
            return response()->json(['message' => 'Member is already active.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $application = $user->memberApplication()->where('status', 'pending')->first();
        if (! $application) {
            return response()->json(['message' => 'No pending application found for this member.'], 422);
        }

        $originalEmail = $application->original_email ?: $user->email;
        $applicantName = trim(($user->personalData->first_name ?? '').' '.($user->personalData->last_name ?? ''));
        $reason = trim((string) $validated['reason']);

        DB::transaction(function () use ($application, $user, $reason) {
            $application->update([
                'status' => 'declined',
                'decline_reason' => $reason ?: null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            // Archive the account: soft-delete + release unique fields so the
            // applicant can re-register with the same email later.
            $user->update([
                'email' => $this->scrambleArchivedEmail($user, $user->email),
                'user_number' => 'ARCH-'.$user->user_number,
            ]);

            $contact = $user->personalData?->contact;
            if ($contact) {
                $contact->update([
                    'email' => $this->scrambleArchivedEmail($user, $contact->email),
                ]);
            }

            $user->account_status = 'inactive';
            $user->save();

            $user->delete();

            app(AuditLogService::class)->log(
                'member.declined',
                'Membership application declined.',
                'member',
                $user,
                Auth::user(),
                ['reason' => $reason]
            );
        });

        app(NotificationService::class)->sendMemberDeclineEmail($originalEmail, $applicantName, $reason);

        return response()->json(['message' => 'Application declined and applicant notified.']);
    }

    public function toggleSpecialLoanEligibility(User $user): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.update'), 403);

        $user->update([
            'special_loan_eligible' => ! ($user->special_loan_eligible ?? false),
        ]);

        $user->refresh();

        app(AuditLogService::class)->log(
            'member.special-loan-eligibility',
            $user->special_loan_eligible
                ? 'Special loan eligibility enabled.'
                : 'Special loan eligibility disabled.',
            'member',
            $user,
            Auth::user()
        );

        return response()->json([
            'special_loan_eligible' => (bool) $user->special_loan_eligible,
        ]);
    }

    private function scrambleArchivedEmail(User $user, ?string $email): ?string
    {
        if (! $email) {
            return $email;
        }

        return 'archived_'.$user->id.'_'.strtolower(trim($email));
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.view'), 403);

        $search = SearchFilter::escapeLike($request->string('q')->toString());
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $tokens = array_filter(preg_split('/\s+/', $search));

        $members = User::query()->memberUsers()->with('personalData')
            ->where(function ($q) use ($search, $tokens) {
                $q->where('user_number', 'like', "%{$search}%")
                    ->orWhereHas('personalData', function ($pd) use ($search, $tokens) {
                        $pd->where(function ($sub) use ($search) {
                            $sub->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('mid_name', 'like', "%{$search}%");
                        });
                        if (count($tokens) > 1) {
                            $pd->orWhere(function ($tq) use ($tokens) {
                                foreach ($tokens as $token) {
                                    $tq->where(function ($sub) use ($token) {
                                        $sub->where('first_name', 'like', "%{$token}%")
                                            ->orWhere('last_name', 'like', "%{$token}%")
                                            ->orWhere('mid_name', 'like', "%{$token}%");
                                    });
                                }
                            });
                        }
                    });
            })
            ->limit(10)->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => trim(($m->personalData->first_name ?? '').' '.($m->personalData->last_name ?? '')), 'user_number' => $m->user_number, 'branch_id' => $m->branch_id, 'branch_name' => $m->branch?->name]);

        return response()->json(['data' => $members]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()?->can('members.view'), 403);

        $validated = $request->validate([
            'export_type' => ['required', 'in:pdf,excel'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $members = User::query()
            ->memberUsers()
            ->with(['branch', 'personalData.address', 'personalData.contact'])
            ->whereBetween('users.created_at', [$start, $end]);

        BranchScope::applyToUsers($members, Auth::user(), $request->input('branch_id'));

        $members = $members->orderByDesc('created_at')->get();

        return $validated['export_type'] === 'pdf'
            ? $this->downloadMembersPdf($members, $start, $end)
            : $this->downloadMembersExcel($members, $start, $end);
    }

    private function downloadMembersPdf($members, Carbon $start, Carbon $end)
    {
        $pdf = Pdf::loadView('exports.members-pdf', [
            'members' => $members,
            'start' => $start,
            'end' => $end,
            'generatedAt' => now(),
            'generatedBy' => Auth::user(),
            'appName' => config('app.name', 'Cooperative Management System'),
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $this->exportFilename('pdf', $start, $end), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function downloadMembersExcel($members, Carbon $start, Carbon $end)
    {
        $xml = $this->buildMembersSpreadsheetXml($members);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $this->exportFilename('xls', $start, $end), [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function exportFilename(string $extension, Carbon $start, Carbon $end): string
    {
        return 'members-'.$start->format('Y-m-d').'-to-'.$end->format('Y-m-d').'.'.$extension;
    }

    private function buildMembersSpreadsheetXml($members): string
    {
        $headers = [
            'Member ID',
            'Full Name',
            'Email',
            'Phone Number',
            'Status',
            'Gender',
            'Civil Status',
            'Address',
            'Join Date',
        ];

        $rows = $members->map(function (User $member) {
            $address = $member->personalData?->address;

            return [
                $member->user_number,
                $this->memberFullName($member),
                $member->email,
                $member->personalData?->contact?->phone_no ?? '',
                ucfirst((string) ($member->account_status ?? 'active')),
                $member->personalData?->gender ?? '',
                $member->personalData?->civil_status ?? '',
                $this->memberAddress($address),
                optional($member->created_at)->format('M d, Y') ?? '',
            ];
        });

        $xmlRows = collect([$headers])
            ->merge($rows)
            ->map(function ($row) {
                $cells = collect($row)->map(function ($value) {
                    $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');

                    return '<Cell><Data ss:Type="String">'.$escaped.'</Data></Cell>';
                })->implode('');

                return '<Row>'.$cells.'</Row>';
            })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'
            .'<Worksheet ss:Name="Members"><Table>'
            .$xmlRows
            .'</Table></Worksheet></Workbook>';
    }

    private function memberFullName(User $member): string
    {
        return trim(implode(' ', array_filter([
            $member->personalData?->first_name,
            $member->personalData?->mid_name,
            $member->personalData?->last_name,
            $member->personalData?->suffix,
        ])));
    }

    private function memberAddress(?Address $address): string
    {
        if (! $address) {
            return '';
        }

        return implode(', ', array_filter([
            $address->street,
            $address->barangay,
            $address->city_municipality,
            $address->province,
            $address->region,
            $address->zip_code,
        ]));
    }

    /**
     * The member wizard serializes beneficiaries as a JSON string when sent
     * as multipart form data. Normalize to an array so validation works in
     * both the JSON and multipart paths.
     */
    /**
     * Prevent privilege escalation: only an admin may create/update admins,
     * and only users with officer-management permissions may assign officer
     * roles to a member account.
     */
    private function assertCanAssignRole(mixed $role, string $permission): void
    {
        if (empty($role)) {
            return;
        }

        if ($role === 'admin' && ! Auth::user()?->hasRole('admin')) {
            abort(403, 'Only administrators can manage admin accounts.');
        }

        if (! Auth::user()?->can($permission)) {
            abort(403, 'You do not have permission to assign officer roles.');
        }
    }

    /**
     * Branch-scoped officers cannot place members in another branch.
     */
    private function resolveMemberBranch(array $validated): ?int
    {
        $viewer = Auth::user();

        if (BranchScope::isUnrestricted($viewer)) {
            return isset($validated['branch_id']) && $validated['branch_id'] !== null && $validated['branch_id'] !== ''
                ? (int) $validated['branch_id']
                : null;
        }

        return $viewer?->branch_id;
    }

    private function normalizeBeneficiaries(Request $request): void
    {
        $beneficiaries = $request->input('beneficiaries');

        if (is_string($beneficiaries)) {
            $decoded = json_decode($beneficiaries, true);
            $request->merge(['beneficiaries' => is_array($decoded) ? $decoded : []]);
        }
    }

    private function addressData(array $validated): array
    {
        return [
            'country' => $validated['country'] ?? null,
            'region' => $validated['region'] ?? null,
            'province' => $validated['province'] ?? null,
            'city_municipality' => $validated['city'] ?? null,
            'barangay' => $validated['barangay'] ?? null,
            'street' => $validated['street'] ?? null,
            'zip_code' => $validated['postal_code'] ?? null,
        ];
    }

    private function hasAnyAddressField(array $validated): bool
    {
        foreach (['country', 'region', 'province', 'city', 'barangay', 'street', 'postal_code'] as $field) {
            if (! empty($validated[$field])) {
                return true;
            }
        }

        return false;
    }

    private function employmentData(array $validated): array
    {
        return [
            'employment_type' => $validated['employment_type'] ?? null,
            'company' => $validated['employer_name'] ?? null,
            'position' => $validated['position'] ?? null,
            'monthly_income' => $validated['monthly_income'] ?? null,
        ];
    }

    private function memberIdTextData(array $validated): array
    {
        return [
            'national_id' => $validated['national_id'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'sss' => $validated['sss'] ?? null,
            'philhealth' => $validated['philhealth'] ?? null,
            'barangay_id' => $validated['barangay_id'] ?? null,
        ];
    }

    private function hasAnyMemberIdField(array $validated, array $idPhotoPaths): bool
    {
        foreach (['national_id', 'tin', 'sss', 'philhealth', 'barangay_id'] as $field) {
            if (! empty($validated[$field])) {
                return true;
            }
        }

        return $idPhotoPaths !== [];
    }
}
