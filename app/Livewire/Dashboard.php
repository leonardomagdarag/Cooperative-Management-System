<?php

namespace App\Livewire;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Url(as: 'section', history: true)]
    public $currentSection = 'dashboard';

    public $isLoading = false;

    // protected $adminSections, protected $managerSections, protected $memberSections, $loanSections, $treasurerSections, $secretarySections;

    protected $listeners = [
        'sectionChanged' => 'updateSection',
    ];

    #[On('open-notifications-inbox')]
    public function openNotificationsInbox(): void
    {
        $this->updateSection('notifications');
    }

    private function isPermissionManagedRole(string $role): bool
    {
        return in_array($role, ['admin', 'manager', 'loan', 'secretary', 'treasurer'], true);
    }

    private function getUserSections($user): array
    {
        $userRole = $user->getRoleNames()->first() ?? 'member';
        $roleSections = config("settings.sections.{$userRole}", []);

        // Keep member/inactive/terminated behavior role-based.
        if (!$this->isPermissionManagedRole($userRole)) {
            return $roleSections;
        }

        // Officer/admin/manager sections should be permission-aware.
        $sectionPermissionMap = [
            'dashboard' => ['dashboard.view'],
            'notifications' => ['dashboard.view'],
            'officers' => ['officers.create', 'officers.update', 'officers.delete', 'officers.manage-permissions'],
            'members' => ['members.view'],
            'services' => ['services.view', 'services.create', 'services.update'],
            'loans' => ['loans.view', 'loans.create', 'loans.update', 'loans.approve', 'loans.reject', 'loans.disburse'],
            'transactions' => ['transactions.view', 'transactions.create', 'transactions.update', 'transactions.void', 'transactions.export'],
            'ledger' => ['transactions.view', 'deposits.view', 'withdrawals.view'],
            'announcements' => ['announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete'],
            'reports' => ['reports.view', 'reports.export', 'reports.financial', 'reports.audit'],
            'logs' => ['logs.view', 'logs.export'],
            'settings' => ['settings.view', 'settings.update'],
            'loan-products' => ['settings.view', 'settings.update'],
            'role-permissions' => ['settings.view', 'settings.update'],
        ];

        $resolved = [];
        foreach ($roleSections as $section) {
            if ($section === 'logs' && $userRole !== 'admin') {
                continue;
            }

            // Dashboard and notifications are always accessible to any authenticated user.
            if (in_array($section, ['dashboard', 'notifications', 'profile', 'settings'], true)) {
                $resolved[] = $section;
                continue;
            }

            if (!isset($sectionPermissionMap[$section])) {
                // Permission-managed roles can only access sections with explicit permission mapping.
                continue;
            }

            $canAccessSection = collect($sectionPermissionMap[$section])
                ->some(fn($permission) => $user->can($permission));

            if ($canAccessSection) {
                $resolved[] = $section;
            }
        }

        return array_values(array_unique($resolved));
    }

    public function render()
    {
        //get user role
        $user = Auth::user();
        $userRole = $user->getRoleNames()->first() ?? 'member';
        $userSections = $this->getUserSections($user);

        if (!empty($userSections) && !in_array($this->currentSection, $userSections, true)) {
            $this->currentSection = $userSections[0];
        }

        // Map 'dashboard' section to role-specific overview component
        $sectionMapping = [
            'member' => 'overviews.member-overview',
            'admin' => 'overviews.admin-overview',
            'manager' => 'overviews.manager-overview',
            'loan' => 'overviews.loan-overview',
            'secretary' => 'overviews.secretary-overview',
            'treasurer' => 'overviews.treasurer-overview',
        ];

        $currentSectionPath = $this->currentSection === 'dashboard' && isset($sectionMapping[$userRole])
            ? $sectionMapping[$userRole]
            : $this->currentSection;

        return view('livewire.dashboard', [
            'userRole' => $userRole,
            'userSections' => $userSections,
            'currentSectionPath' => $currentSectionPath,
        ]);
    }

    public function updateSection($newSection)
    {
        $this->isLoading = true;

        // Authorization check here if needed
        // mount method won't work... manual it is :(
        if (! Auth::check()) {
            $this->isLoading = false;
            return;
        }

        $user = Auth::user();
        $allowedSections = $this->getUserSections($user);

        if (in_array($newSection, $allowedSections, true)) {
            $this->currentSection = $newSection;
        } elseif ($newSection === 'profile' && Auth::check()) {
            // Profile is always accessible to any authenticated user viewing their own data.
            $this->currentSection = $newSection;
        } else {
            // Optional: unauthorized attempt
            $this->isLoading = false;
            abort(403);
        }

        $this->isLoading = false;
    }

    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            app(AuditLogService::class)->log(
                'auth.logout',
                'User logged out from dashboard.',
                'auth',
                $user,
                $user
            );
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }
}
