<?php

namespace App\Livewire\Content;

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Officers Component
 *
 * Manages officer CRUD operations with extended personal data including gender,
 * civil status, and role management. Similar to Members but with additional fields
 * and stricter permission controls.
 */
class Officers extends Component
{
    use WithPagination;

    // Search and filtering
    public $search = '';

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    // Row selection
    public $selectedRows = [];

    public $selectAll = false;

    // Modal states
    public $showModal = false;

    public $selectedOfficer = null;

    public $showEditModal = false;

    public $isEdit = false;

    public $confirmingDelete = false;

    public $officerToDelete = null;

    public $activeTab = 'overview';

    // Permissions modal
    public $showPermissionsModal = false;

    public $selectedOfficerForPermissions = null;

    // Form data array
    public $form = [];

    public $editingOfficerId = null;

    public $modalKey = 0;

    public $profileModalKey = 0;

    // Track tab validation errors
    public $tabErrors = [
        'personal' => false,
        'contact' => false,
        'login' => false,
    ];

    protected $listeners = [
        'permission-saved' => 'onPermissionSaved',
        'permission-manager-close' => 'closePermissionsModal',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    /**
     * Validate individual field on blur
     */
    public function validateField($field)
    {
        $rules = $this->getValidationRules();

        if (isset($rules[$field])) {
            $this->validateOnly($field, $rules, $this->getCustomMessages());
            $this->updateTabErrors();
        }
    }

    /**
     * Get validation rules
     */
    private function getValidationRules()
    {
        return [
            'form.first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.mid_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s\-\'.]+$/'],
            'form.email' => 'required|email|unique:users,email' . ($this->isEdit && $this->selectedOfficer ? ',' . $this->selectedOfficer->id . ',id' : ''),
            'form.phone_number' => 'nullable|string|max:11',
            'form.birth_date' => 'required|date|before:today',
            'form.gender' => 'required|in:male,female,other',
            'form.civil_status' => 'required|in:single,married,widowed,separated',
            'form.title' => 'required|in:Chairman,Vice Chairman,Secretary,Treasurer,Loan Officer,Member',
            'form.role' => 'required|in:admin,manager,secretary,treasurer,loan',
            'form.password' => $this->isEdit ? 'nullable|min:6|confirmed' : 'required|min:6|confirmed',
            'form.password_confirmation' => $this->isEdit ? 'nullable' : 'required',
        ];
    }

    /**
     * Get custom validation messages
     */
    private function getCustomMessages()
    {
        return [
            'form.first_name.required' => 'First name is required.',
            'form.first_name.regex' => 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.last_name.required' => 'Last name is required.',
            'form.last_name.regex' => 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.mid_name.regex' => 'Middle name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.email.required' => 'Email address is required.',
            'form.email.email' => 'Please enter a valid email address.',
            'form.email.unique' => 'This email address is already registered.',
            'form.phone_number.max' => 'Phone number must be 11 digits or less.',
            'form.birth_date.required' => 'Date of birth is required.',
            'form.birth_date.before' => 'Birth date must be before today.',
            'form.gender.required' => 'Gender is required.',
            'form.civil_status.required' => 'Civil status is required.',
            'form.title.required' => 'Officer title is required.',
            'form.role.required' => 'Role is required.',
            'form.password.required' => 'Password is required.',
            'form.password.min' => 'Password must be at least 6 characters.',
            'form.password.confirmed' => 'Password confirmation does not match.',
            'form.password_confirmation.required' => 'Please confirm your password.',
        ];
    }

    /**
     * Update tab error indicators
     */
    private function updateTabErrors()
    {
        $errors = $this->getErrorBag();

        $personalFields = [
            'form.first_name',
            'form.last_name',
            'form.mid_name',
            'form.birth_date',
            'form.gender',
            'form.civil_status',
            'form.title',
            'form.role'
        ];
        $this->tabErrors['personal'] = collect($personalFields)->some(fn($field) => $errors->has($field));

        $contactFields = [
            'form.email',
            'form.phone_number',
            'form.street',
            'form.barangay',
            'form.city',
            'form.postal_code'
        ];
        $this->tabErrors['contact'] = collect($contactFields)->some(fn($field) => $errors->has($field));

        $loginFields = ['form.password', 'form.password_confirmation'];
        $this->tabErrors['login'] = collect($loginFields)->some(fn($field) => $errors->has($field));
    }

    /**
     * Check if form is valid
     */
    public function getIsFormValidProperty()
    {
        $requiredFilled = !empty($this->form['first_name']) &&
            !empty($this->form['last_name']) &&
            !empty($this->form['email']) &&
            !empty($this->form['role']);

        if (!$this->isEdit) {
            $requiredFilled = $requiredFilled &&
                !empty($this->form['password']) &&
                !empty($this->form['password_confirmation']);
        }

        $noErrors = $this->getErrorBag()->isEmpty();

        return $requiredFilled && $noErrors;
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function viewProfile($userId)
    {
        $this->selectedOfficer = User::with([
            'personalData.address',
            'personalData.contact',
            'personalData.employment',
            'personalData.memberId'
        ])->find($userId);
        $this->activeTab = 'overview';
        $this->profileModalKey = now()->timestamp . rand(1000, 9999);
        $this->showModal = true;
    }

    /**
     * Change active tab in profile modal
     */
    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedOfficer = null;
        $this->activeTab = 'overview';
    }

    /**
     * Initialize form for adding new officer
     *
     * Key difference from addMember(): Pre-initializes all form fields with empty values
     * to ensure proper wire:model binding in officer-form component, especially for
     * gender, civil_status, and role dropdowns.
     */
    public function addOfficer()
    {
        $this->resetValidation();
        $this->isEdit = false;
        $this->form = [
            'first_name' => '',
            'mid_name' => '',
            'last_name' => '',
            'birth_date' => '',
            'gender' => 'male',         // Officer-specific: default to male
            'civil_status' => 'single', // Officer-specific: default to single
            'title' => 'Member',        // Officer-specific: default title
            'email' => '',
            'phone_number' => '',
            'street' => '',
            'barangay' => '',
            'city' => '',
            'postal_code' => '',
            'role' => 'secretary',      // Officer-specific: defaults to secretary role
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->modalKey = now()->timestamp . rand(1000, 9999);
        $this->showEditModal = true;
    }

    /**
     * Load officer data into form for editing
     *
     * Note: birth_date requires special handling for DateTimeInterface conversion
     * to ensure proper date input field population.
     */
    public function editOfficer($userId)
    {
        $this->resetValidation();
        $this->isEdit = true;
        $this->editingOfficerId = $userId;
        $this->modalKey = now()->timestamp . rand(1000, 9999);
        $this->showModal = false;
        $this->selectedOfficer = null;
        $officer = User::with(['personalData.address', 'personalData.contact'])->find($userId);

        $this->selectedOfficer = $officer;
        $this->form = [
            'first_name' => $officer->personalData->first_name ?? '',
            'mid_name' => $officer->personalData->mid_name ?? '',
            'last_name' => $officer->personalData->last_name ?? '',
            // Handle DateTimeInterface to string conversion for date input
            'birth_date' => ($officer->personalData->birth_date instanceof \DateTimeInterface) ? $officer->personalData->birth_date->format('Y-m-d') : ($officer->personalData->birth_date ?? ''),
            'gender' => $officer->personalData->gender ?? '',
            'civil_status' => $officer->personalData->civil_status ?? '',
            'title' => $officer->personalData->title ?? 'Member',
            'email' => $officer->email,
            'phone_number' => $officer->personalData->contact->phone_no ?? '',
            'street' => $officer->personalData->address->street ?? '',
            'barangay' => $officer->personalData->address->barangay ?? '',
            'city' => $officer->personalData->address->city_municipality ?? '',
            'postal_code' => $officer->personalData->address->zip_code ?? '',
            'role' => $officer->role,  // User's system role (admin, manager, etc.)
        ];

        $this->showEditModal = true;
    }

    /**
     * Save officer data (create or update)
     *
     * Extended version of saveMember() with additional fields:
     * - gender: Demographic data stored in personal_data table
     * - civil_status: Demographic data stored in personal_data table
     * - role: System role (admin/manager/secretary/treasurer/loan) with validation
     *
     * Uses database transaction to ensure data integrity across multiple tables.
     */
    public function saveOfficer()
    {
        // Validate with officer-specific fields
        $this->validate($this->getValidationRules(), $this->getCustomMessages());
        $this->updateTabErrors();

        DB::beginTransaction();
        try {
            if ($this->isEdit) {
                // Update existing officer
                $officer = $this->selectedOfficer;

                // Update contact
                if ($officer->personalData->contact) {
                    $officer->personalData->contact->update([
                        'phone_no' => $this->form['phone_number'] ?? null,
                        'email' => $this->form['email'],
                    ]);
                } else {
                    $contact = Contact::create([
                        'phone_no' => $this->form['phone_number'] ?? null,
                        'email' => $this->form['email'],
                    ]);
                    $officer->personalData->update(['contact_id' => $contact->id]);
                }

                // Update address
                if ($officer->personalData->address) {
                    $officer->personalData->address->update([
                        'street' => $this->form['street'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                } elseif (! empty($this->form['street']) || ! empty($this->form['city'])) {
                    $address = Address::create([
                        'street' => $this->form['street'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                    $officer->personalData->update(['address_id' => $address->id]);
                }

                // Update personal data including officer-specific demographic fields
                $officer->personalData->update([
                    'first_name' => $this->form['first_name'],
                    'last_name' => $this->form['last_name'],
                    'mid_name' => !empty($this->form['mid_name']) ? $this->form['mid_name'] : null,
                    'birth_date' => !empty($this->form['birth_date']) ? $this->form['birth_date'] : null,
                    'gender' => !empty($this->form['gender']) ? $this->form['gender'] : null,
                    'civil_status' => !empty($this->form['civil_status']) ? $this->form['civil_status'] : null,
                    'title' => !empty($this->form['title']) ? $this->form['title'] : null,
                ]);

                // Update user record including role (key difference from members)
                $updateData = [
                    'email' => $this->form['email'],
                    'role' => $this->form['role'],  // Role can be changed for officers
                ];
                if (! empty($this->form['password'])) {
                    $updateData['password'] = Hash::make($this->form['password']);
                }
                $officer->update($updateData);

                $this->dispatch('toast', ['message' => 'Officer updated successfully!', 'type' => 'success']);
            } else {
                // Create new officer
                $contact = Contact::create([
                    'phone_no' => $this->form['phone_number'] ?? null,
                    'email' => $this->form['email'],
                ]);

                $address = null;
                if (! empty($this->form['street']) || ! empty($this->form['city'])) {
                    $address = Address::create([
                        'street' => $this->form['street'] ?? null,
                        'barangay' => $this->form['barangay'] ?? null,
                        'city_municipality' => $this->form['city'] ?? null,
                        'zip_code' => $this->form['postal_code'] ?? null,
                    ]);
                }

                // Create personal data with officer-specific demographic fields
                $personalData = PersonalData::create([
                    'first_name' => $this->form['first_name'],
                    'last_name' => $this->form['last_name'],
                    'mid_name' => !empty($this->form['mid_name']) ? $this->form['mid_name'] : null,
                    'birth_date' => !empty($this->form['birth_date']) ? $this->form['birth_date'] : null,
                    'gender' => !empty($this->form['gender']) ? $this->form['gender'] : null,
                    'civil_status' => !empty($this->form['civil_status']) ? $this->form['civil_status'] : null,
                    'title' => !empty($this->form['title']) ? $this->form['title'] : null,
                    'contact_id' => $contact->id,
                    'address_id' => $address ? $address->id : null,
                ]);

                // Create user with role from form (not hardcoded like members)
                User::create([
                    'personal_data_id' => $personalData->id,
                    'email' => $this->form['email'],
                    'password' => Hash::make($this->form['password']),
                    'role' => $this->form['role'],  // Role selected from dropdown
                ]);

                $this->dispatch('toast', ['message' => 'Officer created successfully!', 'type' => 'success']);
            }

            DB::commit();
            $this->closeEditModal();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Officer save error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('toast', ['message' => 'An error occurred: ' . $e->getMessage(), 'type' => 'error']);
        }
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->isEdit = false;
        $this->form = [];
        $this->selectedOfficer = null;
        $this->editingOfficerId = null;
        $this->resetValidation();
    }

    /**
     * Confirm officer deletion
     */
    public function confirmDelete($userId)
    {
        // Prevent officers from deleting their own account
        if (Auth::id() == $userId) {
            $this->dispatch('toast', ['message' => 'You cannot delete your own account.', 'type' => 'error']);
            return;
        }

        $this->officerToDelete = $userId;
        $this->confirmingDelete = true;
    }

    /**
     * Cancel deletion
     */
    public function cancelDelete()
    {
        $this->confirmingDelete = false;
        $this->officerToDelete = null;
    }

    /**
     * Soft delete officer
     * 
     * Performs soft delete on User and PersonalData records.
     * Soft-deleted records can be archived after a period via scheduled job.
     */
    public function deleteOfficer()
    {
        if (!$this->officerToDelete) {
            return;
        }

        try {
            $officer = User::find($this->officerToDelete);

            if (!$officer) {
                $this->dispatch('toast', ['message' => 'Officer not found.', 'type' => 'error']);
                return;
            }

            // Soft delete the user (cascades via model events if needed)
            $officer->delete();

            // Optionally soft delete personal data as well
            if ($officer->personalData) {
                $officer->personalData->delete();
            }

            $this->dispatch('toast', ['message' => 'Officer deleted successfully!', 'type' => 'success']);
            $this->confirmingDelete = false;
            $this->officerToDelete = null;

            // Close modal if viewing deleted officer
            if ($this->selectedOfficer && $this->selectedOfficer->id === $officer->id) {
                $this->closeModal();
            }
        } catch (\Exception $e) {
            \Log::error('Officer delete error: ' . $e->getMessage());
            $this->dispatch('toast', ['message' => 'Failed to delete officer.', 'type' => 'error']);
        }
    }

    /**
     * Open permissions manager for an officer
     */
    public function managePermissions($officerId)
    {
        $user = Auth::user();
        if (!$user || !$user->can('officers.manage-permissions')) {
            $this->dispatch('toast', ['message' => 'You do not have permission to manage officer permissions.', 'type' => 'error']);
            return;
        }

        $this->selectedOfficerForPermissions = $officerId;
        $this->showPermissionsModal = true;
    }

    /**
     * Close permissions modal
     */
    /**
     * Handle permission saved event from OfficerPermissionManager
     */
    public function onPermissionSaved($message = 'Permissions updated successfully.')
    {
        if (is_array($message)) {
            $message = $message['message'] ?? 'Permissions updated successfully.';
        }

        $this->dispatch('toast', ['message' => $message, 'type' => 'success']);
        $this->closePermissionsModal();

        // Refresh the officer profile if viewing
        if ($this->selectedOfficer) {
            $this->selectedOfficer = $this->selectedOfficer->fresh();
        }
    }

    /**
     * Close permissions modal
     */
    public function closePermissionsModal()
    {
        $this->showPermissionsModal = false;
        $this->selectedOfficerForPermissions = null;
    }

    /**
     * Render officers list with search, filter, and sort
     *
     * Key differences from Members render:
     * - Stricter permissions: only admin and manager can edit
     * - Filters by officer roles (admin, manager, secretary, treasurer, loan)
     * - Search includes role field in addition to names
     */
    public function render()
    {
        $user = Auth::user();
        $canEdit = $user && (
            $user->can('officers.create') ||
            $user->can('officers.update') ||
            $user->can('officers.delete') ||
            $user->can('officers.manage-permissions')
        );

        // Query officers only (excludes 'member' and 'inactive' roles)
        $query = User::with(['personalData.address', 'personalData.contact', 'accounts'])
            ->whereIn('role', ['admin', 'manager', 'secretary', 'treasurer', 'loan'])
            ->when($this->search, function ($query) {
                // Search by name OR role
                $query->whereHas('personalData', function ($q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('mid_name', 'like', '%' . $this->search . '%');
                })
                    ->orWhere('role', 'like', '%' . $this->search . '%');
            });

        // Special handling: JOIN required to sort by personal_data fields
        if (in_array($this->sortField, ['first_name', 'last_name', 'mid_name'])) {
            $query->join('personal_data', 'users.personal_data_id', '=', 'personal_data.id')
                ->orderBy('personal_data.' . $this->sortField, $this->sortDirection)
                ->select('users.*');  // Avoid column ambiguity
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $officers = $query->paginate(10);

        return view('livewire.content.officers', [
            'officers' => $officers,
            'canEdit' => $canEdit,
        ]);
    }
}
