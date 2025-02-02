<?php

namespace LHDev\LotoRo\Commands;

use LHDev\LotoRo\Http\Controllers\LotoRo;
use Illuminate\Console\Command;

class FetchLotoRo extends Command
{
    protected $signature = 'lotoro:fetch
                            {--type= : The type of lottery to fetch (649, 540, or all)}
                            {--from-year= : The year to fetch data for (4 digits)}
                            {--from-month= : The month to fetch data for (without leading zeros)}
                            {--to-year= : The year to fetch data up to (4 digits).}
                            {--to-month= : The month to fetch data up to (without leading zeros)}';

    protected $description = 'Fetch the Romanian lottery results history';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // get the type of lottery to fetch, this is a required option
        $type = $this->option('type');

        // get the year and month to fetch data for
        $from_year = $this->option('from-year') ?? null;
        $from_month = $this->option('from-month') ?? null;
        $to_year = $this->option('to-year') ?? null;
        $to_month = $this->option('to-month') ?? null;

        $controller = new LotoRo($from_year, $from_month, $to_year, $to_month);

        $action = match($type) {
            '649' => fn() => $this->fetchAndLog($controller, 'fetch649', '"Loto 6/49" lottery results fetched successfully.'),
            '540' => fn() => $this->fetchAndLog($controller, 'fetch540', '"Loto 5/40" lottery results fetched successfully.'),
            'all'  => fn() => $this->fetchAllLotteries($controller),
            default => fn() => $this->error('The --type option requires a valid type to be specified. Use 649, 540, or all. Example: --type=649'),
        };

        $action();
    }

    /**
     * Fetch the lottery results and log a message
     *
     * @param LotoRo $controller
     * @param string $method
     * @param string $message
     */
    private function fetchAndLog($controller, string $method, string $message): void
    {
        $controller->$method();

        $this->info($message);
    }

    /**
     * Fetch all the Romanian lottery results
     *
     * @param $controller
     * @return void
     */
    private function fetchAllLotteries($controller): void
    {
        $this->fetchAndLog($controller, 'fetch649', '"Loto 6/49" lottery results fetched successfully.');
        $this->fetchAndLog($controller, 'fetch540', '"Loto 5/40" lottery results fetched successfully.');
    }
}
