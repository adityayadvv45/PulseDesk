<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        protected SlaService $sla,
        protected ActivityLogger $activity,
        protected Notifier $notifier,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Ticket::query()
            ->with(['requester:id,name,email', 'assignee:id,name,email', 'tags:id,name,color'])
            ->withCount('comments');

        // Customers only ever see their own tickets.
        if ($user->isCustomer()) {
            $query->where('requester_id', $user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($request->filled('assignee_id')) {
            $assignee = $request->query('assignee_id');
            $query->where('assignee_id', $assignee === 'unassigned' ? null : $assignee);
        }
        if ($request->boolean('mine')) {
            $query->where('assignee_id', $user->id);
        }
        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tag));
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'created_at');
        $dir = $request->query('dir', 'desc');
        if (in_array($sort, ['created_at', 'updated_at', 'priority', 'status', 'resolution_due_at'], true)) {
            $query->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($request->integer('per_page', 15));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $ticket = new Ticket([
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        // Customers are always the requester of their own tickets.
        $ticket->requester_id = $user->isStaff() && ! empty($data['requester_id'])
            ? $data['requester_id']
            : $user->id;

        if ($user->isStaff() && ! empty($data['assignee_id'])) {
            $ticket->assignee_id = $data['assignee_id'];
        }

        $ticket->created_at = now();
        $this->sla->applyTo($ticket);
        $ticket->save();

        if (! empty($data['tags'])) {
            $validTags = Tag::whereIn('id', $data['tags'])->pluck('id');
            $ticket->tags()->sync($validTags);
        }

        $this->activity->log($ticket, 'created', ['priority' => $ticket->priority]);

        if ($ticket->assignee_id) {
            $this->activity->log($ticket, 'assigned', ['to' => $ticket->assignee_id]);
            $this->notifier->notify($ticket->assignee_id, $ticket, 'assigned', "You were assigned ticket #{$ticket->id}: {$ticket->subject}");
        }

        return response()->json(
            $ticket->load(['requester:id,name,email', 'assignee:id,name,email', 'tags:id,name,color']),
            201
        );
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'requester:id,name,email,role',
            'assignee:id,name,email,role',
            'tags:id,name,color',
            'activityLogs.user:id,name',
        ]);

        // Comments: customers never see internal notes.
        $commentsQuery = $ticket->comments()->with('user:id,name,role')->oldest();
        if ($request->user()->isCustomer()) {
            $commentsQuery->where('is_internal', false);
        }
        $ticket->setRelation('comments', $commentsQuery->get());

        return response()->json($ticket);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);
        $user = $request->user();

        $rules = [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];

        // Only staff may change status / priority / assignee.
        if ($user->isStaff()) {
            $rules['status'] = ['sometimes', Rule::in(['open', 'pending', 'resolved', 'closed'])];
            $rules['priority'] = ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])];
            $rules['assignee_id'] = ['sometimes', 'nullable', 'integer', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        if (array_key_exists('status', $data) && $data['status'] !== $ticket->status) {
            $from = $ticket->status;
            $ticket->status = $data['status'];
            if (in_array($data['status'], ['resolved', 'closed'], true) && ! $ticket->resolved_at) {
                $ticket->resolved_at = now();
            }
            if (in_array($data['status'], ['open', 'pending'], true)) {
                $ticket->resolved_at = null;
            }
            $this->activity->log($ticket, 'status_changed', ['from' => $from, 'to' => $data['status']]);
        }

        if (array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority) {
            $from = $ticket->priority;
            $ticket->priority = $data['priority'];
            $this->sla->applyTo($ticket); // recompute SLA targets
            $this->activity->log($ticket, 'priority_changed', ['from' => $from, 'to' => $data['priority']]);
        }

        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] != $ticket->assignee_id) {
            $ticket->assignee_id = $data['assignee_id'];
            $this->activity->log($ticket, 'assigned', ['to' => $data['assignee_id']]);
            if ($data['assignee_id']) {
                $this->notifier->notify($data['assignee_id'], $ticket, 'assigned', "You were assigned ticket #{$ticket->id}: {$ticket->subject}");
            }
        }

        foreach (['subject', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $ticket->{$field} = $data[$field];
            }
        }

        $ticket->save();

        if (array_key_exists('tags', $data)) {
            $validTags = Tag::whereIn('id', $data['tags'])->pluck('id');
            $ticket->tags()->sync($validTags);
        }

        return response()->json(
            $ticket->fresh(['requester:id,name,email', 'assignee:id,name,email', 'tags:id,name,color'])
        );
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->authorize('assign', $ticket);

        $data = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->assignee_id = $data['assignee_id'] ?? null;
        $ticket->save();

        $this->activity->log($ticket, 'assigned', ['to' => $ticket->assignee_id]);
        if ($ticket->assignee_id) {
            $this->notifier->notify($ticket->assignee_id, $ticket, 'assigned', "You were assigned ticket #{$ticket->id}: {$ticket->subject}");
        }

        return response()->json($ticket->fresh(['assignee:id,name,email']));
    }

    public function claim(Request $request, Ticket $ticket)
    {
        $this->authorize('assign', $ticket);

        $ticket->assignee_id = $request->user()->id;
        $ticket->save();
        $this->activity->log($ticket, 'assigned', ['to' => $ticket->assignee_id, 'claimed' => true]);

        return response()->json($ticket->fresh(['assignee:id,name,email']));
    }

    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted.']);
    }
}
