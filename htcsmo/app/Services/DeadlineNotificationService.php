<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeadlineNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @return array{created: int, delivered: int, expected: int, eligible: bool, reviewer_missing: bool}
     */
    public function notify(Requisition $requisition): array
    {
        // Approval and closure actions lock this same row. Re-read it under the
        // lock so a queued scan or an old page cannot notify a previous stage.
        return DB::transaction(function () use ($requisition) {
            $current = Requisition::query()->lockForUpdate()->find($requisition->id);

            if (! $current || (! $current->isDeadlineNearing() && ! $current->isDeadlineOverdue())) {
                return ['created' => 0, 'delivered' => 0, 'expected' => 0, 'eligible' => false, 'reviewer_missing' => false];
            }

            return $this->notifyCurrentStage($current);
        });
    }

    /**
     * @return array{created: int, delivered: int, expected: int, eligible: bool, reviewer_missing: bool}
     */
    private function notifyCurrentStage(Requisition $requisition): array
    {
        $requisition->loadMissing('user');

        $overdue = $requisition->isDeadlineOverdue();
        $state = $overdue ? 'overdue' : 'nearing';
        $reviewerRole = $requisition->currentReviewerRole();
        $stage = $this->stageLabel($reviewerRole, $requisition->status);
        $due = $requisition->due_at?->format('M j, Y g:i A') ?? 'the recorded deadline';
        $title = $overdue ? 'Overdue: 3-Working-Day SLA Exceeded' : 'Near Deadline: 3-Working-Day SLA';
        $message = $overdue
            ? "Requisition {$requisition->tracking_code} is overdue while at {$stage}. It was due {$due}."
            : "Requisition {$requisition->tracking_code} is nearing its {$due} deadline and is currently at {$stage}.";

        $recipients = $this->recipients($requisition, $reviewerRole);
        $reviewerMissing = $reviewerRole === null || ! $recipients->contains(
            fn (User $recipient) => $recipient->role === $reviewerRole
                && ($reviewerRole !== 'dept_head' || $requisition->user?->department_id !== null)
        );
        $stageKey = $requisition->status.':'.$reviewerRole.':'.$requisition->stage_started_at?->format('Y-m-d H:i:s');
        $created = 0;
        $delivered = 0;

        foreach ($recipients as $recipient) {
            $isOwner = $recipient->id === $requisition->user_id;
            $canOpenRequest = $isOwner || $recipient->role === 'smo';
            $notification = $this->notifications->send(
                $recipient,
                "deadline.{$state}",
                $title,
                $message,
                [
                    'icon' => $overdue ? 'alert-triangle' : 'clock',
                    'type' => $overdue ? 'danger' : 'warning',
                    'action_type' => $canOpenRequest ? 'requisition' : 'approval_queue',
                    'action_id' => $requisition->id,
                    'dedupe_key' => "deadline:{$requisition->id}:{$state}:{$stageKey}:{$recipient->id}:".now()->toDateString(),
                ]
            );

            $delivered += (int) ($notification !== null);
            $created += (int) ($notification?->wasRecentlyCreated === true);
        }

        return [
            'created' => $created,
            'delivered' => $delivered,
            'expected' => $recipients->count(),
            'eligible' => true,
            'reviewer_missing' => $reviewerMissing,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Requisition $requisition, ?string $reviewerRole): Collection
    {
        $recipients = User::query()
            ->where('role', 'smo')
            ->where('account_status', User::ACCOUNT_ACTIVE)
            ->get();

        if ($requisition->user?->hasActiveAccess()) {
            $recipients->push($requisition->user);
        }

        if ($reviewerRole && $reviewerRole !== 'smo'
            && ($reviewerRole !== 'dept_head' || $requisition->user?->department_id !== null)) {
            $reviewers = User::query()
                ->where('role', $reviewerRole)
                ->where('account_status', User::ACCOUNT_ACTIVE)
                ->when(
                    $reviewerRole === 'dept_head',
                    fn ($users) => $users->where('department_id', $requisition->user?->department_id)
                )
                ->get();
            $recipients = $recipients->merge($reviewers);
        }

        return $recipients->unique('id')->values();
    }

    private function stageLabel(?string $reviewerRole, string $status): string
    {
        if (in_array($status, ['processing', 'ready_for_pickup'], true)) {
            return $status === 'processing' ? 'SMO processing' : 'SMO pickup preparation';
        }

        return match ($reviewerRole) {
            'dept_head' => 'Department Head, Dean, or Principal approval',
            'vp_finance' => 'VP Finance approval',
            'vp_admin' => 'VP Administration approval',
            'provost' => 'Provost approval',
            'president' => 'President approval',
            'smo' => 'SMO processing',
            default => 'the current workflow stage',
        };
    }
}
