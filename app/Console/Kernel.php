<?php

namespace App\Console;

use App\Console\Commands\ReinstallCSAPIFromCustomer;
use App\Console\Commands\RemoceCSAPIFromCustomer;
use App\Console\Commands\Shopify\FetchLatestNews;
use App\Console\Commands\UpdateCustomCarrierServiceInfoForAllCustomers;
use App\Console\Commands\UpdatePickupPointSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        UpdateCustomCarrierServiceInfoForAllCustomers::class,
        RemoceCSAPIFromCustomer::class,
        FetchLatestNews::class,
        ReinstallCSAPIFromCustomer::class,
        UpdatePickupPointSettings::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
