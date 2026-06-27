<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;

class SlaService
{
    /**
     * Apply response/resolution due dates to a ticket based on its org's SLA
     * policy for the ticket priority. Call on create and whenever priority changes.
     */
    public function applyTo(Ticket $ticket): void
    {
        $policy = SlaPolicy::where('priority', $ticket->priority)->first();

        if (! $policy) {
            return;
        }

        $base = $ticket->created_at ?? now();
        $ticket->response_due_at = (clone $base)->addMinutes($policy->response_minutes);
        $ticket->resolution_due_at = (clone $base)->addMinutes($policy->resolution_minutes);
    }
}
