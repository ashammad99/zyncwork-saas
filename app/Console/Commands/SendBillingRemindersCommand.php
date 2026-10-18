<?php

namespace App\Console\Commands;

use App\Features\Billing\Services\BillingNotifier;
use Illuminate\Console\Command;

/**
 * Trial reminders (3 days and 1 day before the end) and the "trial ended"
 * email, across all tenants. Meant to run hourly; running it more often, or
 * twice at once, sends nothing twice (BillingNotifier records each email).
 */
class SendBillingRemindersCommand extends Command
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Email tenants whose trial ends soon or has just ended';

    public function handle(BillingNotifier $notifier): int
    {
        $sent = $notifier->sendTrialReminders();

        $this->info("Sent trial emails to {$sent} tenant(s).");

        return self::SUCCESS;
    }
}
