<?php

namespace App\Traits;

use App\Support\PersonPasswordPolicy;

/**
 * Shared person-form validation helpers used by Members and Officers components.
 *
 * Components using this trait must implement:
 *   - getValidationRules(): array
 *   - updateTabErrors(): void
 */
trait ValidatesPersonForm
{
    protected function getPersonNameTokens(): array
    {
        return PersonPasswordPolicy::normalizedNameTokens([
            $this->form['first_name'] ?? '',
            $this->form['mid_name'] ?? '',
            $this->form['last_name'] ?? '',
        ]);
    }

    protected function passwordLooksTooPredictable(string $password): bool
    {
        return PersonPasswordPolicy::looksPredictable($password, $this->getPersonNameTokens());
    }

    protected function getPasswordValidationRules(): array
    {
        return [
            'form.password' => PersonPasswordPolicy::passwordRules($this->isEdit, $this->getPersonNameTokens()),
            'form.password_confirmation' => PersonPasswordPolicy::confirmationRules($this->isEdit),
        ];
    }

    /**
     * Centralized validation messages for all person-form fields.
     * Covers the superset of both Members and Officers fields; unused keys are harmless.
     */
    protected function getCustomMessages(): array
    {
        return [
            // Name fields
            'form.first_name.required' => 'First name is required.',
            'form.first_name.regex'    => 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.last_name.required'  => 'Last name is required.',
            'form.last_name.regex'     => 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.mid_name.regex'      => 'Middle name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'form.suffix.regex'        => 'Suffix can only contain letters, spaces, hyphens, apostrophes, and periods.',
            // Contact
            'form.email.required'      => 'Email address is required.',
            'form.email.email'         => 'Please enter a valid email address.',
            'form.email.unique'        => 'This email address is already registered.',
            'form.phone_number.required' => 'Phone number is required.',
            'form.phone_number.max'    => 'Phone number must be 11 digits or less.',
            'form.mobile_number.max'   => 'Mobile number must be 11 digits or less.',
            // Personal identity
            'form.birth_date.required' => 'Date of birth is required.',
            'form.birth_date.date'     => 'Please enter a valid date.',
            'form.birth_date.before'   => 'Birth date must be before today.',
            'form.gender.required'     => 'Gender is required.',
            'form.civil_status.required' => 'Civil status is required.',
            // Officer-specific role fields (harmless in Members context)
            'form.title.required'      => 'Officer title is required.',
            'form.role.required'       => 'System role is required.',
            // Password
            'form.password.required'              => 'Password is required.',
            'form.password.min'                   => 'Password must be at least ' . PersonPasswordPolicy::MIN_LENGTH . ' characters.',
            'form.password.regex'                 => 'Password must include both letters and numbers.',
            'form.password.confirmed'             => PersonPasswordPolicy::confirmationMismatchMessage(),
            'form.password_confirmation.required' => PersonPasswordPolicy::confirmationRequiredMessage(),
            'form.password_confirmation.required_with' => 'Please confirm your password.',
            'form.password_confirmation.same'     => PersonPasswordPolicy::confirmationMismatchMessage(),
            'form.current_password.required'      => 'Current password is required.',
            'form.current_password.current_password' => 'The current password is incorrect.',
        ];
    }

    /**
     * Validate a single Livewire field on blur.
     * Requires the component to implement getValidationRules() and updateTabErrors().
     */
    public function validateField(string $field): void
    {
        $rules = $this->getValidationRules();

        if (isset($rules[$field])) {
            $this->validateOnly($field, $rules, $this->getCustomMessages());
            $this->updateTabErrors();
        }
    }
}
