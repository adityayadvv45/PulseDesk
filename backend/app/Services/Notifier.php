<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class Notifier
{
    /**
     * In-app notification centre + a logged/webhook notification, per the spec.
     */
    public function notify(int $userId, Ticket $ticket, string $type, string $message): void
    {
        if (! $userId) {
            return;
        }

        AppNotification::create([
            'organization_id' => $ticket->organization_id,
            'user_id' => $userId,
            'ticket_id' => $ticket->id,
            'type' => $type,
            'message' => $message,
        ]);

        Log::channel('stack')->info('pulsedesk.notification', [
            'user_id' => $userId,
            'ticket_id' => $ticket->id,
            'type' => $type,
            'message' => $message,
        ]);
    }
}
