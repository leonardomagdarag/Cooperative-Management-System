<?php

namespace App\Livewire\Content;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Settings extends Component
{
    public function openLoanProducts(): void
    {
        if (! $this->isAdmin()) {
            $this->dispatch('error', message: 'Only admins can access loan product management.');

            return;
        }

        $this->dispatch('sectionChanged', 'loan-products');
    }

    public function render()
    {
        return view('livewire.content.settings', [
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->hasRole('admin'));
    }
}
