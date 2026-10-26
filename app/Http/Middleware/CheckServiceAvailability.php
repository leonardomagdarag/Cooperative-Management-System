<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;

class CheckServiceAvailability
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $service)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // The approval endpoint handles several transaction types. Resolve
        // the service from the bound transaction so approvals obey the same
        // availability switches as submissions.
        if ($service === 'transaction') {
            $transaction = $request->route('transaction');
            abort_unless($transaction instanceof Transaction, 404);

            $service = match ($transaction->transaction_type) {
                'deposit' => 'deposit',
                'withdrawal' => 'withdrawal',
                'loan_payment' => 'loan_payment',
                // Ledger-only entries such as fees do not have a member service
                // availability switch. Their own approval permission still applies.
                default => null,
            };

            if ($service === null) {
                return $next($request);
            }
        }

        $isOfficer = (bool) ($user->is_officer
            || $user->hasRole('admin')
            || $user->hasAnyRole(PermissionCatalog::officerRoles()));
        $userType = $isOfficer ? 'officer' : 'member';
        $transactionMode = $request->input('transaction_mode');

        if (! $this->isServiceAvailable($service, $userType, $transactionMode)) {
            return response()->json([
                'message' => 'This service is currently under maintenance. Please try again later.',
                'service' => $service,
                'maintenance' => true,
            ], 423);
        }

        return $next($request);
    }

    /**
     * Check if a service is available for a user type and transaction mode.
     * Each toggle is evaluated independently (Option B).
     */
    private function isServiceAvailable(string $service, string $userType, ?string $transactionMode = null): bool
    {
        $globalKey = "service_{$service}_enabled";
        $userKey = "service_{$service}_{$userType}_enabled";

        if (! (bool) SystemSetting::get($globalKey, true)) {
            return false;
        }

        if (! (bool) SystemSetting::get($userKey, true)) {
            return false;
        }

        if ($transactionMode && in_array($service, ['deposit', 'withdrawal', 'loan_payment'], true)) {
            $modeKey = "service_{$service}_{$transactionMode}_enabled";
            if (! (bool) SystemSetting::get($modeKey, true)) {
                return false;
            }
        }

        return true;
    }
}
