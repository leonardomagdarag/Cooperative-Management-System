<?php

namespace App\Livewire;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    public function openNotificationsInbox(int $notificationId = 0): void
    {
        $this->updateSection('notifications');

        if ($notificationId > 0) {
            $this->dispatch('open-notification-modal', notificationId: $notificationId);
        }
    }

    private function isPermissionManagedUser($user): bool
    {
        return (bool) ($user && (($user->is_officer ?? false) || $user->hasRole('admin')));
    }

    private function getUserSections($user): array
    {
        if (! $user) {
            return [];
        }

        return Cache::remember(
            "dashboard.sections.user.{$user->id}",
            now()->addMinute(),
            fn() => $this->resolveUserSections($user)
        );
    }

    private function resolveUserSections($user): array
    {
        $userRole = $user->primary_role;

        // Non-officer roles (member, inactive, terminated) keep config-driven sections
        // as they have no Spatie permissions assigned.
        if (! $this->isPermissionManagedUser($user)) {
            return config("settings.sections.{$userRole}", []);
        }

        // Officer roles are fully driven by their database permissions (role permissions +
        // per-officer overrides). The config is no longer consulted so that overrides work
        // without needing a matching config entry.
        $sectionPermissionMap = [
            'officers'        => ['officers.view', 'officers.create', 'officers.update', 'officers.delete', 'officers.manage-permissions'],
            'members'         => ['members.view'],
            'services'        => ['services.view', 'services.create', 'services.update'],
            'loans'           => ['loans.view', 'loans.create', 'loans.update', 'loans.approve', 'loans.reject', 'loans.disburse'],
            'ledger'          => ['transactions.view', 'deposits.view', 'withdrawals.view'],
            'announcements'   => ['announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete'],
            'reports'         => ['reports.view', 'reports.export', 'reports.financial', 'reports.audit'],
            'logs'            => ['logs.view', 'logs.export'],
            'settings'        => ['settings.view', 'settings.update'],
            'public-content'  => ['settings.view', 'settings.update'],
            'loan-products'   => ['settings.view', 'settings.update'],
            'role-permissions' => ['settings.view', 'settings.update'],
        ];

        // These sections are always visible for any authenticated officer.
        $resolved = ['dashboard', 'notifications', 'profile'];

        foreach ($sectionPermissionMap as $section => $permissions) {
            // Logs are restricted to admin only regardless of permissions.
            if ($section === 'logs' && $userRole !== 'admin') {
                continue;
            }

            if (collect($permissions)->some(fn($permission) => $user->can($permission))) {
                $resolved[] = $section;
            }
        }

        return array_values(array_unique($resolved));
    }

    public function render()
    {
        //get user role
        $user = Auth::user();
        $userRole = $user->primary_role;
        $userSections = $this->getUserSections($user);

        if (!empty($userSections) && !in_array($this->currentSection, $userSections, true)) {
            $this->currentSection = $userSections[0];
        }

        // Map 'dashboard' section to role-specific overview component
        $sectionMapping = [
            'member' => 'overviews.member-overview',
            'admin' => 'overviews.admin-overview',
            'manager' => 'overviews.manager-overview',
            'accountant' => 'overviews.treasurer-overview',
            'secretary' => 'overviews.secretary-overview',
            'loan' => 'overviews.loan-overview',
        ];

        if ($this->currentSection === 'dashboard') {
            $currentSectionPath = $sectionMapping[$userRole]
                ?? ($this->isPermissionManagedUser($user) ? 'overviews.officer-overview' : $this->currentSection);
        } else {
            $currentSectionPath = $this->currentSection;
        }

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

        return redirect()->route('login_page');
    }
}
