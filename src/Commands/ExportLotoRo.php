<?php

namespace LHDev\LotoRo\Commands;

use LHDev\LotoRo\Http\Controllers\LotoRo;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ExportLotoRo extends Command
{
    protected $signature = 'lotoro:export
                            {--from-year= : The starting year for the data export (4 digits)}
                            {--from-month= : The starting month for the data export (without leading zeros)}
                            {--to-year= : The ending year for the data export (4 digits)}
                            {--to-month= : The ending month for the data export (without leading zeros)}
                            {--format= : The format of the export (JSON or CSV) - default is <info>JSON</info>}
                            {--mode= : The mode of the export (view, download, save) - default is <info>save</info>}';

    protected $description = 'Export the locally saved Romanian lottery results data';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $fromYear = $this->option('from-year');
        $fromMonth = $this->option('from-month');
        $toYear = $this->option('to-year');
        $toMonth = $this->option('to-month');
        $format = $this->option('format') ?? 'json';
        $mode = $this->option('mode') ?? 'save';

        $data = [
            'from-year' => $fromYear,
            'from-month' => $fromMonth,
            'to-year' => $toYear,
            'to-month' => $toMonth,
            'format' => $format,
            'mode' => $mode,
        ];

        $controller = new LotoRo();

        $request = new Request();
        $request->replace($data);

        $response = $controller->exportData($request);

        if($response->isSuccessful()) {
            if ($mode === 'view') {
                $this->info($response->getContent());
            } else {
                $this->info('Data export completed successfully.');
            }
        } else {
            $this->error('Data export failed: ' . $response->getContent());
        }
    }
}
