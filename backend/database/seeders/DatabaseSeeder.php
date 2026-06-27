<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Primary demo tenant (spec: 1 org, admin, 2 agents, 2 customers, ~12 tickets)
        $this->seedOrganization(
            name: 'Acme Support',
            slug: 'acme',
            adminEmail: 'admin@acme.test',
            ticketCount: 12,
            primary: true,
        );

        // Second tenant — proves isolation (Org A can't see Org B).
        $this->seedOrganization(
            name: 'Globex Helpdesk',
            slug: 'globex',
            adminEmail: 'admin@globex.test',
            ticketCount: 6,
            primary: false,
        );

        TenantContext::clear();
    }

    protected function seedOrganization(string $name, string $slug, string $adminEmail, int $ticketCount, bool $primary): void
    {
        $org = Organization::create(['name' => $name, 'slug' => $slug]);
        TenantContext::set($org->id);

        // SLA policies per priority (minutes).
        $sla = [
            'urgent' => [30, 240],
            'high' => [60, 480],
            'medium' => [240, 1440],
            'low' => [480, 2880],
        ];
        foreach ($sla as $priority => [$resp, $resol]) {
            SlaPolicy::create([
                'organization_id' => $org->id,
                'priority' => $priority,
                'response_minutes' => $resp,
                'resolution_minutes' => $resol,
            ]);
        }

        $admin = User::create([
            'organization_id' => $org->id,
            'name' => $primary ? 'Avery Admin' : 'Gabe Admin',
            'email' => $adminEmail,
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $agents = collect();
        $agentNames = $primary ? ['Sam Agent', 'Riley Agent'] : ['Quinn Agent', 'Drew Agent'];
        foreach ($agentNames as $i => $aname) {
            $agents->push(User::create([
                'organization_id' => $org->id,
                'name' => $aname,
                'email' => Str::slug(explode(' ', $aname)[0]) . '@' . $slug . '.test',
                'role' => 'agent',
                'password' => Hash::make('password'),
            ]));
        }

        $customers = collect();
        $custNames = $primary ? ['Casey Customer', 'Jordan Client'] : ['Morgan Buyer', 'Taylor User'];
        foreach ($custNames as $cname) {
            $customers->push(User::create([
                'organization_id' => $org->id,
                'name' => $cname,
                'email' => Str::slug(explode(' ', $cname)[0]) . '@' . $slug . '.test',
                'role' => 'customer',
                'password' => Hash::make('password'),
            ]));
        }

        $tagNames = [
            'billing' => '#ef4444',
            'bug' => '#f97316',
            'feature-request' => '#8b5cf6',
            'onboarding' => '#10b981',
            'account' => '#3b82f6',
        ];
        $tags = collect();
        foreach ($tagNames as $tname => $color) {
            $tags->push(Tag::create([
                'organization_id' => $org->id,
                'name' => $tname,
                'color' => $color,
            ]));
        }

        $slaService = new SlaService();
        $subjects = [
            'Cannot log into my account',
            'Invoice charged twice this month',
            'Feature request: dark mode',
            'App crashes on export to CSV',
            'How do I reset my password?',
            'SSO setup help needed',
            'Data not syncing across devices',
            'Upgrade plan question',
            'Webhook deliveries failing',
            'Mobile app shows blank screen',
            'Refund request for last order',
            'API rate limit too low',
            'Email notifications not arriving',
            'Cannot invite team members',
        ];

        $statuses = ['open', 'open', 'open', 'pending', 'pending', 'resolved', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'medium', 'high', 'urgent'];

        for ($i = 0; $i < $ticketCount; $i++) {
            $createdAt = Carbon::now()->subDays(rand(0, 13))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $requester = $customers->random();
            $status = $statuses[$i % count($statuses)];
            $priority = $priorities[array_rand($priorities)];
            $assignee = in_array($status, ['open']) && rand(0, 1) ? null : $agents->random();

            $ticket = new Ticket([
                'organization_id' => $org->id,
                'subject' => $subjects[$i % count($subjects)],
                'description' => 'Hi team, ' . Str::lower($subjects[$i % count($subjects)]) . '. This started recently and is affecting my work. Please advise on next steps. Thanks!',
                'status' => $status,
                'priority' => $priority,
                'requester_id' => $requester->id,
                'assignee_id' => $assignee?->id,
            ]);
            $ticket->created_at = $createdAt;
            $ticket->updated_at = $createdAt;
            $slaService->applyTo($ticket);

            if (in_array($status, ['resolved', 'closed'])) {
                $ticket->first_responded_at = (clone $createdAt)->addMinutes(rand(10, 120));
                $ticket->resolved_at = (clone $createdAt)->addMinutes(rand(180, 2000));
            } elseif ($assignee && rand(0, 1)) {
                $ticket->first_responded_at = (clone $createdAt)->addMinutes(rand(10, 180));
            }
            $ticket->save();

            // tags
            $ticket->tags()->sync($tags->random(rand(1, 2))->pluck('id'));

            // activity: created
            ActivityLog::create([
                'organization_id' => $org->id,
                'ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'action' => 'created',
                'meta' => ['priority' => $priority],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($assignee) {
                ActivityLog::create([
                    'organization_id' => $org->id,
                    'ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'action' => 'assigned',
                    'meta' => ['to' => $assignee->id],
                    'created_at' => (clone $createdAt)->addMinutes(5),
                    'updated_at' => (clone $createdAt)->addMinutes(5),
                ]);
            }

            // a public reply + maybe an internal note
            if ($ticket->first_responded_at && $assignee) {
                Comment::create([
                    'organization_id' => $org->id,
                    'ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'body' => 'Thanks for reaching out — we are looking into this now and will update you shortly.',
                    'is_internal' => false,
                    'created_at' => $ticket->first_responded_at,
                    'updated_at' => $ticket->first_responded_at,
                ]);
                ActivityLog::create([
                    'organization_id' => $org->id,
                    'ticket_id' => $ticket->id,
                    'user_id' => $assignee->id,
                    'action' => 'replied',
                    'created_at' => $ticket->first_responded_at,
                    'updated_at' => $ticket->first_responded_at,
                ]);

                if (rand(0, 1)) {
                    Comment::create([
                        'organization_id' => $org->id,
                        'ticket_id' => $ticket->id,
                        'user_id' => $assignee->id,
                        'body' => 'Internal: reproduced on staging, escalating to engineering.',
                        'is_internal' => true,
                        'created_at' => (clone $ticket->first_responded_at)->addMinutes(3),
                        'updated_at' => (clone $ticket->first_responded_at)->addMinutes(3),
                    ]);
                }
            }
        }
    }
}
