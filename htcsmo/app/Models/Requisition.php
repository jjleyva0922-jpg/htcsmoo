<?php

namespace App\Models;

use App\Services\BusinessDaySlaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Requisition extends Model
{
    public const ARCHIVED_STATUSES = ['completed', 'released', 'rejected', 'withdrawn'];

    public const FULFILLED_STATUSES = ['completed', 'released'];

    public const ARCHIVE_CATEGORIES = [
        'fulfilled' => self::FULFILLED_STATUSES,
        'rejected' => ['rejected'],
        'withdrawn' => ['withdrawn'],
    ];

    protected $fillable = [
        'tracking_number', // e.g. MIN-CETE-2026100001
        'user_id',
        'request_type',
        'status',
        'submitted_at',
        'sla_warning_at',
        'due_at',
        'stage_started_at',
        'closed_at',
        'purpose',
        'remarks',
        'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'due_at' => 'datetime',
            'stage_started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Boot function to automatically generate the institutional tracking code upon creation
     */
    protected static function booted()
    {
        static::creating(function (Requisition $requisition) {
            if ($requisition->submitted_at && $requisition->sla_warning_at && $requisition->due_at) {
                return;
            }

            $milestones = app(BusinessDaySlaService::class)->milestones($requisition->submitted_at ?? now());
            $requisition->submitted_at ??= $milestones['submitted_at'];
            $requisition->sla_warning_at ??= $milestones['sla_warning_at'];
            $requisition->due_at ??= $milestones['due_at'];
        });

        static::saving(function (Requisition $requisition) {
            if (! $requisition->isDirty('status')) {
                return;
            }

            $requisition->stage_started_at = now();

            if (in_array($requisition->status, self::ARCHIVED_STATUSES, true)) {
                $requisition->closed_at ??= now();
            } else {
                $requisition->closed_at = null;
            }
        });

        static::created(function ($requisition) {
            if (empty($requisition->tracking_number)) {
                $requisition->tracking_number = $requisition->getTrackingCodeFallback();
                $requisition->saveQuietly();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function latestClarificationLog(): HasOne
    {
        return $this->hasOne(ApprovalLog::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $logs) => $logs->where('action', 'like', '%Clarification%'),
        );
    }

    public function latestClosureLog(): HasOne
    {
        return $this->hasOne(ApprovalLog::class)->ofMany(['id' => 'max'], function (Builder $logs) {
            $logs->where(fn (Builder $events) => $events->where('action', 'like', 'Rejected by %')
                ->orWhere('action', 'Withdrawn by requestor')
                ->orWhere('action', 'Completed by SMO In-Charge')
                ->orWhere('action', 'like', 'Released by %'));
        });
    }

    public function archiveStatusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Completed (pickup confirmed)',
            'released' => 'Released (legacy)',
            'rejected' => 'Rejected / disapproved',
            'withdrawn' => 'Withdrawn by requestor',
            default => $this->requestorStatus()['label'],
        };
    }

    public function closureRemarks(): ?string
    {
        return $this->latestClosureLog?->remarks
            ?? ($this->status === 'rejected' ? $this->remarks : null);
    }

    public function clarificationRemarks(): ?string
    {
        if ($this->status !== 'for_clarification') {
            return null;
        }

        $log = $this->relationLoaded('logs')
            ? $this->logs->filter(fn ($log) => str_contains($log->action, 'Clarification'))->sortByDesc('id')->first()
            : $this->latestClarificationLog;

        return filled($log?->remarks) ? $log->remarks : (filled($this->remarks) ? $this->remarks : null);
    }

    /** Personal workflow copy; labels describe the next stage, not a new approval event. */
    public function requestorStatus(): array
    {
        [$label, $tone, $icon, $nextStep] = match ($this->status) {
            'pending' => ['Department review', 'progress', 'clock-3', 'Waiting for your department head. You can still edit or withdraw this pending request.'],
            'approved_dept' => $this->request_type === 'minor'
                ? ['VP Finance review', 'progress', 'clock-3', 'Waiting for VP Finance review. No action is currently required from you.']
                : ['VP Administration review', 'progress', 'clock-3', 'Waiting for VP Administration review. No action is currently required from you.'],
            'approved_vp' => $this->request_type === 'major'
                ? ['Provost review', 'progress', 'clock-3', 'Waiting for Provost review before presidential review. This request is not ready for pickup.']
                : ['Awaiting SMO processing', 'progress', 'package-open', 'Approvals are complete; waiting for SMO processing. This request is not ready for pickup.'],
            'approved_provost' => ['President review', 'progress', 'clock-3', 'Waiting for presidential review. This request is not ready for pickup.'],
            'approved_president' => ['Awaiting SMO processing', 'progress', 'package-open', 'Approvals are complete; waiting for SMO processing. This request is not ready for pickup.'],
            'processing' => ['SMO processing', 'progress', 'package-open', 'SMO is processing your request. Wait for a ready-for-pickup update.'],
            'ready_for_pickup' => ['Ready for pickup', 'ready', 'package-check', 'SMO has marked this request ready for pickup. Review its details and any recorded instructions.'],
            'for_clarification' => ['Clarification required', 'clarification', 'message-circle-question', 'Read the clarification remarks and follow up with the reviewing office and SMO.'],
            'completed' => ['Pickup completed', 'success', 'circle-check', 'This request is closed after handover. You can review its history or download the requisition PDF.'],
            'released' => ['Released (legacy)', 'success', 'circle-check', 'This legacy request is recorded as released. You can review its history or download the requisition PDF.'],
            'rejected' => ['Request disapproved', 'danger', 'circle-x', 'Review the decision remarks in the request history.'],
            'withdrawn' => ['Withdrawn', 'neutral', 'circle-minus', 'You withdrew this request before approval. Its items and history remain in your archive. Submit a new request if supplies are still needed.'],
            default => ['Status under review', 'progress', 'circle-help', 'Open the request history for the latest recorded update.'],
        };

        return ['label' => $label, 'tone' => $tone, 'icon' => $icon, 'next_step' => $nextStep];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::ARCHIVED_STATUSES);
    }

    public function scopeNeedingRequestorAction(Builder $query): Builder
    {
        return $query->whereIn('status', ['for_clarification', 'ready_for_pickup']);
    }

    public function scopePrioritizeForRequestor(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE WHEN status = 'for_clarification' THEN 0 WHEN status = 'ready_for_pickup' THEN 1
             WHEN due_at <= ? THEN 2 WHEN sla_warning_at <= ? THEN 3 ELSE 4 END",
            [now(), now()],
        )->orderByRaw('CASE WHEN due_at <= ? THEN due_at END', [now()])
            ->orderByRaw('COALESCE(submitted_at, created_at) DESC')->orderByDesc('id');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereIn('status', self::ARCHIVED_STATUSES);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === 'smo' ? $query : $query->where('user_id', $user->id);
    }

    /**
     * Search the human-readable requisition data shown throughout the UI.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $matches) use ($search) {
            $like = "%{$search}%";

            $matches->where('tracking_number', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhere('remarks', 'like', $like)
                ->orWhereHas('items', function (Builder $items) use ($like) {
                    $items->where('item_name', 'like', $like)
                        ->orWhere('specifications', 'like', $like);
                })
                ->orWhereHas('user', function (Builder $users) use ($like) {
                    $users->where('name', 'like', $like)
                        ->orWhere('school_id', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('academicDepartment', fn (Builder $departments) => $departments->where('dept_code', 'like', $like));
                });
        });
    }

    public function scopeWithArchiveFilters(Builder $query, array $filters, bool $allowDepartment = false): Builder
    {
        $dateColumn = ($filters['date_basis'] ?? 'closed') === 'submitted'
            ? DB::raw('COALESCE(submitted_at, created_at)') : 'closed_at';

        return $query
            ->when(trim($filters['search'] ?? '') !== '', fn (Builder $query) => $query->where(function (Builder $matches) use ($filters) {
                $matches->search($filters['search'])->orWhereHas('logs', fn (Builder $logs) => $logs
                    ->where(fn (Builder $events) => $events->where('remarks', 'like', '%'.trim($filters['search']).'%')
                        ->orWhere('action', 'like', '%'.trim($filters['search']).'%')));
            }))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereIn('status', self::ARCHIVE_CATEGORIES[$category]))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(
                $allowDepartment && ($filters['dept'] ?? null),
                fn (Builder $query) => $query->whereHas('user', fn (Builder $users) => $users->inDepartment($filters['dept']))
            )
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate($dateColumn, '<=', $date));
    }

    public function scopePrioritizeExecutiveRequestors(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE (SELECT role FROM users WHERE users.id = requisitions.user_id) WHEN 'president' THEN 0 WHEN 'provost' THEN 1 ELSE 2 END"
        );
    }

    public function scopeAwaitingAdministrativeApproval(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereIn('status', ['pending', 'approved_dept', 'approved_provost'])
                ->orWhere(function (Builder $majorVp) {
                    $majorVp->where('status', 'approved_vp')->where('request_type', 'major');
                });
        });
    }

    public function scopeApprovalDeadlineRisks(Builder $query): Builder
    {
        return $query->deadlineRisks();
    }

    public function scopeDeadlineRisks(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', self::ARCHIVED_STATUSES)
            ->whereNotNull('sla_warning_at')
            ->where('sla_warning_at', '<=', now());
    }

    public function scopeAssignedToReviewer(Builder $query, User $reviewer): Builder
    {
        return match ($reviewer->role) {
            'dept_head' => $query->where('status', 'pending')
                ->whereHas('user', fn (Builder $users) => $users->where('department_id', $reviewer->department_id)),
            'vp_finance' => $query->where('status', 'approved_dept')->where('request_type', 'minor'),
            'vp_admin' => $query->where('status', 'approved_dept')->where('request_type', 'major'),
            'provost' => $query->where('status', 'approved_vp')->where('request_type', 'major'),
            'president' => $query->where('status', 'approved_provost')->where('request_type', 'major'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Includes the established executive requestor route for both tiers. */
    public function scopeAwaitingFulfillment(Builder $query): Builder
    {
        return $query->where(function (Builder $approved) {
            $approved->where(function (Builder $minor) {
                $minor->where('request_type', 'minor')->whereIn('status', ['approved_vp', 'approved_president']);
            })->orWhere(function (Builder $major) {
                $major->where('request_type', 'major')->where('status', 'approved_president');
            });
        });
    }

    public function canStartProcessing(): bool
    {
        return ($this->request_type === 'minor' && in_array($this->status, ['approved_vp', 'approved_president'], true))
            || ($this->request_type === 'major' && $this->status === 'approved_president');
    }

    public static function initialStatusFor(User $requestor): string
    {
        return match ($requestor->role) {
            'president', 'provost' => 'approved_president',
            'vp_admin', 'vp_finance' => 'approved_vp',
            'dept_head' => 'approved_dept',
            default => 'pending',
        };
    }

    public function nextReviewerRole(): ?string
    {
        return match ($this->status) {
            'pending' => 'dept_head',
            'approved_dept' => $this->request_type === 'minor' ? 'vp_finance' : 'vp_admin',
            'approved_vp' => $this->request_type === 'minor' ? 'smo' : 'provost',
            'approved_provost' => 'president',
            'approved_president' => 'smo',
            'processing', 'ready_for_pickup' => 'smo',
            default => null,
        };
    }

    public function currentReviewerRole(): ?string
    {
        if ($this->status === 'for_clarification') {
            if ($this->relationLoaded('logs')) {
                return $this->logs
                    ->filter(fn ($log) => str_contains($log->action, 'Clarification'))
                    ->sortByDesc('id')
                    ->first()?->role;
            }

            return $this->logs()->where('action', 'like', '%Clarification%')->latest('id')->value('role');
        }

        return $this->nextReviewerRole();
    }

    public function approvalDeadlineDaysElapsed(): int
    {
        return app(BusinessDaySlaService::class)->workingDaysElapsed(
            $this->submitted_at ?? $this->created_at,
        );
    }

    public function isAwaitingAdministrativeApproval(): bool
    {
        return in_array($this->status, ['pending', 'approved_dept', 'approved_provost'], true)
            || ($this->status === 'approved_vp' && $this->request_type === 'major');
    }

    public function isApprovalDeadlineNearing(): bool
    {
        return $this->isDeadlineNearing();
    }

    public function isApprovalDeadlineOverdue(): bool
    {
        return $this->isDeadlineOverdue();
    }

    public function isDeadlineNearing(): bool
    {
        return $this->isSlaActive()
            && $this->sla_warning_at !== null
            && now()->greaterThanOrEqualTo($this->sla_warning_at)
            && ! $this->isDeadlineOverdue();
    }

    public function isDeadlineOverdue(): bool
    {
        return $this->isSlaActive()
            && $this->due_at !== null
            && now()->greaterThanOrEqualTo($this->due_at);
    }

    public function isSlaActive(): bool
    {
        return ! in_array($this->status, self::ARCHIVED_STATUSES, true);
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->role === 'smo' || $this->user_id === $user->id;
    }

    public function canBeReviewedBy(User $user): bool
    {
        if ($this->status === 'for_clarification') {
            $questioningRole = $this->currentReviewerRole();

            return $questioningRole !== null && $user->role === $questioningRole
                && ($user->role !== 'dept_head' || $this->user?->department_id === $user->department_id);
        }

        return match ($user->role) {
            'dept_head' => $this->status === 'pending'
                && $this->user?->department_id === $user->department_id,
            'vp_finance' => $this->status === 'approved_dept' && $this->request_type === 'minor',
            'vp_admin' => $this->status === 'approved_dept' && $this->request_type === 'major',
            'provost' => $this->status === 'approved_vp' && $this->request_type === 'major',
            'president' => $this->status === 'approved_provost' && $this->request_type === 'major',
            'smo' => $this->canStartProcessing()
                || in_array($this->status, ['processing', 'ready_for_pickup'], true),
            default => false,
        };
    }

    /**
     * Accessor for tracking_code compatibility across Blade views
     */
    public function getTrackingCodeAttribute(): string
    {
        return $this->tracking_number ?? $this->getTrackingCodeFallback();
    }

    public function getTrackingCodeFallback(): string
    {
        $isMajor = strtoupper($this->request_type) === 'MAJOR';
        $prefix = $isMajor ? 'MAJ' : 'MIN';
        $tierCode = $isMajor ? '2' : '1';
        $dept = strtoupper($this->user->department ?? 'HTC');
        $year = ($this->submitted_at ?? $this->created_at ?? now())->format('Y');
        $uniqueNumber = $year.$tierCode.str_pad($this->id, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$dept}-{$uniqueNumber}";
    }

    /**
     * Check the saved warning for the three-working-day submission SLA.
     */
    public function isNearingDeadline(): bool
    {
        return $this->isDeadlineNearing();
    }

    /**
     * Check the saved deadline for the three-working-day submission SLA.
     */
    public function isOverdue(): bool
    {
        return $this->isDeadlineOverdue();
    }
}
