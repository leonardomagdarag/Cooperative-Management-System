<?php

namespace App\Livewire\Content;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class Logs extends Component
{
    use WithPagination;

    public $search = '';

    public $tagFilter = 'all';

    public $eventFilter = 'all';

    public $dateRange = 'all';

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTagFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEventFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateRange(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function nextPage(): void
    {
        $this->setPage($this->getPage() + 1);
    }

    public function previousPage(): void
    {
        $this->setPage(max(1, $this->getPage() - 1));
    }

    public function render()
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('admin') && $user->can('logs.view'), 403);

        $query = Activity::query()
            ->with(['causer.personalData', 'subject'])
            ->when($this->search, function ($q) {
                $q->where(function ($searchQ) {
                    $searchQ->where('description', 'like', '%' . $this->search . '%')
                        ->orWhere('event', 'like', '%' . $this->search . '%')
                        ->orWhere('log_name', 'like', '%' . $this->search . '%')
                        ->orWhere('subject_type', 'like', '%' . $this->search . '%')
                        ->orWhere('causer_type', 'like', '%' . $this->search . '%')
                        ->orWhereHas('causer', function ($causerQ) {
                            $causerQ->where('email', 'like', '%' . $this->search . '%')
                                ->orWhereHas('personalData', function ($personalQ) {
                                    $personalQ->where('first_name', 'like', '%' . $this->search . '%')
                                        ->orWhere('last_name', 'like', '%' . $this->search . '%');
                                });
                        });
                });
            })
            ->when($this->tagFilter !== 'all', fn($q) => $q->where('log_name', $this->tagFilter))
            ->when($this->eventFilter !== 'all', fn($q) => $q->where('event', $this->eventFilter));

        if ($this->dateRange === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->dateRange === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->dateRange === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        $logs = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.content.logs', [
            'logs' => $logs,
            'tagOptions' => Activity::query()->select('log_name')->distinct()->whereNotNull('log_name')->orderBy('log_name')->pluck('log_name'),
            'eventOptions' => Activity::query()->select('event')->distinct()->whereNotNull('event')->orderBy('event')->pluck('event'),
        ]);
    }
}
