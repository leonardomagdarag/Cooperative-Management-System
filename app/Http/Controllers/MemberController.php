<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    // Display member dashboard with account balance and active loans
    public function index()
    {
        $user = Auth::user();
        $account = $user->accounts()->first(); // Get user's first account (financial...)
        $loans = $account ? $account->loans()->where('loan_status', 'active')->get() : collect(); // Get only active loans

        // Pass data to the view
        return view('dashboard', compact('user', 'account', 'loans'));
    }

    // AJAX endpoint to refresh account and loan data without page reload
    public function refreshData()
    {
        $user = Auth::user();
        $account = $user->accounts()->first(); // Get user' first account
        $loans = $account ? $account->loans()->where('loan_status', 'active')->get() : collect();

        // Return JSON response with formatted data
        return response()->json([
            'balance' => $account ? number_format($account->balance, 2) : '0.00',

            // Shout out to Claude AI
            'loans' => $loans->map(function ($loan) {
                return [
                    'loan_type' => $loan->loan_type,
                    'loan_amount' => number_format($loan->loan_amount, 2),
                    'interest_rate' => $loan->interest_rate,
                    'loan_status' => $loan->loan_status,
                    'due_date' => $loan->due_date,
                ];
            })->values()->toArray(), // Convert collection to array
        ]);
    }
}
