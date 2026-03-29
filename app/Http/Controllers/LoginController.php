<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Employment;
use App\Models\MemberId;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Attempt login
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate(); // prevents session fixation

            $user = Auth::user();

            // Check for inactive/terminated accounts
            if (in_array($user->role, ['inactive', 'terminated'])) {
                Auth::logout();

                // Return JSON for AJAX or redirect for regular form submission
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not active.',
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Your account is not active.',
                ]);
            }

            // Role-based redirects
            $redirectUrl = match ($user->role) {
                'admin' => '/admin/dashboard',
                'manager' => '/manager/dashboard',
                'member' => '/member/dashboard',
                'secretary' => '/secretary/dashboard',
                'loan' => '/loan/dashboard',
                'treasurer' => 'treasurer/dashboard',
                //remove if in prod!
                'developer' => '/dev/home',
                default => 'member/dashboard'
            };

            // Return JSON for AJAX or redirect for regular form submission
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => $redirectUrl,
                ]);
            }

            return redirect()->intended($redirectUrl);
        }

        // Authentication failed
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function register(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            // Personal Information
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female,Other',
            'birthdate' => 'required|date|before:today',
            'age' => 'required|integer|min:18',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:users,email|unique:contacts,email',
            'address' => 'required|string',

            // Employment & Income
            'occupation' => 'required|string|max:255',
            'monthly_income' => 'required|string',

            // Membership Details
            'membership_type' => 'required|in:Regular,Associate',
            'referred_by' => 'nullable|string|max:255',

            // Account Credentials
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => 'required|accepted',
        ]);

        try {
            // Use database transaction to ensure all records are created or none
            DB::beginTransaction();

            // Parse the address into components (you may need to adjust this based on your address format)
            $addressParts = $this->parseAddress($validated['address']);

            // 1. Create Address
            $address = Address::create([
                'country' => $addressParts['country'] ?? 'Philippines',
                'region' => $addressParts['region'] ?? null,
                'province' => $addressParts['province'] ?? null,
                'city_municipality' => $addressParts['city'] ?? null,
                'barangay' => $addressParts['barangay'] ?? null,
                'street' => $addressParts['street'] ?? $validated['address'],
                'zip_code' => $addressParts['zip'] ?? null,
            ]);

            // 2. Create Contact
            $contact = Contact::create([
                'phone_no' => $validated['phone'],
                'email' => $validated['email'],
                'facebook' => null,
            ]);

            // 3. Create Employment
            $employment = Employment::create([
                'employment_type' => 'Employed', // Default, can be updated later
                'employment_status' => 'Active',
                'position' => $validated['occupation'],
                'monthly_income' => $this->parseMonthlyIncome($validated['monthly_income']),
                'industry' => null, // Can be added later by admin
            ]);

            // 4. Create Member ID placeholder
            $memberId = MemberId::create([
                'tin' => null,
                'sss' => null,
                'philhealth' => null,
                'barangay_id' => null,
            ]);

            // 5. Create Personal Data
            $personalData = PersonalData::create([
                'first_name' => $validated['first_name'],
                'mid_name' => $validated['middle_name'],
                'last_name' => $validated['last_name'],
                'birth_date' => $validated['birthdate'],
                'gender' => $validated['gender'],
                'civil_status' => 'Single', // Default, can be updated later
                'address_id' => $address->id,
                'contact_id' => $contact->id,
                'employment_id' => $employment->id,
                'member_id' => $memberId->id,
            ]);

            // 6. Create User Account
            $user = User::create([
                'personal_data_id' => $personalData->id,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'member', // Default role for new registrations
                'special_loan_eligible' => false,
            ]);

            // 7. Create Initial Savings Account (₱500 initial deposit)
            Account::create([
                'user_id' => $user->id,
                'account_type' => 'savings',
                'balance' => 500.00, // Initial deposit
            ]);

            // 8. Create Share Capital Account (₱500 minimum)
            Account::create([
                'user_id' => $user->id,
                'account_type' => 'share_capital',
                'balance' => 500.00, // Minimum share capital
            ]);

            // Commit the transaction
            DB::commit();

            // Redirect to login page with success message
            return redirect()->route('login_page')->with(
                'success',
                'Registration successful! Your account is pending approval. You will be notified once approved.'
            );
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();

            // Log the error for debugging
            Log::error('Registration Error: ' . $e->getMessage());

            // Return back with error
            return back()->withInput()->withErrors([
                'error' => 'An error occurred during registration. Please try again or contact support.'
            ]);
        }
    }

    /**
     * Parse address string into components
     * This is a basic implementation - adjust based on your address format
     */
    private function parseAddress($addressString)
    {
        // Basic parsing - you can enhance this based on your needs
        $parts = array_map('trim', explode(',', $addressString));

        return [
            'street' => $parts[0] ?? $addressString,
            'barangay' => $parts[1] ?? null,
            'city' => $parts[2] ?? null,
            'province' => $parts[3] ?? null,
            'region' => null,
            'zip' => null,
            'country' => 'Philippines',
        ];
    }

    /**
     * Parse monthly income range to decimal value
     * Uses the midpoint of the range for storage
     */
    private function parseMonthlyIncome($incomeRange)
    {
        return match ($incomeRange) {
            'Below 10000' => 8000.00,
            '10000-20000' => 15000.00,
            '20001-30000' => 25000.00,
            '30001-50000' => 40000.00,
            '50001-75000' => 62500.00,
            'Above 75000' => 100000.00,
            default => 0.00,
        };
    }
}
