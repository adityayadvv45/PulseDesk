<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function makeOrgWithAdmin(string $slug): array
{
    $org = Organization::factory()->create(['slug' => $slug]);
    $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
    $customer = User::factory()->customer()->create(['organization_id' => $org->id]);

    return [$org, $admin, $customer];
}

it('isolates tickets between tenants on the index', function () {
    [$orgA, $adminA, $custA] = makeOrgWithAdmin('org-a');
    [$orgB, $adminB, $custB] = makeOrgWithAdmin('org-b');

    Ticket::factory()->count(3)->create([
        'organization_id' => $orgA->id,
        'requester_id' => $custA->id,
    ]);
    Ticket::factory()->count(5)->create([
        'organization_id' => $orgB->id,
        'requester_id' => $custB->id,
    ]);

    actingAs($adminA, 'sanctum');
    $res = getJson('/api/v1/tickets')->assertOk();
    expect($res->json('total'))->toBe(3);

    actingAs($adminB, 'sanctum');
    $res = getJson('/api/v1/tickets')->assertOk();
    expect($res->json('total'))->toBe(5);
});

it('blocks cross-tenant access to a single ticket (adversarial probe)', function () {
    [$orgA, $adminA, $custA] = makeOrgWithAdmin('org-a');
    [$orgB, $adminB, $custB] = makeOrgWithAdmin('org-b');

    $ticketA = Ticket::factory()->create([
        'organization_id' => $orgA->id,
        'requester_id' => $custA->id,
    ]);

    // Org B admin tries to read Org A's ticket by guessing its id.
    actingAs($adminB, 'sanctum');
    getJson("/api/v1/tickets/{$ticketA->id}")->assertNotFound();
});

it('prevents cross-tenant updates', function () {
    [$orgA, $adminA, $custA] = makeOrgWithAdmin('org-a');
    [$orgB, $adminB, $custB] = makeOrgWithAdmin('org-b');

    $ticketA = Ticket::factory()->create([
        'organization_id' => $orgA->id,
        'requester_id' => $custA->id,
        'status' => 'open',
    ]);

    actingAs($adminB, 'sanctum');
    \Illuminate\Support\Facades\Config::set('app.debug', false);
    $this->patchJson("/api/v1/tickets/{$ticketA->id}", ['status' => 'closed'])
        ->assertNotFound();

    expect($ticketA->fresh()->status)->toBe('open');
});
