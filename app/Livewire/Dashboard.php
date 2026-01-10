<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public $currentSection = 'dashboard';

    public $isLoading = false;

    // protected $adminSections, protected $managerSections, protected $memberSections, $loanSections, $treasurerSections, $secretarySections;

    protected $listeners = [
        'sectionChanged' => 'updateSection',
    ];

    public function render()
    {
        //get user role
        $userRole = Auth::user()->role ?? 'member';
        $userSections = config("settings.sections.{$userRole}", []);

        // Map 'dashboard' section to role-specific overview component
        $sectionMapping = [
            'member' => 'overviews.member-overview',
            'admin' => 'overviews.admin-overview',
            'manager' => 'overviews.manager-overview',
            'loan' => 'overviews.officer-overview',
            'secretary' => 'overviews.officer-overview',
            'treasurer' => 'overviews.officer-overview',
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
        $userRole = $user->role; // Or however you get the role
        $allowedSections = config("settings.sections.{$userRole}", []);

        if (in_array($newSection, $allowedSections)) {
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
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    }
}
