<?php

namespace App\Livewire\Content\Overviews;

use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
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

        $this->recentMembers       = new Collection();
        $this->recentAnnouncements = new Collection();

        $hour = (int) now()->format('H');
        $this->greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $this->loadData();
    }

    public function loadData(): void
    {
        $now   = now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        $totalMembers        = User::query()->where('role', 'member')->count();
        $newMembersThisMonth = User::query()->where('role', 'member')->whereBetween('created_at', [$start, $end])->count();
        $activeMembers       = User::query()->where('role', 'member')->where('account_status', 'active')->count();
        $inactiveMembers     = User::query()->where('role', 'member')->where('account_status', '!=', 'active')->count();
        $totalAnnouncements  = Announcement::query()->count();
        $recentAnnouncementsCount = Announcement::query()->whereBetween('created_at', [$start, $end])->count();

        $this->recentMembers = User::query()
            ->with('personalData')
            ->where('role', 'member')
            ->latest()
            ->take(8)
            ->get();

        $this->recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(6)
            ->get();

        $this->kpis = [
            'period_label'               => $start->format('M Y'),
            'members_total'              => $totalMembers,
            'members_new_month'          => $newMembersThisMonth,
            'members_active'             => $activeMembers,
            'members_inactive'           => $inactiveMembers,
            'announcements_total'        => $totalAnnouncements,
            'announcements_this_month'   => $recentAnnouncementsCount,
        ];
    }

    public function render()
    {
        return view('livewire.content.overviews.secretary-overview');
    }
}
