<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Defence in depth: the global scope already filters by org, but we also
     * assert the org matches and apply role rules here.
     */
    protected function sameOrg(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $this->sameOrg($user, $ticket)) {
            return false;
        }

        // Customers only see tickets they raised; staff see all org tickets.
        if ($user->isCustomer()) {
            return $ticket->requester_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true; // any authenticated user in the org can open a ticket
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $this->sameOrg($user, $ticket)) {
            return false;
        }

        // Customers may edit only their own ticket's subject/description.
        if ($user->isCustomer()) {
            return $ticket->requester_id === $user->id;
        }

        return $user->isStaff();
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->isStaff();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->isAdmin();
    }
}
