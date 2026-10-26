<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('drip:send')->dailyAt('01:00')->withoutOverlapping();
        $schedule->command('activity:archive')->monthlyOn(1, '02:30')->withoutOverlapping();
        $schedule->command('claims:retry-email-notifications')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('claims:send-follow-up-reminders')->dailyAt('09:00')->timezone(config('dtc.timezone'))->withoutOverlapping();
        $schedule->command('claims:prune-attachments')->dailyAt('03:30')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
