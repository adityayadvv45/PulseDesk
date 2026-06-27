<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;

class CommentPolicy
{
    public function create(User $user, Ticket $ticket): bool
    {
        if ($user->organization_id !== $ticket->organization_id) {
            return false;
        }

        if ($user->isCustomer()) {
            return $ticket->requester_id === $user->id;
        }

        return true;
    }

    // Only staff may post internal notes.
    public function createInternal(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Comment $comment): bool
    {
        if ($user->organization_id !== $comment->organization_id) {
            return false;
        }

        // Customers never see internal notes.
        if ($comment->is_internal) {
            return $user->isStaff();
        }

        return true;
    }
}
