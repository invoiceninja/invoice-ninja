<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console;

use App\Jobs\Cron\AutoBillCron;
use App\Jobs\Cron\RecurringExpensesCron;
use App\Jobs\Cron\RecurringInvoicesCron;
use App\Jobs\Cron\SubscriptionCron;
use App\Jobs\EDocument\EInvoicePullDocs;
use App\Jobs\Invoice\InvoiceCheckLateWebhook;
use App\Jobs\Ninja\AdjustEmailQuota;
use App\Jobs\Ninja\BankTransactionSync;
use App\Jobs\Ninja\CheckACHStatus;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Ninja\QueueSize;
use App\Jobs\Ninja\SystemMaintenance;
use App\Jobs\Ninja\TaskScheduler;
use App\Jobs\Quote\QuoteCheckExpired;
use App\Jobs\Subscription\CleanStaleInvoiceOrder;
use App\Jobs\Util\DiskCleanup;
use App\Jobs\Util\QuoteReminderJob;
use App\Jobs\Util\ReminderJob;
use App\Jobs\Util\SchedulerCheck;
use App\Jobs\Util\UpdateExchangeRates;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\PaymentDrivers\Rotessa\Jobs\TransactionReport;
use App\Utils\Ninja;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /* Check for the latest version of Invoice Ninja */
        $schedule->job(new VersionCheck())->daily();

        /* Returns the number of jobs in the queue */
        $schedule->job(new QueueSize())->everyFiveMinutes()->withoutOverlapping()->name('queue-size-job')->onOneServer();

        /* Send reminders */
        $schedule->job(new ReminderJob())->hourly()->withoutOverlapping()->name('reminder-job')->onOneServer();

        /* Send quote reminders */
        $schedule->job(new QuoteReminderJob())->hourly()->withoutOverlapping()->name('quote-reminder-job')->onOneServer();

        /* Sends recurring invoices*/
        $schedule->job(new RecurringInvoicesCron())->hourly()->withoutOverlapping()->name('recurring-invoice-job')->onOneServer();

        /* Checks for scheduled tasks */
        $schedule->job(new TaskScheduler())->hourlyAt(10)->withoutOverlapping()->name('task-scheduler-job')->onOneServer();

        /* Checks Rotessa Transactions */
        $schedule->job(new TransactionReport())->dailyAt('01:48')->withoutOverlapping()->name('rotessa-transaction-report')->onOneServer();

        /* Stale Invoice Cleanup*/
        $schedule->job(new CleanStaleInvoiceOrder())->hourlyAt('30')->withoutOverlapping()->name('stale-invoice-job')->onOneServer();

        /* Checks for large companies and marked them as is_large */
        $schedule->job(new CompanySizeCheck())->dailyAt('23:20')->withoutOverlapping()->name('company-size-job')->onOneServer();

        /* Pulls in the latest exchange rates */
        $schedule->job(new UpdateExchangeRates())->dailyAt('23:30')->withoutOverlapping()->name('exchange-rate-job')->onOneServer();

        /* Runs cleanup code for subscriptions */
        $schedule->job(new SubscriptionCron())->hourlyAt(1)->withoutOverlapping()->name('subscription-job')->onOneServer();

        /* Sends recurring expenses*/
        $schedule->job(new RecurringExpensesCron())->dailyAt('00:10')->withoutOverlapping()->name('recurring-expense-job')->onOneServer();

        /* Checks the status of the scheduler */
        $schedule->job(new SchedulerCheck())->dailyAt('01:10')->withoutOverlapping();

        /* Checks and cleans redundant files */
        $schedule->job(new DiskCleanup())->dailyAt('02:10')->withoutOverlapping()->name('disk-cleanup-job')->onOneServer();

        /* Performs system maintenance such as pruning the backup table */
        $schedule->job(new SystemMaintenance())->sundays()->at('02:30')->withoutOverlapping()->name('system-maintenance-job')->onOneServer();

        /* Fires notifications for expired Quotes */
        $schedule->job(new QuoteCheckExpired())->dailyAt('05:10')->withoutOverlapping()->name('quote-expired-job')->onOneServer();

        /* Performs auto billing */
        $schedule->job(new AutoBillCron())->dailyAt('06:20')->withoutOverlapping()->name('auto-bill-job')->onOneServer();

        /* Fires webhooks for overdue Invoice */
        $schedule->job(new InvoiceCheckLateWebhook())->dailyAt('07:00')->withoutOverlapping()->name('invoice-overdue-job')->onOneServer();

        /* Pulls in bank transactions from third party services */
        $schedule->job(new BankTransactionSync())->twiceDaily(1, 13)->withoutOverlapping()->name('bank-trans-sync-job')->onOneServer();

        if (Ninja::isSelfHost()) {
            $schedule->call(function () {
                Account::query()->whereNotNull('id')->update(['is_scheduler_running' => true]);
            })->everyFiveMinutes();

            $schedule->job(new EInvoicePullDocs())->everyFourHours(rand(0, 59))->withoutOverlapping();

        }

        /* Run hosted specific jobs */
        if (Ninja::isHosted()) {
            $schedule->job(new AdjustEmailQuota())->dailyAt('23:30')->withoutOverlapping();

            /* Checks ACH verification status and updates state to authorize when verified */
            $schedule->job(new CheckACHStatus())->everySixHours()->withoutOverlapping()->name('ach-status-job')->onOneServer();

            $schedule->command('ninja:check-data --database=db-ninja-01')->dailyAt('02:10')->withoutOverlapping()->name('check-data-db-1-job')->onOneServer();

            $schedule->command('ninja:check-data --database=db-ninja-02')->dailyAt('02:20')->withoutOverlapping()->name('check-data-db-2-job')->onOneServer();

            $schedule->command('ninja:s3-cleanup')->dailyAt('23:15')->withoutOverlapping()->name('s3-cleanup-job')->onOneServer();
        }

        if (config('queue.default') == 'database' && Ninja::isSelfHost() && config('ninja.internal_queue_enabled') && !config('ninja.is_docker')) {
            $schedule->command('queue:work database --stop-when-empty --memory=256')->everyMinute()->withoutOverlapping();

            $schedule->command('queue:restart')->everyFiveMinutes()->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
