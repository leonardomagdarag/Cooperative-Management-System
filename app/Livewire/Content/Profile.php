<?php

namespace App\Livewire\Content;

use App\Traits\ManagesOwnProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class Profile extends Component
{
    use ManagesOwnProfile;

    public string $activeTab = 'overview';

    public string $profileMode = 'member';

    public bool $showEditModal = false;

    public function mount(): void
    {
        $this->fillOwnProfileForm();
    }

    public function setTab(string $tab): void
    {
        $allowed = array_keys($this->getTabsForMode($this->profileMode));
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    public function openEditModal(): void
    {
        $this->resetValidation();
        $this->fillOwnProfileForm();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetValidation();
        $this->fillOwnProfileForm();
    }

    public function saveProfile(): void
    {
        $this->saveOwnProfile(
            'member.profile.update',
            'Member profile updated from profile page.',
        );

        $this->showEditModal = false;

        $this->dispatch('toast', [
            'message' => 'Profile updated successfully.',
            'type' => 'success',
        ]);
    }

    private function getProfileMode($user): string
    {
        return $user->hasRole('member') ? 'member' : 'officer';
    }

    private function getTabsForMode(string $mode): array
    {
        return $mode === 'member'
            ? [
                'overview' => ['icon' => 'fas fa-user', 'label' => 'Overview'],
                'contact' => ['icon' => 'fas fa-address-book', 'label' => 'Contact & Address'],
                'employment' => ['icon' => 'fas fa-briefcase', 'label' => 'Employment'],
                'financial' => ['icon' => 'fas fa-wallet', 'label' => 'Financial'],
                'beneficiaries' => ['icon' => 'fas fa-users', 'label' => 'Beneficiaries'],
                'security' => ['icon' => 'fas fa-shield-alt', 'label' => 'Security'],
            ]
            : [
                'overview' => ['icon' => 'fas fa-user', 'label' => 'Overview'],
                'contact' => ['icon' => 'fas fa-address-book', 'label' => 'Contact & Address'],
                'employment' => ['icon' => 'fas fa-briefcase', 'label' => 'Employment'],
                'security' => ['icon' => 'fas fa-shield-alt', 'label' => 'Security'],
            ];
    }

    public function render()
    {
        $user = Auth::user()->load([
            'personalData.address',
            'personalData.contact',
            'personalData.employment',
            'personalData.memberId',
            'accounts',
            'loans',
            'beneficiaries',
            'trustedDevices',
        ]);

        $this->profileMode = $this->getProfileMode($user);

        if (! in_array($this->activeTab, array_keys($this->getTabsForMode($this->profileMode)), true)) {
            $this->activeTab = 'overview';
        }

        $accounts    = $user->accounts;
        $totalBalance = $accounts->sum('balance');

        $loans = $user->loans()->latest()->take(5)->get();
        $activeLoans = $user->loans()->whereIn('loan_status', ['approved', 'disbursed', 'active'])->count();

        $recentLogs = Activity::query()
            ->where('causer_type', $user::class)
            ->where('causer_id', $user->id)
            ->latest()
            ->take(8)
            ->get();

        $trustedDevices = $user->trustedDevices()->latest()->take(5)->get();

        return view('livewire.content.profile', [
            'user'           => $user,
            'accounts'       => $accounts,
            'totalBalance'   => $totalBalance,
            'loans'          => $loans,
            'activeLoans'    => $activeLoans,
            'recentLogs'     => $recentLogs,
            'trustedDevices' => $trustedDevices,
            'tabs'           => $this->getTabsForMode($this->profileMode),
        ]);
    }
}
