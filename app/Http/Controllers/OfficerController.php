<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OfficerController extends Controller
{
    // Display officer dashboard with members list by default
    public function index()
    {
        $user = Auth::user();

        // Add the name of the officer to the user 
        $first_name = optional($user->personalData)->first_name;
        $last_name = optional($user->personalData)->last_name;
        // combine the name
        $user->name = trim($first_name.' '.$last_name);

        // Get members list with personal data and contact info using eager loading
        $members = User::with(['personalData', 'contact'])->get();

        // Return the officer dashboard view
        return view('officer.dashboard', compact('user', 'members'));
    }

    // AJAX endpoint to refresh members data
    public function refreshMembers()
    {
        // Fetch members with related personal data and contact info
        $members = User::with(['personalData', 'contact'])
            ->where('role', 'member')
            ->get()
            // Again, thank you Claude AI
            ->map(function ($member) {
                return [
                    'user_id' => $member->user_id,
                    'name' => trim(optional($member->personalData)->first_name.' '.optional($member->personalData)->last_name),
                    'email' => optional($member->contact)->email ?? 'N/A',
                    'phone_no' => optional($member->contact)->phone_no ?? 'N/A',
                    'role' => ucfirst($member->role),
                ];
            });

        return response()->json($members);
    }

    // AJAX endpoint to refresh accounts data
    public function refreshAccounts()
    {
        // Fetch all accounts with user and personal data relationships
        $accounts = Account::with(['user.personalData'])
            ->get()
            ->map(function ($account) {
                return [
                    'account_id' => $account->account_id,
                    'owner' => trim(optional($account->user->personalData)->first_name.' '.optional($account->user->personalData)->last_name),
                    'account_type' => $account->account_type,
                    'balance' => number_format($account->balance, 2),
                ];
            });

        return response()->json($accounts);
    }

    // AJAX endpoint to refresh loans data
    public function refreshLoans()
    {
        // Fetch all loans with account and user relationships
        $loans = Loan::with(['account.user.personalData'])
            ->get()
            ->map(function ($loan) {
                return [
                    'loan_id' => $loan->loan_id,
                    'borrower' => trim(optional($loan->account->user->personalData)->first_name.' '.optional($loan->account->user->personalData)->last_name),
                    'loan_amount' => number_format($loan->loan_amount, 2),
                    'loan_status' => ucfirst($loan->loan_status),
                    'due_date' => $loan->due_date,
                ];
            });

        return response()->json($loans);
    }
}
