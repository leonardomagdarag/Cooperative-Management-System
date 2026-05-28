<?php

namespace App\Livewire\Content;

use App\Models\Account;
use App\Models\Address;
use App\Models\Beneficiary;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Support\PersonPasswordPolicy;
use App\Models\User;
use App\Services\AuditLogService;
use App\Traits\ValidatesPersonForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Members Component
 *
 * Manages member CRUD operations with full personal data, contact, and address management.
 * Includes search, sorting, and pagination functionality.
 */
class Members extends Component
{
    use WithPagination, ValidatesPersonForm, WithFileUploads;

    // Search and filtering
    public $search = '';

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    // Row selection (for future batch operations)
    public $selectedRows = [];

    public $selectAll = false;

    // Modal states
    public $showModal = false;

    public $selectedMember = null;

    public $showEditModal = false;

    public $showExportModal = false;

    public $isEdit = false;

    public $activeTab = 'overview';

    // Form data array
    public $form = [];

    public $editingMemberId = null;

    public $modalKey = 0;

    public $profileModalKey = 0;

    public $photoUpload = null;

    public $nationalIdPhoto = null;
    public $tinPhoto = null;
    public $sssPhoto = null;
    public $philhealthPhoto = null;
    public $barangayIdPhoto = null;

    public $agreementUpload = null;
    public string $exportType = 'pdf';

    public ?string $exportStartDate = null;

    public ?string $exportEndDate = null;

    /**
     * Per-tab validation field map for the member form.
     */
    private const TAB_FIELDS = [
        'personal' => [
            'form.first_name',
            'form.mid_name',
            'form.last_name',
            'form.suffix',
            'form.birth_date',
            'form.birthplace',
            'form.gender',
            'form.civil_status',
            'form.religion',
            'form.title',
        ],
        'address' => [
            'form.country',
            'form.region',
            'form.province',
            'form.city',
            'form.barangay',
            'form.street',
            'form.postal_code',
        ],
        'contact' => [
            'form.email',
            'form.phone_number',
            'form.mobile_number',
            'form.emergency_contact',
            'form.facebook_link',
        ],
        'ids' => [
            'form.national_id',
            'form.tin',
            'form.sss',
            'form.philhealth',
            'form.barangay_id',
        ],
        'employment' => [
            'form.employment_type',
            'form.employer_name',
            'form.position',
            'form.monthly_income',
            'form.other_income_source',
            'form.other_income_amount',
        ],
        'login' => [
            'form.current_password',
            'form.password',
            'form.password_confirmation',
        ],
        'agreement' => [
            'form.terms_agreed',
        ],
    ];

    // Track tab validation errors
    public $tabErrors = [
        'personal' => false,
        'address' => false,
        'contact' => false,
        'ids' => false,
        'employment' => false,
        'login' => false,
        'agreement' => false,
    ];

    public function mount(): void
    {
        $this->resetExportFilters();
    }

    /**
     * Convert empty string to null for numeric fields
     */
    private function emptyToNull($value)
    {
        return $value === '' || $value === null ? null : $value;
    }

    /**
     * Minimum member age from configuration.
     */
    private function minimumAgeRequirement(): int
    {
        return max(0, (int) config('settings.requirement.age', 18));
    }

    /**
     * Sequential step order used by the add-member wizard.
     * Returns the tab name that follows $currentTab, or null if $currentTab is the last step.
     */
    private const WIZARD_STEPS = ['personal', 'address', 'contact', 'ids', 'employment', 'login', 'agreement', 'review'];

    /**
     * Called by the Next button in add-mode only.
     * Validates the current tab server-side; on success dispatches the next tab name
     * to Alpine via a browser event so the client can advance without a full re-render.
     */
    public function nextStep(string $currentTab): void
    {
        // Validate the current tab — throws validation exception on failure, which Livewire
        // returns as errors to the browser without dispatching the advance event.
        if ($currentTab !== 'review') {
            $this->validateTab($currentTab);
        }

        $steps = self::WIZARD_STEPS;
        $idx   = array_search($currentTab, $steps, true);
        $next  = ($idx !== false && isset($steps[$idx + 1])) ? $steps[$idx + 1] : null;

        if ($next) {
            $this->dispatch('wizard-advance', tab: $next);
        }
    }

    /**
     * Validate a single member form field when focus leaves the input.
     */
    public function validateField(string $field): void
    {
        $rules = $this->getValidationRules();

        if ($this->isEdit && ! empty($this->form['password']) && ! Auth::user()->can('members.update')) {
            $rules['form.current_password'] = 'required|current_password';
        }

        if (! array_key_exists($field, $rules)) {
            return;
        }

        if ($this->isEdit && empty($this->form['password']) && in_array($field, ['form.password', 'form.password_confirmation', 'form.current_password'], true)) {
            $this->resetValidation(['form.password', 'form.password_confirmation', 'form.current_password']);
            $this->updateTabErrors();

            return;
        }

        $this->validateOnly($field, $rules, $this->getCustomMessages());
        $this->updateTabErrors();
    }

    /**
     * Validate only the fields relevant to a specific tab.
     * Used by the multi-tab member modal before moving between steps.
     */
    public function validateTab($tab)
    {
        $tab = (string) $tab;
        if (! isset(self::TAB_FIELDS[$tab])) {
            return;
        }

        $rules = $this->getValidationRules();

        // Add conditional current_password rule (matches saveMember behavior)
        if ($this->isEdit && ! empty($this->form['password']) && ! Auth::user()->can('members.update')) {
            $rules['form.current_password'] = 'required|current_password';
        }

        $fields = self::TAB_FIELDS[$tab];
        $subsetRules = [];
        foreach ($fields as $field) {
            if (isset($rules[$field])) {
                $subsetRules[$field] = $rules[$field];
            }
        }

        // Login tab: if editing and password is blank, skip validating password fields
        if ($tab === 'login' && $this->isEdit && empty($this->form['password'])) {
            unset($subsetRules['form.password'], $subsetRules['form.password_confirmation'], $subsetRules['form.current_password']);
        }

        if (! empty($subsetRules)) {
            $this->validate($subsetRules, $this->getCustomMessages());
        }

        $this->updateTabErrors();
    }

    /**
     * Get validation rules
     */
    private function getValidationRules()
    {
        $minimumAge = $this->minimumAgeRequirement();

        return [
            // Personal Information
            'form.first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.mid_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.suffix' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.birth_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail) use ($minimumAge) {
                    if (empty($value)) {
                        return;
                    }

                    try {
                        $birthDate = Carbon::parse((string) $value);
                    } catch (\Throwable) {
                        return;
                    }

                    if ($birthDate->greaterThan(now()->subYears($minimumAge))) {
                        $fail("Members must be at least {$minimumAge} years old.");
                    }
                },
            ],
            'form.birthplace' => 'nullable|string|max:255',
            'form.gender' => 'required|in:male,female,other',
            'form.civil_status' => 'required|in:single,married,widowed,separated',
            'form.religion' => 'nullable|string|max:64',
            'form.title' => 'nullable|in:Chairman,Vice Chairman,Secretary,Treasurer,Loan Officer,Member',
            // Address
            'form.country' => 'nullable|string|max:100',
            'form.region' => 'nullable|string|max:100',
            'form.province' => 'nullable|string|max:100',
            'form.city' => 'nullable|string|max:100',
            'form.barangay' => 'nullable|string|max:100',
            'form.street' => 'nullable|string|max:255',
            'form.postal_code' => 'nullable|string|max:20',
            // Contact Information
            'form.email' => 'required|email|unique:users,email' . ($this->isEdit ? ',' . $this->form['id'] . ',id' : ''),
            'form.phone_number' => 'required|string|max:11',
            'form.mobile_number' => 'nullable|string|max:11',
            'form.facebook_link' => 'nullable|url|max:255',
            // Government IDs
            'form.national_id' => 'nullable|string|max:64',
            'form.tin' => 'nullable|string|max:64',
            'form.sss' => 'nullable|string|max:64',
            'form.philhealth' => 'nullable|string|max:64',
            'form.barangay_id' => 'nullable|string|max:64',
            // Employment
            'form.employment_type' => 'nullable|in:full-time,part-time,self-employed,freelancer,government,private,ofw,retired,unemployed,student,homemaker',
            'form.employer_name' => 'nullable|string|max:255',
            'form.position' => 'nullable|string|max:100',
            'form.monthly_income' => 'nullable|numeric|min:0',
            'form.other_income_source' => 'nullable|string|max:255',
            'form.other_income_amount' => 'nullable|numeric|min:0',
            // Password
            ...$this->getPasswordValidationRules(),
            // Agreement
            'form.terms_agreed' => $this->isEdit ? 'nullable|boolean' : 'accepted',
        ];
    }

    /**
     * Update tab error indicators
     */
    private function updateTabErrors()
    {
        $errors = $this->getErrorBag();

        // Personal tab fields
        $personalFields = [
            'form.first_name',
            'form.last_name',
            'form.mid_name',
            'form.suffix',
            'form.birth_date',
            'form.birthplace',
            'form.gender',
            'form.civil_status',
            'form.religion',
            'form.title'
        ];
        $this->tabErrors['personal'] = collect($personalFields)->some(fn($field) => $errors->has($field));

        // Address tab fields
        $addressFields = [
            'form.country',
            'form.region',
            'form.province',
            'form.city',
            'form.barangay',
            'form.street',
            'form.postal_code'
        ];
        $this->tabErrors['address'] = collect($addressFields)->some(fn($field) => $errors->has($field));

        // Contact tab fields
        $contactFields = [
            'form.email',
            'form.phone_number',
            'form.mobile_number',
            'form.emergency_contact',
            'form.facebook_link'
        ];
        $this->tabErrors['contact'] = collect($contactFields)->some(fn($field) => $errors->has($field));

        // IDs tab fields
        $idsFields = ['form.national_id', 'form.tin', 'form.sss', 'form.philhealth', 'form.barangay_id'];
        $this->tabErrors['ids'] = collect($idsFields)->some(fn($field) => $errors->has($field));

        // Employment tab fields
        $employmentFields = [
            'form.employment_type',
            'form.employer_name',
            'form.position',
            'form.monthly_income',
            'form.other_income_source',
            'form.other_income_amount'
        ];
        $this->tabErrors['employment'] = collect($employmentFields)->some(fn($field) => $errors->has($field));

        // Login tab fields
        $loginFields = ['form.password', 'form.password_confirmation', 'form.current_password'];
        $this->tabErrors['login'] = collect($loginFields)->some(fn($field) => $errors->has($field));

        // Agreement tab fields
        $this->tabErrors['agreement'] = $errors->has('form.terms_agreed') || $errors->has('agreementUpload');
    }

    /**
     * Check if form is valid (no errors and required fields filled)
     */
    public function getIsFormValidProperty()
    {
        // Check required fields with proper trimming
        $requiredFilled = ! empty(trim($this->form['first_name'] ?? '')) &&
            ! empty(trim($this->form['last_name'] ?? '')) &&
            ! empty(trim($this->form['email'] ?? '')) &&
            ! empty($this->form['birth_date'] ?? '') &&
            ! empty($this->form['gender'] ?? '') &&
            ! empty($this->form['civil_status'] ?? '') &&
            ! empty(trim($this->form['phone_number'] ?? ''));

        if (! $this->isEdit) {
            $requiredFilled = $requiredFilled &&
                ! empty($this->form['password'] ?? '') &&
                ! empty($this->form['password_confirmation'] ?? '') &&
                ($this->form['password'] ?? '') === ($this->form['password_confirmation'] ?? '');
        }

        // Check no validation errors
        $noErrors = $this->getErrorBag()->isEmpty();

        return $requiredFilled && $noErrors;
    }

    /**
     * Computed property to check for missing required fields in each tab
     */
    public function getTabRequiredErrorsProperty()
    {
        $errors = [];

        // Personal tab required fields
        $errors['personal'] = empty(trim($this->form['first_name'] ?? '')) ||
            empty(trim($this->form['last_name'] ?? '')) ||
            empty($this->form['birth_date'] ?? '') ||
            empty($this->form['gender'] ?? '') ||
            empty($this->form['civil_status'] ?? '');

        // Contact tab required fields  
        $errors['contact'] = empty(trim($this->form['email'] ?? '')) ||
            empty(trim($this->form['phone_number'] ?? ''));

        // Login tab required fields (only when not editing)
        if (!$this->isEdit) {
            $errors['login'] = empty($this->form['password'] ?? '') ||
                empty($this->form['password_confirmation'] ?? '');
        } else {
            $errors['login'] = false;
        }

        // Other tabs have no required fields
        $errors['address'] = false;
        $errors['ids'] = false;
        $errors['employment'] = false;

        // Agreement tab required fields (add mode only)
        $errors['agreement'] = !$this->isEdit && empty($this->form['terms_agreed'] ?? false);

        return $errors;
    }

    /**
     * Reset pagination when search query changes
     */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openExportModal(): void
    {
        $this->closeModal();
        $this->closeEditModal();
        $this->resetValidation(['exportType', 'exportStartDate', 'exportEndDate']);
        $this->resetExportFilters();
        $this->showExportModal = true;
    }

    public function closeExportModal(): void
    {
        $this->showExportModal = false;
        $this->resetValidation(['exportType', 'exportStartDate', 'exportEndDate']);
    }

    public function exportMembers()
    {
        $validated = $this->validate([
            'exportType' => ['required', 'in:pdf,excel'],
            'exportStartDate' => ['required', 'date'],
            'exportEndDate' => ['required', 'date', 'after_or_equal:exportStartDate'],
        ], [
            'exportEndDate.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $start = Carbon::parse($validated['exportStartDate'])->startOfDay();
        $end = Carbon::parse($validated['exportEndDate'])->endOfDay();
        $members = $this->membersExportQuery($start, $end)->get();

        $this->showExportModal = false;

        return $validated['exportType'] === 'pdf'
            ? $this->downloadMembersPdf($members, $start, $end)
            : $this->downloadMembersExcel($members, $start, $end);
    }

    /**
     * Toggle sort direction or change sort field
     */
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Load member profile with all relationships and show modal
     */
    public function viewProfile($userId)
    {
        $this->closeEditModal();
        $this->closeExportModal();

        $this->selectedMember = User::with([
            'personalData.address',
            'personalData.contact',
            'personalData.employment',
            'personalData.memberId',
            'accounts',
            'loans',
            'beneficiaries',
        ])->find($userId);
        $this->activeTab = 'overview';
        $this->profileModalKey = now()->timestamp . rand(1000, 9999); // Generate unique key for fresh modal
        $this->showModal = true;
    }

    /**
     * Change active tab in profile modal
     */
    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    /**
     * Close profile view modal
     */
    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedMember = null;
        $this->activeTab = 'overview';
    }

    /**
     * Initialize form for adding new member
     */
    public function addMember()
    {
        $this->closeModal();
        $this->closeExportModal();

        $this->resetValidation();
        $this->isEdit = false;
        // Initialize all form fields with empty values to ensure proper wire:model binding
        $this->form = [
            // Personal Information
            'first_name' => '',
            'mid_name' => '',
            'last_name' => '',
            'suffix' => '',
            'birth_date' => '',
            'birthplace' => '',
            'gender' => '',
            'civil_status' => '',
            'religion' => '',
            'nationality' => 'Filipino',
            'title' => 'Member',
            // Address
            'country' => 'Philippines',
            'region' => '',
            'province' => '',
            'city' => '',
            'barangay' => '',
            'street' => '',
            'postal_code' => '',
            // Contact Information
            'email' => '',
            'phone_number' => '',
            'mobile_number' => '',
            'emergency_contact' => '',
            'facebook_link' => '',
            // Government IDs
            'national_id' => '',
            'tin' => '',
            'sss' => '',
            'philhealth' => '',
            'barangay_id' => '',
            // Employment
            'employment_type' => '',
            'employer_name' => '',
            'position' => '',
            'monthly_income' => '',
            'other_income_source' => '',
            'other_income_amount' => '',
            // Beneficiaries
            'beneficiaries' => [],
            // Login
            'password' => '',
            'password_confirmation' => '',
            // Agreement
            'terms_agreed' => false,
        ];
        $this->modalKey = now()->timestamp . rand(1000, 9999); // Generate truly unique key using timestamp
        $this->photoUpload = null;
        $this->nationalIdPhoto = null;
        $this->tinPhoto = null;
        $this->sssPhoto = null;
        $this->philhealthPhoto = null;
        $this->barangayIdPhoto = null;
        $this->agreementUpload = null;
        $this->showEditModal = true;
    }

    /**
     * Load member data into form for editing
     */
    public function editMember($userId)
    {
        Log::info('=== EDIT MEMBER CALLED ===', [
            'userId' => $userId,
            'currentIsEdit' => $this->isEdit,
            'currentEditingMemberId' => $this->editingMemberId,
            'currentShowEditModal' => $this->showEditModal,
            'formHasData' => ! empty($this->form),
        ]);

        $this->resetValidation();
        $this->closeExportModal();
        $this->isEdit = true;
        $this->editingMemberId = $userId;
        $this->modalKey = now()->timestamp . rand(1000, 9999); // Generate truly unique key using timestamp
        $this->showModal = false; // Close profile modal first
        $this->selectedMember = null; // Clear selected member to fully close profile modal
        $member = User::with([
            'personalData.address',
            'personalData.contact',
            'personalData.employment',
            'personalData.memberId',
            'beneficiaries',
        ])->find($userId);

        // Populate form with all member data
        $this->form = [
            'id' => $member->id,
            // Personal Information
            'first_name' => $member->personalData->first_name ?? '',
            'mid_name' => $member->personalData->mid_name ?? '',
            'last_name' => $member->personalData->last_name ?? '',
            'suffix' => $member->personalData->suffix ?? '',
            'birth_date' => $member->personalData->birth_date ?? '',
            'birthplace' => $member->personalData->birthplace ?? '',
            'gender' => $member->personalData->gender ?? '',
            'civil_status' => $member->personalData->civil_status ?? '',
            'religion' => $member->personalData->religion ?? '',
            'nationality' => 'Filipino', // Default, can be added to DB if needed
            'title' => $member->personalData->title ?? 'Member',
            // Address
            'country' => $member->personalData->address?->country ?? 'Philippines',
            'region' => $member->personalData->address?->region ?? '',
            'province' => $member->personalData->address?->province ?? '',
            'city' => $member->personalData->address?->city_municipality ?? '',
            'barangay' => $member->personalData->address?->barangay ?? '',
            'street' => $member->personalData->address?->street ?? '',
            'postal_code' => $member->personalData->address?->zip_code ?? '',
            // Contact Information
            'email' => $member->email,
            'phone_number' => $member->personalData->contact?->phone_no ?? '',
            'mobile_number' => $member->personalData->contact?->phone_no ?? '', // Same as phone
            'emergency_contact' => '', // Can be added to contacts table if needed
            'facebook_link' => $member->personalData->contact?->facebook ?? '',
            // Government IDs
            'national_id' => $member->personalData->memberId?->national_id ?? '',
            'tin' => $member->personalData->memberId?->tin ?? '',
            'sss' => $member->personalData->memberId?->sss ?? '',
            'philhealth' => $member->personalData->memberId?->philhealth ?? '',
            'barangay_id' => $member->personalData->memberId?->barangay_id ?? '',
            // Employment
            'employment_type' => $member->personalData->employment?->employment_type ?? '',
            'employer_name' => $member->personalData->employment?->company ?? '',
            'position' => $member->personalData->employment?->position ?? '',
            'monthly_income' => $member->personalData->employment?->monthly_income ?? '',
            'other_income_source' => $member->personalData->employment?->other_income_source ?? '',
            'other_income_amount' => $member->personalData->employment?->other_income_amount ?? '',
            // Beneficiaries (array)
            'beneficiaries' => $member->beneficiaries->map(function ($b) {
                return [
                    'id' => $b->id,
                    'first_name' => $b->first_name,
                    'mid_name' => $b->mid_name,
                    'last_name' => $b->last_name,
                    'birth_date' => $b->birth_date,
                    'relationship' => $b->relationship,
                    'phone_no' => $b->phone_no,
                    'street' => $b->street,
                    'barangay' => $b->barangay,
                    'city' => $b->city,
                    'province' => $b->province,
                    'postal_code' => $b->postal_code,
                ];
            })->toArray(),
            // Profile Picture (placeholder for future implementation)
            'profile_picture' => '',
        ];

        $this->photoUpload = null;
        $this->nationalIdPhoto = null;
        $this->tinPhoto = null;
        $this->sssPhoto = null;
        $this->philhealthPhoto = null;
        $this->barangayIdPhoto = null;
        $this->showEditModal = true;

        Log::info('=== EDIT MEMBER FORM POPULATED ===', [
            'formId' => $this->form['id'] ?? 'no-id',
            'formFirstName' => $this->form['first_name'] ?? 'no-name',
            'showEditModal' => $this->showEditModal,
            'isEdit' => $this->isEdit,
            'editingMemberId' => $this->editingMemberId,
        ]);
    }

    /**
     * Save member data (create or update)
     */
    public function saveMember()
    {
        $this->form['email'] = User::normalizeEmail($this->form['email'] ?? null);

        Log::info('=== SAVE MEMBER CALLED ===', [
            'isEdit' => $this->isEdit,
            'editingMemberId' => $this->editingMemberId,
            'formId' => $this->form['id'] ?? 'no-id',
            'formFirstName' => $this->form['first_name'] ?? 'no-name',
            'formLastName' => $this->form['last_name'] ?? 'no-name',
            'formEmail' => $this->form['email'] ?? 'no-email',
            'showEditModal' => $this->showEditModal,
            'formKeys' => array_keys($this->form),
        ]);

        // Get validation rules and add beneficiaries rules
        $rules = $this->getValidationRules();

        // Add conditional current_password rule
        if ($this->isEdit && ! empty($this->form['password']) && ! Auth::user()->can('members.update')) {
            $rules['form.current_password'] = 'required|current_password';
        }

        // Agreement file upload validation
        $rules['agreementUpload'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240';

        // Add beneficiaries array validation
        $rules['form.beneficiaries'] = 'nullable|array';
        $rules['form.beneficiaries.*.first_name'] = 'required|string|max:100';
        $rules['form.beneficiaries.*.last_name'] = 'required|string|max:100';
        $rules['form.beneficiaries.*.relationship'] = 'required|string|max:50';
        $rules['form.beneficiaries.*.birth_date'] = 'nullable|date';
        $rules['form.beneficiaries.*.phone_no'] = 'nullable|string|max:11';

        $this->validate($rules, $this->getCustomMessages());
        $this->updateTabErrors();

        Log::info('=== VALIDATION PASSED ===', [
            'isEdit' => $this->isEdit,
            'formId' => $this->form['id'] ?? 'no-id',
        ]);

        // Use transaction to ensure all related records are created/updated atomically
        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                // UPDATE MODE: Modify existing member and related records
                $member = User::find($this->form['id']);

                // Update or create contact record
                if ($member->personalData->contact) {
                    $member->personalData->contact->update([
                        'phone_no' => $this->form['phone_number'] ?? null,
                        'email' => $this->form['email'],
                        'facebook' => $this->form['facebook_link'] ?? null,
                    ]);
                } else {
                    $contact = Contact::create([
                        'phone_no' => $this->form['phone_number'] ?? null,
                        'email' => $this->form['email'],
                        'facebook' => $this->form['facebook_link'] ?? null,
                    ]);
                    $member->personalData->update(['contact_id' => $contact->id]);
                }

                // Update or create address record
                if ($member->personalData->address) {
                    $member->personalData->address->update([
                        'country' => $this->form['country'] ?? null,
                        'region' => $this->form['region'] ?? null,
                        'province' => $this->form['province'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'street' => $this->form['street'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                } elseif (! empty($this->form['street']) || ! empty($this->form['city'])) {
                    $address = Address::create([
                        'country' => $this->form['country'] ?? null,
                        'region' => $this->form['region'] ?? null,
                        'province' => $this->form['province'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'street' => $this->form['street'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                    $member->personalData->update(['address_id' => $address->id]);
                }

                // Update or create employment record
                if ($member->personalData->employment) {
                    $member->personalData->employment->update([
                        'employment_type' => $this->form['employment_type'] ?? null,
                        'company' => $this->form['employer_name'] ?? null,
                        'position' => $this->form['position'] ?? null,
                        'monthly_income' => $this->emptyToNull($this->form['monthly_income'] ?? null),
                        'other_income_source' => $this->form['other_income_source'] ?? null,
                        'other_income_amount' => $this->emptyToNull($this->form['other_income_amount'] ?? null),
                    ]);
                } elseif (! empty($this->form['employment_type'])) {
                    $employment = Employment::create([
                        'employment_type' => $this->form['employment_type'] ?? null,
                        'company' => $this->form['employer_name'] ?? null,
                        'position' => $this->form['position'] ?? null,
                        'monthly_income' => $this->emptyToNull($this->form['monthly_income'] ?? null),
                        'other_income_source' => $this->form['other_income_source'] ?? null,
                        'other_income_amount' => $this->emptyToNull($this->form['other_income_amount'] ?? null),
                    ]);
                    $member->personalData->update(['employment_id' => $employment->id]);
                }

                // Update or create member IDs record
                if ($member->personalData->memberId) {
                    $member->personalData->memberId->update([
                        'national_id' => $this->form['national_id'] ?? null,
                        'tin' => $this->form['tin'] ?? null,
                        'sss' => $this->form['sss'] ?? null,
                        'philhealth' => $this->form['philhealth'] ?? null,
                        'barangay_id' => $this->form['barangay_id'] ?? null,
                    ]);
                } elseif (! empty($this->form['national_id']) || ! empty($this->form['tin'])) {
                    $memberId = MemberId::create([
                        'national_id' => $this->form['national_id'] ?? null,
                        'tin' => $this->form['tin'] ?? null,
                        'sss' => $this->form['sss'] ?? null,
                        'philhealth' => $this->form['philhealth'] ?? null,
                        'barangay_id' => $this->form['barangay_id'] ?? null,
                    ]);
                    $member->personalData->update(['member_id' => $memberId->id]);
                }

                // Update personal data
                $personalDataUpdate = [
                    'first_name' => $this->form['first_name'],
                    'last_name' => $this->form['last_name'],
                    'mid_name' => $this->form['mid_name'] ?? null,
                    'suffix' => $this->form['suffix'] ?? null,
                    'birth_date' => $this->form['birth_date'] ?? null,
                    'birthplace' => $this->form['birthplace'] ?? null,
                    'gender' => $this->form['gender'] ?? null,
                    'civil_status' => $this->form['civil_status'] ?? null,
                    'religion' => $this->form['religion'] ?? null,
                    'title' => $this->form['title'] ?? 'Member',
                ];

                if ($this->photoUpload) {
                    if ($member->personalData->profile_picture) {
                        Storage::disk('public')->delete($member->personalData->profile_picture);
                    }
                    $personalDataUpdate['profile_picture'] = $this->photoUpload->store('profile-photos', 'public');
                }

                $member->personalData->update($personalDataUpdate);

                // Handle ID photo uploads for existing memberId
                if ($member->personalData->memberId) {
                    $idUpdates = [];
                    $idFields = [
                        'national_id_photo' => $this->nationalIdPhoto,
                        'tin_photo'          => $this->tinPhoto,
                        'sss_photo'          => $this->sssPhoto,
                        'philhealth_photo'   => $this->philhealthPhoto,
                        'barangay_id_photo'  => $this->barangayIdPhoto,
                    ];
                    foreach ($idFields as $column => $upload) {
                        if ($upload) {
                            $existing = $member->personalData->memberId->$column;
                            if ($existing) Storage::disk('public')->delete($existing);
                            $idUpdates[$column] = $upload->store('id-photos', 'public');
                        }
                    }
                    if ($idUpdates) {
                        $member->personalData->memberId->update($idUpdates);
                    }
                }

                // Update beneficiaries
                if (isset($this->form['beneficiaries'])) {
                    // Get existing beneficiary IDs
                    $existingIds = collect($this->form['beneficiaries'])->pluck('id')->filter();
                    // Delete beneficiaries not in the form
                    $member->beneficiaries()->whereNotIn('id', $existingIds)->delete();

                    // Update or create beneficiaries
                    foreach ($this->form['beneficiaries'] as $beneficiaryData) {
                        if (isset($beneficiaryData['id'])) {
                            // Update existing
                            Beneficiary::where('id', $beneficiaryData['id'])->update([
                                'first_name' => $beneficiaryData['first_name'],
                                'mid_name' => $beneficiaryData['mid_name'] ?? null,
                                'last_name' => $beneficiaryData['last_name'],
                                'birth_date' => $beneficiaryData['birth_date'] ?? null,
                                'relationship' => $beneficiaryData['relationship'],
                                'phone_no' => $beneficiaryData['phone_no'] ?? null,
                                'street' => $beneficiaryData['street'] ?? null,
                                'barangay' => $beneficiaryData['barangay'] ?? null,
                                'city' => $beneficiaryData['city'] ?? null,
                                'province' => $beneficiaryData['province'] ?? null,
                                'postal_code' => $beneficiaryData['postal_code'] ?? null,
                            ]);
                        } else {
                            // Create new
                            Beneficiary::create([
                                'user_id' => $member->id,
                                'first_name' => $beneficiaryData['first_name'],
                                'mid_name' => $beneficiaryData['mid_name'] ?? null,
                                'last_name' => $beneficiaryData['last_name'],
                                'birth_date' => $beneficiaryData['birth_date'] ?? null,
                                'relationship' => $beneficiaryData['relationship'],
                                'phone_no' => $beneficiaryData['phone_no'] ?? null,
                                'street' => $beneficiaryData['street'] ?? null,
                                'barangay' => $beneficiaryData['barangay'] ?? null,
                                'city' => $beneficiaryData['city'] ?? null,
                                'province' => $beneficiaryData['province'] ?? null,
                                'postal_code' => $beneficiaryData['postal_code'] ?? null,
                            ]);
                        }
                    }
                }

                // Update user credentials (password only if provided)
                $updateData = ['email' => $this->form['email']];
                if (! empty($this->form['password'])) {
                    $updateData['password'] = Hash::make($this->form['password']);
                }
                $member->update($updateData);

                app(AuditLogService::class)->log(
                    'member.updated',
                    'Member profile updated.',
                    'member',
                    $member,
                    Auth::user(),
                    [
                        'member_id' => $member->id,
                        'email' => $member->email,
                    ]
                );

                Log::info('=== MEMBER UPDATE COMPLETED ===', [
                    'memberId' => $member->id,
                    'email' => $this->form['email'],
                ]);

                // Dispatch toast notification
                $this->dispatch('toast', ['message' => 'Member updated successfully!', 'type' => 'success']);
            } else {
                // CREATE MODE: Insert new member with all related records

                // Step 1: Create contact record
                $contact = Contact::create([
                    'phone_no' => $this->form['phone_number'] ?? null,
                    'email' => $this->form['email'],
                    'facebook' => $this->form['facebook_link'] ?? null,
                ]);

                // Step 2: Create address record (optional)
                $address = null;
                if (! empty($this->form['street']) || ! empty($this->form['city'])) {
                    $address = Address::create([
                        'country' => $this->form['country'] ?? null,
                        'region' => $this->form['region'] ?? null,
                        'province' => $this->form['province'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'street' => $this->form['street'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                }

                // Step 3: Create employment record (optional)
                $employment = null;
                if (! empty($this->form['employment_type'])) {
                    $employment = Employment::create([
                        'employment_type' => $this->form['employment_type'] ?? null,
                        'company' => $this->form['employer_name'] ?? null,
                        'position' => $this->form['position'] ?? null,
                        'monthly_income' => $this->form['monthly_income'] ?? null,
                        'other_income_source' => $this->form['other_income_source'] ?? null,
                        'other_income_amount' => $this->form['other_income_amount'] ?? null,
                    ]);
                }

                // Step 4: Create member IDs record (optional)
                $memberId = null;
                $hasIdData = ! empty($this->form['national_id']) || ! empty($this->form['tin']) || ! empty($this->form['sss']) || ! empty($this->form['philhealth']) || ! empty($this->form['barangay_id'])
                    || $this->nationalIdPhoto || $this->tinPhoto || $this->sssPhoto || $this->philhealthPhoto || $this->barangayIdPhoto;
                if ($hasIdData) {
                    $memberId = MemberId::create([
                        'national_id'       => $this->form['national_id'] ?? null,
                        'national_id_photo' => $this->nationalIdPhoto ? $this->nationalIdPhoto->store('id-photos', 'public') : null,
                        'tin'               => $this->form['tin'] ?? null,
                        'tin_photo'         => $this->tinPhoto ? $this->tinPhoto->store('id-photos', 'public') : null,
                        'sss'               => $this->form['sss'] ?? null,
                        'sss_photo'         => $this->sssPhoto ? $this->sssPhoto->store('id-photos', 'public') : null,
                        'philhealth'        => $this->form['philhealth'] ?? null,
                        'philhealth_photo'  => $this->philhealthPhoto ? $this->philhealthPhoto->store('id-photos', 'public') : null,
                        'barangay_id'       => $this->form['barangay_id'] ?? null,
                        'barangay_id_photo' => $this->barangayIdPhoto ? $this->barangayIdPhoto->store('id-photos', 'public') : null,
                    ]);
                }

                // Step 5: Create personal data record (links to all related records)
                $profilePhotoPath = $this->photoUpload
                    ? $this->photoUpload->store('profile-photos', 'public')
                    : null;
                $personalData = PersonalData::create([
                    'first_name' => $this->form['first_name'],
                    'last_name' => $this->form['last_name'],
                    'mid_name' => $this->form['mid_name'] ?? null,
                    'suffix' => $this->form['suffix'] ?? null,
                    'birth_date' => $this->form['birth_date'] ?? null,
                    'birthplace' => $this->form['birthplace'] ?? null,
                    'gender' => $this->form['gender'] ?? null,
                    'civil_status' => $this->form['civil_status'] ?? null,
                    'religion' => $this->form['religion'] ?? null,
                    'title' => $this->form['title'] ?? 'Member',
                    'profile_picture' => $profilePhotoPath,
                    'contact_id' => $contact->id,
                    'address_id' => $address ? $address->id : null,
                    'employment_id' => $employment ? $employment->id : null,
                    'member_id' => $memberId ? $memberId->id : null,
                ]);

                // Step 6: Create user record (links to personal data, role hardcoded as 'member')
                $user = User::create([
                    'personal_data_id' => $personalData->id,
                    'email' => $this->form['email'],
                    'password' => Hash::make($this->form['password']),
                    'role' => 'member',
                ]);

                // Step 7: Store agreement file upload if provided
                if ($this->agreementUpload) {
                    $this->agreementUpload->store('member-agreements/' . $user->id, 'public');
                }

                // Step 8: Create beneficiaries if provided
                if (isset($this->form['beneficiaries'])) {
                    foreach ($this->form['beneficiaries'] as $beneficiaryData) {
                        Beneficiary::create([
                            'user_id' => $user->id,
                            'first_name' => $beneficiaryData['first_name'],
                            'mid_name' => $beneficiaryData['mid_name'] ?? null,
                            'last_name' => $beneficiaryData['last_name'],
                            'birth_date' => $beneficiaryData['birth_date'] ?? null,
                            'relationship' => $beneficiaryData['relationship'],
                            'phone_no' => $beneficiaryData['phone_no'] ?? null,
                            'street' => $beneficiaryData['street'] ?? null,
                            'barangay' => $beneficiaryData['barangay'] ?? null,
                            'city' => $beneficiaryData['city'] ?? null,
                            'province' => $beneficiaryData['province'] ?? null,
                            'postal_code' => $beneficiaryData['postal_code'] ?? null,
                        ]);
                    }
                }

                // Step 9: Automatically create savings and share capital accounts
                Account::create([
                    'user_id' => $user->id,
                    'account_type' => 'savings',
                    'balance' => 0.00,
                ]);

                Account::create([
                    'user_id' => $user->id,
                    'account_type' => 'share-capital',
                    'balance' => 0.00,
                ]);

                // Assign Spatie roles/permissions
                // All members get the 'member' role
                $user->assignRole('member');

                app(AuditLogService::class)->log(
                    'member.created',
                    'New member created by staff.',
                    'member',
                    $user,
                    Auth::user(),
                    [
                        'member_id' => $user->id,
                        'email' => $user->email,
                    ]
                );

                // Send welcome email to new member
                try {
                    $this->sendWelcomeEmail($user, $this->form['password']);
                } catch (\Exception $e) {
                    Log::error('Failed to send welcome email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if email fails
                }

                // Dispatch toast notification
                $this->dispatch('toast', ['message' => 'Member created successfully!', 'type' => 'success']);
            }

            DB::commit();

            Log::info('=== DB COMMIT SUCCESSFUL ===', [
                'memberId' => $this->form['id'] ?? 'new',
            ]);

            $this->dispatch('member-saved');
            $this->closeEditModal();
        } catch (\Exception $e) {
            // Rollback all changes if any error occurs
            DB::rollBack();

            Log::error('=== SAVE MEMBER ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formId' => $this->form['id'] ?? 'new',
            ]);

            $this->dispatch('toast', ['message' => 'An error occurred: ' . $e->getMessage(), 'type' => 'error']);
        }
    }

    /**
     * Close edit/add modal and reset form
     */
    public function closeEditModal()
    {
        Log::info('=== CLOSE EDIT MODAL CALLED ===', [
            'previousShowEditModal' => $this->showEditModal,
            'previousIsEdit' => $this->isEdit,
            'previousEditingMemberId' => $this->editingMemberId,
            'formHadData' => ! empty($this->form),
        ]);

        $this->showEditModal = false;
        $this->form = [];
        $this->isEdit = false;
        $this->editingMemberId = null;
        $this->photoUpload = null;
        $this->nationalIdPhoto = null;
        $this->tinPhoto = null;
        $this->sssPhoto = null;
        $this->philhealthPhoto = null;
        $this->barangayIdPhoto = null;
        // Keep modalKey as-is to prevent issues with next modal open
        $this->resetValidation();

        Log::info('=== CLOSE EDIT MODAL COMPLETED ===', [
            'showEditModal' => $this->showEditModal,
            'isEdit' => $this->isEdit,
            'editingMemberId' => $this->editingMemberId,
            'formIsEmpty' => empty($this->form),
        ]);
    }

    /**
     * Render members list with search, filter, and sort
     */
    public function render()
    {
        $user = Auth::user();
        $canCreate = $user && $user->can('members.create');
        $canEdit = $user && (
            $user->can('members.update') ||
            $user->can('members.delete')
        );
        $canExport = (bool) $user;

        $query = $this->membersQuery()->with(['personalData']);
        $this->applyMembersSorting($query);

        $members = $query->paginate(10);

        return view('livewire.content.members', [
            'members' => $members,
            'canCreate' => $canCreate,
            'canEdit' => $canEdit,
            'canExport' => $canExport,
        ]);
    }

    private function resetExportFilters(): void
    {
        $this->exportType = 'pdf';
        $this->exportStartDate = now()->startOfMonth()->toDateString();
        $this->exportEndDate = now()->endOfMonth()->toDateString();
    }

    private function membersQuery(): Builder
    {
        return User::query()
            ->memberUsers()
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('user_number', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('personalData', function (Builder $subQ) {
                            $subQ->where('first_name', 'like', '%' . $this->search . '%')
                                ->orWhere('last_name', 'like', '%' . $this->search . '%')
                                ->orWhere('mid_name', 'like', '%' . $this->search . '%')
                                ->orWhereHas('contact', function (Builder $contactQ) {
                                    $contactQ->where('phone_no', 'like', '%' . $this->search . '%')
                                        ->orWhere('email', 'like', '%' . $this->search . '%')
                                        ->orWhere('facebook', 'like', '%' . $this->search . '%');
                                })
                                ->orWhereHas('address', function (Builder $addressQ) {
                                    $addressQ->where('street', 'like', '%' . $this->search . '%')
                                        ->orWhere('barangay', 'like', '%' . $this->search . '%')
                                        ->orWhere('city_municipality', 'like', '%' . $this->search . '%')
                                        ->orWhere('province', 'like', '%' . $this->search . '%')
                                        ->orWhere('region', 'like', '%' . $this->search . '%')
                                        ->orWhere('zip_code', 'like', '%' . $this->search . '%');
                                });
                        });
                });
            });
    }

    private function applyMembersSorting(Builder $query): void
    {
        if (in_array($this->sortField, ['first_name', 'last_name', 'mid_name'], true)) {
            $query->join('personal_data', 'users.personal_data_id', '=', 'personal_data.id')
                ->orderBy('personal_data.' . $this->sortField, $this->sortDirection)
                ->select('users.*');

            return;
        }

        if ($this->sortField === 'user_number') {
            $query->orderByRaw('CAST(users.user_number AS UNSIGNED) ' . $this->sortDirection);

            return;
        }

        $query->orderBy('users.' . $this->sortField, $this->sortDirection);
    }

    private function membersExportQuery(Carbon $start, Carbon $end): Builder
    {
        $query = $this->membersQuery()
            ->with(['personalData.address', 'personalData.contact'])
            ->whereBetween('users.created_at', [$start, $end]);

        $this->applyMembersSorting($query);

        return $query;
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
        return 'members-' . $start->format('Y-m-d') . '-to-' . $end->format('Y-m-d') . '.' . $extension;
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

                    return '<Cell><Data ss:Type="String">' . $escaped . '</Data></Cell>';
                })->implode('');

                return '<Row>' . $cells . '</Row>';
            })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:html="http://www.w3.org/TR/REC-html40">'
            . '<Worksheet ss:Name="Members"><Table>'
            . $xmlRows
            . '</Table></Worksheet></Workbook>';
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

    public function nextPage()
    {
        $this->setPage($this->getPage() + 1);
    }

    public function previousPage()
    {
        $this->setPage(max(1, $this->getPage() - 1));
    }

    /**
     * Toggle special loan eligibility for a member (Manager/Admin only)
     */
    public function toggleSpecialLoanEligibility($memberId)
    {
        // Require member update permission to toggle special loan eligibility.
        if (!Auth::user()->can('members.update')) {
            $this->dispatch('toast', ['message' => 'Unauthorized action.', 'type' => 'error']);
            return;
        }

        try {
            $member = User::findOrFail($memberId);
            $member->special_loan_eligible = !$member->special_loan_eligible;
            $member->save();

            app(AuditLogService::class)->log(
                'member.special_loan_eligibility_toggled',
                'Special loan eligibility status changed.',
                'member',
                $member,
                Auth::user(),
                [
                    'member_id' => $member->id,
                    'special_loan_eligible' => (bool) $member->special_loan_eligible,
                ]
            );

            $status = $member->special_loan_eligible ? 'enabled' : 'disabled';
            $this->dispatch('toast', [
                'message' => "Special loan eligibility {$status} for {$member->personalData->first_name} {$member->personalData->last_name}.",
                'type' => 'success'
            ]);

            // Refresh the selected member data
            if ($this->selectedMember && $this->selectedMember->id === $memberId) {
                $this->selectedMember = User::with(['personalData', 'accounts', 'loans'])->find($memberId);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', ['message' => 'Failed to update eligibility.', 'type' => 'error']);
        }
    }

    /**
     * Send welcome email to new member
     */
    private function sendWelcomeEmail($user, $plainPassword)
    {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME');
        $mail->Password   = env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
        $mail->Port       = env('MAIL_PORT', 587);

        // Recipients
        $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
        $mail->addAddress($user->email, $user->personalData->first_name . ' ' . $user->personalData->last_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to the Cooperative!';

        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0891b2 0%, #10b981 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                    .welcome-box { background: #dbeafe; border-left: 4px solid #0891b2; padding: 15px; margin: 20px 0; }
                    .credentials { background: white; border: 1px solid #e5e7eb; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .footer { background: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #0891b2 0%, #10b981 100%); color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎉 Welcome to the Cooperative!</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$user->personalData->first_name} {$user->personalData->last_name}</strong>,</p>
                        
                        <div class='welcome-box'>
                            <strong>Welcome!</strong> Your member account has been successfully created.
                        </div>
                        
                        <p>We're excited to have you as part of our cooperative family. Your membership gives you access to various benefits and services.</p>
                        
                        <div class='credentials'>
                            <p><strong>Your Login Credentials:</strong></p>
                            <p><strong>Email:</strong> {$user->email}</p>
                            <p><strong>Password:</strong> {$plainPassword}</p>
                            <p style='color: #dc2626; font-size: 14px;'><em>⚠️ Please change your password after your first login for security.</em></p>
                        </div>
                        
                        <p>You can now log in to access your account and explore our services.</p>
                        
                        <center>
                            <a href='" . url('/login') . "' class='button'>Login to Your Account</a>
                        </center>
                        
                        <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Cooperative Management System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
    }
}
