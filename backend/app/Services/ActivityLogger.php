<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ticket;

class ActivityLogger
{
    public function log(Ticket $ticket, string $action, array $meta = [], ?int $userId = null): void
    {
        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'meta' => $meta ?: null,
        ]);
    }
}
