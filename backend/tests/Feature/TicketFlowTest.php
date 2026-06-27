<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function org(string $slug = 'flow'): Organization
{
    return Organization::factory()->create(['slug' => $slug]);
}

it('lets a customer create a ticket and only see their own', function () {
    $o = org();
    $cust = User::factory()->customer()->create(['organization_id' => $o->id]);
    $other = User::factory()->customer()->create(['organization_id' => $o->id]);

    Ticket::factory()->create(['organization_id' => $o->id, 'requester_id' => $other->id]);

    actingAs($cust, 'sanctum');
    postJson('/api/v1/tickets', [
        'subject' => 'My printer is broken',
        'description' => 'It will not print at all.',
        'priority' => 'high',
    ])->assertCreated()
      ->assertJsonPath('subject', 'My printer is broken')
      ->assertJsonPath('requester.id', $cust->id);

    // Customer sees only their own ticket, not the other customer's.
    $res = getJson('/api/v1/tickets')->assertOk();
    expect($res->json('total'))->toBe(1);
});

it('applies SLA due dates on ticket creation', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@acme.test')->first();

    actingAs($admin, 'sanctum');
    $res = postJson('/api/v1/tickets', [
        'subject' => 'Urgent outage',
        'description' => 'Everything is down.',
        'priority' => 'urgent',
    ])->assertCreated();

    expect($res->json('response_due_at'))->not->toBeNull();
    expect($res->json('resolution_due_at'))->not->toBeNull();
    expect($res->json('sla_response.breached'))->toBeFalse();
});

it('hides internal notes from customers but shows them to agents', function () {
    $o = org('notes');
    $agent = User::factory()->agent()->create(['organization_id' => $o->id]);
    $cust = User::factory()->customer()->create(['organization_id' => $o->id]);
    $ticket = Ticket::factory()->create([
        'organization_id' => $o->id,
        'requester_id' => $cust->id,
    ]);

    // Agent posts a public reply + an internal note.
    actingAs($agent, 'sanctum');
    postJson("/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'Public: we are on it.',
        'is_internal' => false,
    ])->assertCreated();
    postJson("/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'Internal: escalate to eng.',
        'is_internal' => true,
    ])->assertCreated();

    // Agent sees both comments.
    actingAs($agent, 'sanctum');
    $res = getJson("/api/v1/tickets/{$ticket->id}")->assertOk();
    expect(count($res->json('comments')))->toBe(2);

    // Customer sees only the public one.
    actingAs($cust, 'sanctum');
    $res = getJson("/api/v1/tickets/{$ticket->id}")->assertOk();
    expect(count($res->json('comments')))->toBe(1);
    expect($res->json('comments.0.is_internal'))->toBeFalse();
});

it('forbids customers from posting internal notes', function () {
    $o = org('cust-notes');
    $cust = User::factory()->customer()->create(['organization_id' => $o->id]);
    $ticket = Ticket::factory()->create([
        'organization_id' => $o->id,
        'requester_id' => $cust->id,
    ]);

    actingAs($cust, 'sanctum');
    postJson("/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'sneaky note',
        'is_internal' => true,
    ])->assertForbidden();
});

it('records first response time and logs activity on status change', function () {
    $o = org('audit');
    $agent = User::factory()->agent()->create(['organization_id' => $o->id]);
    $cust = User::factory()->customer()->create(['organization_id' => $o->id]);
    $ticket = Ticket::factory()->create([
        'organization_id' => $o->id,
        'requester_id' => $cust->id,
        'status' => 'open',
    ]);

    actingAs($agent, 'sanctum');
    postJson("/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'first reply',
    ])->assertCreated();

    expect($ticket->fresh()->first_responded_at)->not->toBeNull();

    $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('status', 'resolved');

    expect($ticket->fresh()->resolved_at)->not->toBeNull();

    $res = getJson("/api/v1/tickets/{$ticket->id}")->assertOk();
    $actions = collect($res->json('activity_logs'))->pluck('action');
    expect($actions)->toContain('status_changed');
});
