<?php

namespace App\Livewire\Dev;

use Livewire\Component;

class TestDashboard extends Component
{
    public $currentSection = 'dashboard';

    public $allSections = [];

    protected $listeners = [
        'sectionChanged' => 'updateSection',
    ];

    public function mount()
    {
        // Get all unique sections from all roles
        $settingsSections = config('settings.sections', []);
        $sections = [];

        foreach ($settingsSections as $role => $roleSections) {
            $sections = array_merge($sections, $roleSections);
        }

        $this->allSections = array_unique($sections);
        sort($this->allSections);
    }

    public function render()
    {
        return view('livewire.dev.test-dashboard');
    }

    public function updateSection($newSection)
    {
        $this->currentSection = $newSection;
    }
}
