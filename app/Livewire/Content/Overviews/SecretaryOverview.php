<?php

namespace App\Livewire\Content\Overviews;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SecretaryOverview extends Component
{
    public array $kpis = [];

    public Collection $recentMembers;

    public Collection $recentAnnouncements;

    public string $greeting = '';

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasAnyRole(['admin', 'manager', 'secretary']), 403);

        $this->recentMembers = new Collection();
        $this->recentAnnouncements = new Collection();

        $hour = (int) now()->format('H');
        $this->greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $this->loadData();
    }

    public function loadData(): void
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $memberQuery = User::query()->where('role', 'member');

        $this->recentMembers = (clone $memberQuery)
            ->with('personalData')
            ->latest()
            ->take(8)
            ->get();

        $this->recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(6)
            ->get();

        $this->kpis = [
            'period_label' => $start->format('M Y'),
            'members_total' => (clone $memberQuery)->count(),
            'members_new_month' => (clone $memberQuery)->whereBetween('created_at', [$start, $end])->count(),
            'members_active' => (clone $memberQuery)->where('account_status', 'active')->count(),
            'members_inactive' => (clone $memberQuery)->where('account_status', '!=', 'active')->count(),
            'announcements_total' => Announcement::query()->count(),
            'announcements_this_month' => Announcement::query()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    public function render()
    {
        return view('livewire.content.overviews.secretary-overview');
    }
}
