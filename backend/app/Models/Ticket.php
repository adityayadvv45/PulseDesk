<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'subject',
        'description',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
        'response_due_at',
        'resolution_due_at',
        'first_responded_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected $appends = ['sla_response', 'sla_resolution'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ticket_tag');
    }

    public function isResolvedState(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }

    /**
     * Live "time remaining / breached" indicator for the first-response SLA.
     */
    public function getSlaResponseAttribute(): ?array
    {
        if (! $this->response_due_at) {
            return null;
        }

        $met = $this->first_responded_at !== null;
        $reference = $this->first_responded_at ?? now();
        $breached = $reference->greaterThan($this->response_due_at);

        return [
            'due_at' => $this->response_due_at->toIso8601String(),
            'met' => $met,
            'breached' => $breached,
            'minutes_remaining' => $met ? null : (int) round(now()->diffInMinutes($this->response_due_at, false)),
        ];
    }

    /**
     * Live "time remaining / breached" indicator for the resolution SLA.
     */
    public function getSlaResolutionAttribute(): ?array
    {
        if (! $this->resolution_due_at) {
            return null;
        }

        $met = $this->resolved_at !== null;
        $reference = $this->resolved_at ?? now();
        $breached = $reference->greaterThan($this->resolution_due_at);

        return [
            'due_at' => $this->resolution_due_at->toIso8601String(),
            'met' => $met,
            'breached' => $breached,
            'minutes_remaining' => $met ? null : (int) round(now()->diffInMinutes($this->resolution_due_at, false)),
        ];
    }
}
