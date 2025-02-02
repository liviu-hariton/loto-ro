<?php

namespace LHDev\LotoRo\Http\Controllers;

use App\Http\Controllers\Controller;
use LHDev\LotoRo\Enums\LotoDrawType;
use LHDev\LotoRo\Models\LotoRoDraw;
use LHDev\LotoRo\Models\LotoRoResult;
use LHDev\LotoRo\Models\LotoRoTotal;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Filesystem\Filesystem;

class LotoRo extends Controller
{
    /**
     * URL for 6/49 lottery game results.
     */
    private const string _LOTO_649_URL = 'https://www.loto.ro/loto-new/newLotoSiteNexioFinalVersion/web/app2.php/jocuri/649_si_noroc/rezultate_extragere.html';

    /**
     * URL for 5/40 lottery game results.
     */
    private const string _LOTO_540_URL = 'https://www.loto.ro/loto-new/newLotoSiteNexioFinalVersion/web/app2.php/jocuri/540_si_super_noroc/rezultate_extrageri.html';

    /**
     * The history of the lottery results starts from January 1998
     */
    private const int _START_YEAR = 1998;
    private const int _START_MONTH = 1;

    /**
     * The parameters to send in the POST request to fetch the data.
     */
    private const string _PARAM_YEAR = 'select-year';
    private const string _PARAM_MONTH = 'select-month';

    /**
     * The CSS classes used to extract the data from the HTML response.
     */
    private const string _DRAWS_CONTAINER_CSS_CLASS = 'rezultate-extrageri-content';
    private const string _DRAW_DATE_CONTAINER_CSS_CLASS = 'button-open-details';
    private const string _DRAW_NUMBERS_CONTAINER_CSS_CLASS = 'numere-extrase';
    private const string _DRAW_RESULTS_TABLE_CSS_CLASS = 'results-table';
    private const string _DRAW_TOTAL_TEXT_INFO = 'Fond total de castiguri: ';

    /**
     * The name of the file to export the data to, without the extension.
     */
    private const string _EXPORT_FILE_NAME = 'lotoro_data';
    private const string _EXPORT_FILE_STORAGE = 'app/exports/';

    /**
     * @var int $fy From year
     * @var int $fm From month
     * @var int $ty Up to year
     * @var int $tm Up to month
     */
    private int $fy;
    private int $fm;
    private int $ty;
    private int $tm;

    protected $files;

    /**
     * Create a new controller instance and set the years and months to fetch data for.
     *
     * @param string|null $from_year
     * @param string|null $from_month
     * @param string|null $to_year
     * @param string|null $to_month
     */
    public function __construct(string $from_year = null, string $from_month = null, string $to_year = null, string $to_month = null)
    {
        $request = request();

        $this->fy = $from_year ?? $request->query('from-year', self::_START_YEAR);
        $this->fm = $from_month ?? $request->query('from-month', self::_START_MONTH);

        $this->ty = $to_year ?? $request->query('to-year', (int)date('Y'));
        $this->tm = $to_month ?? $request->query('to-month', (int)date('n'));

        $this->files = new Filesystem;
    }

    /**
     * Fetch the 6/49 lottery results.
     */
    public function fetch649()
    {
        $this->fetchData(self::_LOTO_649_URL, LotoDrawType::LOTO_649);

        return response()->json(['message' => "6/49 Lottery results fetched successfully."]);
    }

    /**
     * Fetch the 5/40 lottery results.
     * @return void
     */
    public function fetch540()
    {
        $this->fetchData(self::_LOTO_540_URL, LotoDrawType::LOTO_540);

        return response()->json(['message' => "5/40 Lottery results fetched successfully."]);
    }

    /**
     * Fetch data from the given URL and process it.
     *
     * @param string $url The URL to fetch data from.
     * @param LotoDrawType $drawType The type of lottery draw.
     */
    private function fetchData(string $url, LotoDrawType $drawType)
    {
        for($year = $this->fy; $year <= date('Y'); $year++) {
            for($month = $this->fm; $month <= 12; $month++) {
                // stop here as we've reached the year and month to fetch data up to
                if($year == $this->ty && $month > $this->tm) {
                    break 2;
                }

                try {
                    // make a POST request to the URL with the year and month as parameters
                    $response = Http::asForm()->timeout(10)->post($url, [
                        self::_PARAM_YEAR => $year,
                        self::_PARAM_MONTH => $month,
                    ]);

                    if($response->successful()) {
                        $this->extractData($response->body(), $drawType);

                        $this->logMessage("Processing date: $year-$month");
                    } else {
                        throw new Exception("Request failed with status: ".$response->status());
                    }
                } catch (Exception $e) {
                    $this->logMessage("HTTP request failed: ".$e->getMessage(), 'error');
                }
            }
        }
    }

    /**
     * Log a message to the console or log file.
     *
     * @param string $message The message to log.
     * @param string $level The log level (default is 'info').
     */
    private function logMessage(string $message, string $level = 'info')
    {
        if(app()->runningInConsole()) {
            echo "[".strtoupper($level)."] ".$message.PHP_EOL;
        } else {
            Log::{$level}($message);
        }
    }

    /**
     * Extract data from the HTML response.
     *
     * @param string $data The HTML response data.
     * @param LotoDrawType $drawType The type of lottery draw.
     */
    private function extractData(string $data, LotoDrawType $drawType)
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($data);
        $xpath = new DOMXPath($dom);

        $results = [];

        $resultDivs = $xpath->query("//div[contains(@class, '".self::_DRAWS_CONTAINER_CSS_CLASS."')]");

        foreach($resultDivs as $resultDiv) {
            $numbers = $this->extractNumbers($xpath, $resultDiv);

            if(count($numbers) > 0) {
                $date = $this->extractDate($xpath, $resultDiv);
                $tableData = $this->extractTableData($xpath, $resultDiv);

                $results[] = [
                    'date' => $date,
                    'numbers' => $numbers,
                    'table_data' => $tableData,
                ];
            }
        }

        if(count($results) > 0) {
            $this->storeData($drawType, array_reverse($results));
        }
    }

    /**
     * Extract the draw date from the result div.
     *
     * @param DOMXPath $xpath The DOMXPath object.
     * @param DOMNode $resultDiv The result div node.
     * @return string The extracted date.
     */
    private function extractDate(DOMXPath $xpath, DOMNode $resultDiv)
    {
        $dateNode = $xpath->query(".//div[contains(@class, '".self::_DRAW_DATE_CONTAINER_CSS_CLASS."')]//span", $resultDiv);

        return $dateNode->length ? trim($dateNode->item(0)->nodeValue) : '';
    }

    /**
     * Extract the numbers from the result div.
     *
     * @param DOMXPath $xpath The DOMXPath object.
     * @param DOMNode $resultDiv The result div node.
     * @return array The extracted numbers.
     */
    private function extractNumbers(DOMXPath $xpath, DOMNode $resultDiv)
    {
        $imageNodes = $xpath->query(".//div[contains(@class, '".self::_DRAW_NUMBERS_CONTAINER_CSS_CLASS."')]//img", $resultDiv);

        $numbers = [];

        // extract the numbers from the image src attribute
        foreach($imageNodes as $img) {
            // extract the number from the image URL
            if(preg_match('/\/(\d+)\.png$/', $img->getAttribute('src'), $matches)) {
                $numbers[] = (int)$matches[1];
            }
        }

        return $numbers;
    }

    /**
     * Extract the draw's results details table data from the result div.
     *
     * @param DOMXPath $xpath The DOMXPath object.
     * @param DOMNode $resultDiv The result div node.
     * @return array The extracted table data.
     */
    private function extractTableData(DOMXPath $xpath, DOMNode $resultDiv)
    {
        $tableRows = $xpath->query(".//table[contains(@class, '".self::_DRAW_RESULTS_TABLE_CSS_CLASS."')]//tbody/tr", $resultDiv);

        $tableData = [];

        foreach ($tableRows as $row) {
            $cells = $row->getElementsByTagName("td");

            if($cells->length == 4) {
                $tableData[] = [
                    'category' => trim($cells->item(0)->nodeValue),
                    'winners' => trim($cells->item(1)->nodeValue),
                    'prize' => trim($cells->item(2)->nodeValue),
                    'report' => trim($cells->item(3)->nodeValue)
                ];
            } elseif ($cells->length == 1 && str_contains($cells->item(0)->nodeValue, self::_DRAW_TOTAL_TEXT_INFO)) {
                $tableData['total'] = str_replace(self::_DRAW_TOTAL_TEXT_INFO, "", trim($cells->item(0)->nodeValue));
            }
        }

        return $tableData;
    }

    /**
     * Store the extracted data in the database (or update it if it's already there).
     *
     * @param LotoDrawType $drawType The type of lottery draw.
     * @param array $results The extracted results.
     */
    private function storeData(LotoDrawType $drawType, array $results)
    {
        foreach($results as $result) {
            $LotoRoDraw = LotoRoDraw::updateOrCreate(
                [
                    'draw_type' => $drawType,
                    'draw_date' => \DateTime::createFromFormat('d.m.Y', $result['date'])->format('Y-m-d'),
                ],
                [
                    'numbers' => implode(',', $result['numbers']),
                ]
            );

            $LotoRoDraw->updated_at = now();
            $LotoRoDraw->save();

            foreach($result['table_data'] as $row) {
                if(isset($row['category'])) {
                    LotoRoResult::updateOrCreate(
                        [
                            'draw_id' => $LotoRoDraw->id,
                            'category' => $row['category'],
                        ],
                        [
                            'winners' => $row['winners'],
                            'prize' => $row['prize'] ? floatval(str_replace('.', '', $row['prize'])) : null,
                            'report' => $row['report'] ? floatval(str_replace('.', '', $row['report'])) : null,
                        ]
                    );
                } else {
                    LotoRoTotal::updateOrCreate(
                        [
                            'draw_id' => $LotoRoDraw->id,
                        ],
                        [
                            'total_prize' => $row != '' ? floatval(str_replace('.', '', $row)) : 0,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Export the lottery data as JSON or CSV.
     *
     * All data (default behavior) as JSON: /lotoro-export
     * All data as CSV: /lotoro-export?format=csv
     * From January 2020 to now: /lotoro-export?from-year=2020&from-month=1
     * From the beginning up to December 2021: /lotoro-export?to-year=2021&to-month=12
     * Exact range (June 2019 - March 2022): /lotoro-export?from-year=2019&from-month=6&to-year=2022&to-month=3
     * From January 2020 to December 2021 as CSV: /lotoro-export?from-year=2020&from-month=1&to-year=2021&to-month=12&format=csv
     * Display the data in the browser: /lotoro-export?from-year=2019&from-month=6&to-year=2019&to-month=8&display=1
     * Save CSV to disk: /lotoro-export?format=csv&mode=save
     *
     * @param Request $request
     * @return mixed
     */
    public function exportData(Request $request)
    {

        // Set default values if missing
        $fromYear = intval($request->input('from-year')) ?: self::_START_YEAR;
        $fromMonth = str_pad($request->input('from-month', '1') ?: '1', 2, '0', STR_PAD_LEFT);

        $toYear = intval($request->input('to-year')) ?: date('Y');
        $toMonth = str_pad($request->input('to-month', '12') ?: '12', 2, '0', STR_PAD_LEFT);

        // Convert to date strings
        $fromDate = $fromYear."-".$fromMonth."-01";
        $toDate = date("Y-m-t", strtotime($toYear."-".$toMonth."-01"));

        $format = $request->input('format', 'json'); // Default to JSON

        $mode = $request->input('mode', 'download'); // Default to file download

        // Fetch lottery draws within the given timeframe
        $draws = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
            ->with(['results', 'total'])
            ->orderBy('draw_date', 'asc')
            ->get()
            ->map(function ($draw) {
                // Starting from the 1st of July 2005, the Romanian LEU was denominated.
                // All the values before that date should be divided by 10,000.
                // More info here https://ro.wikipedia.org/wiki/Denominare
                if (strtotime($draw->draw_date) < strtotime('2005-07-01')) {
                    $draw->total->total_prize /= 10000;
                    $draw->results->each(function ($result) {
                        $result->prize /= 10000;
                        $result->report /= 10000;
                    });
                }
                return $draw;
            });

        return $format === 'csv' ? $this->exportAsCSV($draws, $mode) : $this->exportAsJSON($draws, $mode);
    }

    /**
     * Get the file name and storage path for the export file.
     *
     * @param $format
     * @return array
     */
    private function getExportFilePath($format)
    {
        $fileName = self::_EXPORT_FILE_NAME.'.'.$format;
        $storagePath = storage_path(self::_EXPORT_FILE_STORAGE.$fileName);

        // Check if the storage path exists, if not, create it
        if(!$this->files->exists(storage_path(self::_EXPORT_FILE_STORAGE))) {
            $this->files->makeDirectory(storage_path(self::_EXPORT_FILE_STORAGE), 0777, true);
        }

        return [$fileName, $storagePath];
    }

    /**
     * Export the data as JSON.
     *
     * @param $draws Collection
     * @param $mode
     * @return ResponseFactory|Application|JsonResponse|Response|StreamedResponse
     */
    private function exportAsJSON(Collection $draws, $mode)
    {
        [$jsonFileName, $storagePath] = $this->getExportFilePath('json');

        $exportData = $draws->map(function ($draw) {
            return [
                'date' => $draw->draw_date,
                'numbers' => explode(',', $draw->numbers),
                'results' => $draw->results->map(function ($result) {
                    return [
                        'category' => $result->category,
                        'winners' => $result->winners,
                        'prize' => $result->prize,
                        'report' => $result->report,
                    ];
                }),
                'total_prize' => $draw->total ? $draw->total->total_prize : null,
            ];
        })->toJson(JSON_PRETTY_PRINT); // Format JSON for readability

        switch($mode) {
            case 'save':
                $this->files->put($storagePath, $exportData);

            case 'download':
                return response()->streamDownload(function() use ($exportData) {
                    echo $exportData;
                }, $jsonFileName, [
                    'Content-Type' => 'application/json',
                    'Content-Disposition' => 'attachment; filename="'.$jsonFileName.'"',
                ]);

            case 'view':
            default:
                return response($exportData, 200, [
                    'Content-Type' => 'application/json',
                ]);
        }
    }

    /**
     * Export the data as CSV.
     *
     * @param $draws Collection
     * @param $mode
     * @return ResponseFactory|Application|JsonResponse|Response|StreamedResponse
     */
    private function exportAsCSV(Collection $draws, $mode)
    {
        [$csvFileName, $storagePath] = $this->getExportFilePath('csv');

        // Capture CSV output in memory
        $handle = fopen('php://temp', 'w+');

        // Set CSV headers
        fputcsv($handle, ['Draw Date', 'Numbers', 'Category', 'Winners', 'Prize', 'Report', 'Total Prize Fund']);

        foreach($draws as $draw) {
            foreach($draw->results as $result) {
                fputcsv($handle, [
                    $draw->draw_date,
                    $draw->numbers,
                    $result->category,
                    $result->winners,
                    $result->prize,
                    $result->report,
                    null // No total prize on result rows
                ]);
            }

            // Include total prize row separately
            if($draw->total) {
                fputcsv($handle, [
                    $draw->draw_date,
                    $draw->numbers,
                    null, null, null, null,
                    $draw->total->total_prize
                ]);
            }
        }

        // Rewind the memory stream to the beginning
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        switch($mode) {
            case 'view':
                return response($csvContent, 200)
                    ->header('Content-Type', 'text/plain');

            case 'save':
                $this->files->put($storagePath, $csvContent);

                return response()->json(['message' => "File saved successfully", 'path' => $storagePath]);

            case 'download':
            default:
                return response()->streamDownload(function() use ($csvContent) {
                    echo $csvContent;
                }, $csvFileName, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="'.$csvFileName.'"',
                ]);
        }
    }

    /**
     * Load available draws dates from the database.
     *
     * Example: /lotoro-draws?from-date=2020-01-01&to-date=2021-12-31
     * Example: /lotoro-draws?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDrawsDates(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type');

        $query = LotoRoDraw::select('draw_date')
            ->whereBetween('draw_date', [$fromDate, $toDate]);

        if($drawType) {
            $query->where('draw_type', $drawType);
        }

        $draws = $query->orderBy('draw_date', 'desc')
            ->pluck('draw_date');

        return response()->json($draws);
    }

    /**
     * Load a specific draw from the database.
     *
     * Example: /lotoro-draw?draw_date=2022-01-01
     * Example: /lotoro-draw?draw_date=2022-01-01&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDraw(Request $request): JsonResponse
    {
        $drawDate = $request->input('draw_date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        $query = LotoRoDraw::where('draw_date', $drawDate)
            ->with(['results', 'total']);

        if($drawType) {
            $query->where('draw_type', $drawType);
        }

        $draw = $query->first();

        if($draw) {
            // Starting from the 1st of July 2005, the Romanian LEU was denominated.
            // All the values before that date should be divided by 10,000.
            // More info here https://ro.wikipedia.org/wiki/Denominare
            if(strtotime($draw->draw_date) < strtotime('2005-07-01')) {
                $draw->total->total_prize /= 10000;

                $draw->results->each(function($result) {
                    $result->prize /= 10000;
                    $result->report /= 10000;
                });
            }

            $draw->numbers = $draw->numbers_array; // Accessing the numbers as an array
        }

        return response()->json($draw);
    }

    /**
     * Get the most drawn numbers for a specific draw type and in a given time interval.
     *
     * Example: /lotoro-most-drawn-numbers?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49&limit=6
     *
     * You will get a structure like this: drawn_number => how many times it was drawn
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMostDrawnNumbers(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);
        $limit = $request->input('limit', 6);

        // Get all drawn numbers from the database
        $draws = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
            ->when($drawType, function($query) use($drawType) {
                return $query->where('draw_type', $drawType);
            })->pluck('numbers');

        // Flatten numbers into an array and count occurrences
        $mostDrawn = collect($draws)
            ->flatMap(fn($numbers) => explode(',', $numbers)) // Split numbers
            ->map(fn($num) => (int) trim($num)) // Convert to integer and trim spaces
            ->countBy()
            ->sortByDesc(fn($count, $number) => $count)
            ->take($limit);

        return response()->json($mostDrawn);
    }

    /**
     * Get the least drawn numbers for a specific draw type and in a given time interval.
     *
     * Example: /lotoro-least-drawn-numbers?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49&limit=6
     *
     * You will get a structure like this: drawn_number => how many times it was drawn
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLeastDrawnNumbers(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);
        $limit = $request->input('limit', 6);

        // Get all drawn numbers from the database
        $draws = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
            ->when($drawType, function($query) use($drawType) {
                return $query->where('draw_type', $drawType);
            })->pluck('numbers');

        // Flatten numbers into an array and count occurrences
        $leastDrawn = collect($draws)
            ->flatMap(fn($numbers) => explode(',', $numbers))
            ->map(fn($num) => (int) trim($num)) // Convert to integer and trim spaces
            ->countBy()
            ->sortBy(fn($count, $number) => $count)
            ->take($limit)
            ->reverse();

        return response()->json($leastDrawn);
    }

    /**
     * Get the prizes amount distribution for a specific draw type and in a given time interval.
     *
     * Example: /lotoro-prizes-distribution?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPrizesDistribution(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        $amounts = LotoRoResult::whereHas('draw', function($query) use($fromDate, $toDate, $drawType) {
            $query->whereBetween('draw_date', [$fromDate, $toDate])
                ->where('draw_type', $drawType);
        })->selectRaw('category, SUM(prize) as amount')
            ->groupBy('category')
            ->orderBy('amount', 'desc')
            ->get()
            ->map(function($prize) {
                // Starting from the 1st of July 2005, the Romanian LEU was denominated.
                // All the values before that date should be divided by 10,000.
                // More info here https://ro.wikipedia.org/wiki/Denominare
                $drawDate = LotoRoDraw::where('id', $prize->draw_id)->value('draw_date');

                if(strtotime($drawDate) < strtotime('2005-07-01')) {
                    $prize->amount /= 10000;
                }

                return $prize;
            });

        return response()->json($amounts);
    }

    /**
     * Get the total prize fund for a specific draw type and in a given time interval.
     *
     * Example: /lotoro-total-prize-fund?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPrizeFund(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        $amount = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
            ->where('draw_type', $drawType)
            ->get()
            ->reduce(function($carry, $draw) {
                $prize = LotoRoTotal::where('draw_id', $draw->id)->value('total_prize');

                // Starting from the 1st of July 2005, the Romanian LEU was denominated.
                // All the values before that date should be divided by 10,000.
                // More info here https://ro.wikipedia.org/wiki/Denominare
                if(strtotime($draw->draw_date) < strtotime('2005-07-01')) {
                    $prize /= 10000;
                }

                return $carry + $prize;
            }, 0);

        return response()->json([number_format($amount, 2, '.', '')]);
    }

    /**
     * Get the total number of winners for a specific draw type and in a given time interval.
     *
     * Example: /lotoro-total-winners?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWinners(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR . '-0' . self::_START_MONTH . '-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        $totalWinners = LotoRoResult::whereHas('draw', function ($query) use ($fromDate, $toDate, $drawType) {
            $query->whereBetween('draw_date', [$fromDate, $toDate])
                ->where('draw_type', $drawType);
        })->sum('winners');

        $winnersData = LotoRoResult::whereHas('draw', function ($query) use ($fromDate, $toDate, $drawType) {
            $query->whereBetween('draw_date', [$fromDate, $toDate])
                ->where('draw_type', $drawType);
        })->get(['winners', 'prize', 'category', 'draw_id']);

        // Group by category first
        $groupedWinners = $winnersData->groupBy('category')->map(function ($results, $category) {
            $totalWinners = $results->sum(fn($result) => (int) $result->winners);

            $totalPrize = $results->sum(function ($result) {
                $prize = $result->prize ?? '0';
                $prize = floatval(str_replace(['.', ','], ['', '.'], trim($prize))); // Convert to float
                $drawDate = LotoRoDraw::where('id', $result->draw_id)->value('draw_date');

                // Starting from the 1st of July 2005, the Romanian LEU was denominated.
                // All the values before that date should be divided by 10,000.
                // More info here https://ro.wikipedia.org/wiki/Denominare
                if(strtotime($drawDate) < strtotime('2005-07-01')) {
                    $prize /= 10000;
                }

                return $prize;
            });

            return [
                'category' => $category,
                'total_winners' => $totalWinners,
                'prize_per_winner' => $totalWinners > 0 ? number_format($totalPrize / $totalWinners, 2, '.', '') : 0,
            ];
        });

        return response()->json([
            'total_winners' => $totalWinners,
            'winners_with_prizes' => $groupedWinners->values(), // Reset numeric keys in JSON response
        ]);
    }

    /**
     * Numbers that were not drawn in a given time interval.
     *
     * Example: /lotoro-not-drawn-numbers?from-date=2020-01-01&to-date=2021-12-31&draw_type=6/49
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNotDrawnNumbers(Request $request): JsonResponse
    {
        $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
        $toDate = $request->input('to-date', date('Y-m-d'));
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        $draws = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
            ->where('draw_type', $drawType)
            ->pluck('numbers');

        $drawnNumbers = collect($draws)
            ->flatMap(fn($numbers) => explode(',', $numbers))
            ->map(fn($num) => (int) trim($num))
            ->unique()
            ->values()
            ->toArray();

        $allNumbers = range(1, $drawType == LotoDrawType::LOTO_649 ? 49 : 40);

        $notDrawn = array_diff($allNumbers, $drawnNumbers);

        return response()->json(array_values($notDrawn));
    }

    /**
     * Numbers generator for the 6/49 lottery game.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateNumbers(Request $request): JsonResponse
    {
        $drawType = $request->input('draw_type', LotoDrawType::LOTO_649);

        if($request->input('probability')) {
            $fromDate = $request->input('from-date', self::_START_YEAR.'-0'.self::_START_MONTH.'-01');
            $toDate = $request->input('to-date', date('Y-m-d'));

            $draws = LotoRoDraw::whereBetween('draw_date', [$fromDate, $toDate])
                ->where('draw_type', $drawType)
                ->pluck('numbers');

            $drawnNumbers = collect($draws)
                ->flatMap(fn($numbers) => explode(',', $numbers))
                ->map(fn($num) => (int) trim($num))
                ->countBy();

            $numbers = range(1, $drawType == LotoDrawType::LOTO_649 ? 49 : 40);

            shuffle($numbers);
            $selectedNumbers = array_slice($numbers, 0, 6);

            $probability = 1;

            // Loop through each selected number
            foreach($selectedNumbers as $number) {
                // Calculate the probability of the selected number being drawn
                // The formula used is: (number of times the number was drawn + 1) / (total number of draws + 49)
                $probability *= ($drawnNumbers->get($number, 0) + 1) / ($drawnNumbers->sum() + 49);
            }

            return response()->json([
                'numbers' => $selectedNumbers,
                'probability' => $probability
            ]);
        } else {
            $numbers = range(1, $drawType == LotoDrawType::LOTO_649 ? 49 : 40);
            shuffle($numbers);

            return response()->json(array_slice($numbers, 0, 6));
        }
    }
}
