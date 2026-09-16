<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether the user can view the specific ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($ticket->user_id === $user->id) {
            return true;
        }

        return $user->isSupportStaff()
            || $user->can('tickets.manage')
            || ($user->is_officer && $user->can('tickets.view'));
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether the user can reply to the ticket.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($ticket->user_id === $user->id) {
            return $ticket->status !== 'closed';
        }

        return $user->isSupportStaff()
            || $user->can('tickets.manage')
            || ($user->is_officer && $user->can('tickets.reply'));
    }

    /**
     * Determine whether the user can update the status of the ticket.
     */
    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && ($user->isSupportStaff() || $user->can('tickets.manage'));
    }

    /**
     * Determine whether the user can assign the ticket to an officer.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && ($user->isSupportStaff() || $user->can('tickets.manage'));
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && ($user->primary_role === 'admin' || $user->can('tickets.manage'));
    }
}
