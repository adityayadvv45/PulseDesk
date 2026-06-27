<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function metrics(Request $request)
    {
        $byStatus = Ticket::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')->pluck('count', 'status');

        $byPriority = Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')->pluck('count', 'priority');

        // Average first-response time in minutes (responded tickets only).
        $avgFirstResponse = Ticket::whereNotNull('first_responded_at')
            ->get(['created_at', 'first_responded_at'])
            ->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at));

        // SLA breach rate on resolution target.
        $total = Ticket::count();
        $breached = Ticket::whereNotNull('resolution_due_at')
            ->where(function ($q) {
                // Open past its resolution target...
                $q->where(function ($inner) {
                    $inner->whereNull('resolved_at')->where('resolution_due_at', '<', now());
                })
                // ...or resolved late.
                ->orWhere(function ($inner) {
                    $inner->whereNotNull('resolved_at')
                          ->whereColumn('resolved_at', '>', 'resolution_due_at');
                });
            })
            ->count();

        // Tickets created per day for the last 14 days.
        $perDay = Ticket::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as count'))
            ->groupBy('day')->orderBy('day')->get();

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $match = $perDay->firstWhere('day', $day);
            $series[] = ['day' => $day, 'count' => $match ? (int) $match->count : 0];
        }

        return response()->json([
            'total' => $total,
            'open' => (int) ($byStatus['open'] ?? 0),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'resolved' => (int) ($byStatus['resolved'] ?? 0),
            'closed' => (int) ($byStatus['closed'] ?? 0),
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'avg_first_response_minutes' => $avgFirstResponse ? round($avgFirstResponse, 1) : null,
            'sla_breach_rate' => $total ? round(($breached / $total) * 100, 1) : 0.0,
            'sla_breached_count' => $breached,
            'unassigned' => Ticket::whereNull('assignee_id')->count(),
            'created_per_day' => $series,
        ]);
    }
}
