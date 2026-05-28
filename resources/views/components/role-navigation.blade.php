{{-- div for the navigation link buttons --}}

@props(['sections', 'currentSection'])

@php
    // set icons and labels for each sections for the navigation buttons
    $sectionConfig = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
        'notifications' => ['icon' => 'fas fa-bell', 'label' => 'Notifications'],
        'officers' => ['icon' => 'fas fa-user-tie', 'label' => 'Officers'],
        'members' => ['icon' => 'fas fa-users', 'label' => 'Members'],
        'services' => ['icon' => 'fas fa-briefcase', 'label' => 'Services'],
        'loans' => ['icon' => 'fas fa-hand-holding-usd', 'label' => 'Loans'],
        'ledger' => ['icon' => 'fas fa-book', 'label' => 'Ledger'],
        'history' => ['icon' => 'fas fa-history', 'label' => 'History'],
        'tickets' => ['icon' => 'fas fa-ticket-alt', 'label' => 'Tickets'],
        'messages' => ['icon' => 'fas fa-envelope', 'label' => 'Messages'],
        'announcements' => ['icon' => 'fas fa-bullhorn', 'label' => 'Announcements'],
        'reports' => ['icon' => 'fas fa-chart-bar', 'label' => 'Reports'],
        'logs' => ['icon' => 'fas fa-clipboard-list', 'label' => 'Logs'],
        'settings' => ['icon' => 'fas fa-cog', 'label' => 'Settings'],
    ];
@endphp

<div class="flex-1 overflow-y-auto px-4 py-2 space-y-2">
    {{-- use for-each to create the navigation link buttons --}}
    @foreach ($sections as $section)
        @if ($section === 'notifications')
            @continue
        @endif

        @if (isset($sectionConfig[$section]) && config("sidebar.{$section}", true))
            <x-nav-link :section="$section" :icon="$sectionConfig[$section]['icon']" :label="$sectionConfig[$section]['label']" :active="$currentSection === $section" />
        @endif
    @endforeach
</div>
