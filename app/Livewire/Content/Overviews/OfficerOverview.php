<?php

namespace App\Livewire\Content\Overviews;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OfficerOverview extends Component
{
    public string $greeting = '';

    public string $primaryRole = '';

    public string $accountStatus = 'active';

    public int $permissionCount = 0;

    public array $grantedPermissions = [];

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user && (($user->is_officer ?? false) || $user->hasRole('admin')), 403);

        $hour = (int) now()->format('H');
        $this->greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $this->primaryRole = $user->primary_role;
        $this->accountStatus = (string) ($user->account_status ?? 'active');
        $this->grantedPermissions = $user->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
        $this->permissionCount = count($this->grantedPermissions);
    }

    public function render()
    {
        return view('livewire.content.overviews.officer-overview');
    }
}
