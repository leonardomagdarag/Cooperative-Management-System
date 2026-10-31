<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Map of dashboard sections to required permissions or roles.
     */
    private const SECTION_PERMISSIONS = [
        'members' => 'members.view',
        'loans' => 'loans.view',
        'reports' => 'reports.view',
        'services' => 'services.view',
        'officers' => 'officers.view',
        'branches' => 'branches.view',
        'ledger' => 'transactions.view',
        'expenses' => 'transactions.view',
        'logs' => 'logs.view',
        'announcements' => 'announcements.view',
        'role-permissions' => 'officers.manage-permissions',
        'loan-products' => 'settings.update',
        'public-content' => 'settings.update',
        'backups' => 'backups.view',
    ];

    public function index(Request $request, ?string $section = null): Response|RedirectResponse
    {
        if ($section !== null && $section !== '') {
            $baseSection = explode('/', trim($section, '/'))[0];
            $requirement = self::SECTION_PERMISSIONS[$baseSection] ?? null;

            if ($requirement) {
                $user = Auth::user();

                if (str_starts_with($requirement, 'role:')) {
                    $role = substr($requirement, 5);
                    if (! $user || (! $user->hasRole($role) && $user->primary_role !== $role)) {
                        return redirect()->route('dashboard')->with('error', 'Access restricted.');
                    }
                } elseif (! $user || ! $user->can($requirement)) {
                    return redirect()->route('dashboard')->with('error', 'Access restricted.');
                }
            }
        }

        // The dashboard is rendered by the React SPA. This route is protected
        // server-side, then hands the request to the SPA shell for both initial
        // navigation and browser reloads.
        return response()->view('react');
    }
}
