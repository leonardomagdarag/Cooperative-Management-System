<?php

namespace App\Livewire\Content;

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Traits\ManagesOwnProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class Settings extends Component
{
    use ManagesOwnProfile;

    public string $activeMemberTab = 'profile';

    public bool $showPersonalAccountSettings = false;

    public array $passwordForm = [
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public array $accountForm = [
        'current_password' => '',
    ];

    public $profilePhotoUpload = null;

    public function mount(): void
    {
        if ($this->isMemberAccount()) {
            $this->fillOwnProfileForm();

            return;
        }
    }

    public function setMemberTab(string $tab): void
    {
        $allowed = ['profile', 'privacy', 'security', 'account', 'ideas'];

        if (in_array($tab, $allowed, true)) {
            $this->activeMemberTab = $tab;
        }
    }

    public function saveMemberProfile(): void
    {
        $this->saveOwnProfile(
            'member.profile.update',
            'Member profile updated from settings.',
        );

        $this->dispatch('toast', [
            'message' => 'Profile updated successfully.',
            'type' => 'success',
        ]);
    }

    public function changePassword(): void
    {
        $validated = $this->validate([
            'passwordForm.current_password' => ['required', 'current_password'],
            'passwordForm.password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->memberUser();
        $user->password = $validated['passwordForm']['password'];
        $user->remember_token = Str::random(60);
        $user->save();
        $user->invalidateOtherSessions(session()->getId());

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        app(AuditLogService::class)->log(
            'member.security.password-changed',
            'Member changed their password from settings.',
            'auth',
            $user,
            $user,
        );

        $this->passwordForm = [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ];

        $this->dispatch('toast', [
            'message' => 'Password updated. Trusted devices were revoked for safety.',
            'type' => 'success',
        ]);
    }

    public function revokeTrustedDevices(): void
    {
        $user = $this->memberUser();

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        $user->remember_token = Str::random(60);
        $user->save();

        app(AuditLogService::class)->log(
            'member.security.devices-revoked',
            'Member revoked trusted devices from settings.',
            'auth',
            $user,
            $user,
        );

        $this->dispatch('toast', [
            'message' => 'Trusted devices revoked.',
            'type' => 'success',
        ]);
    }

    public function deactivateAccount(): void
    {
        $this->validate([
            'accountForm.current_password' => ['required', 'current_password'],
        ]);

        $user = $this->memberUser();
        $user->account_status = 'inactive';
        $user->save();

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        app(AuditLogService::class)->log(
            'member.account.deactivated',
            'Member deactivated their account from settings.',
            'auth',
            $user,
            $user,
        );

        $this->signOutCurrentUser();
    }

    public function deleteAccount(): void
    {
        $this->validate([
            'accountForm.current_password' => ['required', 'current_password'],
        ]);

        $user = $this->memberUser();

        app(AuditLogService::class)->log(
            'member.account.deleted',
            'Member deleted their account from settings.',
            'auth',
            $user,
            $user,
        );

        $user->delete();

        $this->signOutCurrentUser();
    }

    public function openRolePermissions(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $this->dispatch('sectionChanged', 'role-permissions');
    }

    public function openLoanProducts(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $this->dispatch('sectionChanged', 'loan-products');
    }

    public function openPublicContentManager(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $this->dispatch('sectionChanged', 'public-content');
    }

    public function openMyAccountSettings(): void
    {
        abort_unless(Auth::check(), 403);

        $this->fillOwnProfileForm();
        $this->activeMemberTab = 'profile';
        $this->showPersonalAccountSettings = true;
    }

    public function openPersonalAccountSettings(): void
    {
        $this->openMyAccountSettings();
    }

    public function backToSystemSettings(): void
    {
        $this->showPersonalAccountSettings = false;
    }

    public function render()
    {
        if ($this->isMemberAccount() || $this->showPersonalAccountSettings) {
            return view('livewire.content.settings-member', [
                'member' => $this->memberUser(),
                'showBackToSettings' => ! $this->isMemberAccount() && $this->showPersonalAccountSettings,
                'trustedDevices' => TrustedDevice::query()
                    ->where('user_id', Auth::id())
                    ->orderByDesc('last_used_at')
                    ->orderByDesc('created_at')
                    ->take(8)
                    ->get(),
            ]);
        }

        return view('livewire.content.settings', [
            'canManage' => (bool) Auth::user()?->can('settings.update'),
        ]);
    }

    private function isMemberAccount(): bool
    {
        $user = Auth::user();

        return (bool) $user && $user->hasRole('member') && ! ($user->is_officer ?? false);
    }

    private function memberUser(): User
    {
        return Auth::user()->loadMissing([
            'personalData.address',
            'personalData.contact',
            'trustedDevices',
        ]);
    }

    private function fillMemberForms(): void
    {
        $this->fillOwnProfileForm();

        $this->accountForm = [
            'current_password' => '',
        ];

        $this->passwordForm = [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }


    private function signOutCurrentUser(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('login_page');
    }
}
