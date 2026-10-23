<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=365 : Retain read notifications for this many days}';

    protected $description = 'Delete old read notifications while preserving every unread notification';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $deleted = Notification::query()
            ->where('is_read', true)
            ->where('read_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} read notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
