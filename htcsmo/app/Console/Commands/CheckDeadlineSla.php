<?php

namespace App\Console\Commands;

use App\Models\Requisition;
use App\Services\DeadlineNotificationService;
use Illuminate\Console\Command;

class CheckDeadlineSla extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smo:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active requisitions and alert stakeholders when the 3-working-day SLA is nearing or overdue';

    /**
     * Execute the console command.
     */
    public function handle(DeadlineNotificationService $deadlineNotifications)
    {
        $this->info('Scanning active requisitions against the 3-working-day SLA...');

        $atRiskRequisitions = Requisition::with(['user', 'items'])
            ->deadlineRisks()
            ->get();

        $alertsCount = 0;
        $problemCount = 0;

        foreach ($atRiskRequisitions as $requisition) {
            $result = $deadlineNotifications->notify($requisition);
            $alertsCount += $result['created'];

            if ($result['reviewer_missing'] || $result['delivered'] < $result['expected']) {
                $problemCount++;
                $this->warn("{$requisition->tracking_code}: reminder delivery is incomplete or no active current owner is assigned.");
            }
        }

        $this->info("Completed. Created {$alertsCount} in-app alert notification(s).");

        return $problemCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
