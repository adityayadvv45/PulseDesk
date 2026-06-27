<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Ticket;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        protected ActivityLogger $activity,
        protected Notifier $notifier,
    ) {}

    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('create', [Comment::class, $ticket]);
        $user = $request->user();

        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false);

        // Only staff may write internal notes.
        if ($isInternal && ! $user->isStaff()) {
            abort(403, 'Only agents can post internal notes.');
        }

        $comment = new Comment([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => $isInternal,
        ]);
        $comment->save();

        // First public reply from staff records the SLA first-response time.
        if (! $isInternal && $user->isStaff() && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
            $ticket->save();
        }

        $this->activity->log($ticket, $isInternal ? 'internal_note' : 'replied', [
            'comment_id' => $comment->id,
        ]);

        // Notify the relevant party of a public reply.
        if (! $isInternal) {
            $notifyUserId = $user->id === $ticket->requester_id
                ? $ticket->assignee_id
                : $ticket->requester_id;
            if ($notifyUserId) {
                $this->notifier->notify($notifyUserId, $ticket, 'replied', "New reply on ticket #{$ticket->id}: {$ticket->subject}");
            }
        }

        return response()->json($comment->load('user:id,name,role'), 201);
    }
}
