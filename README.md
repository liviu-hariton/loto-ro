# LotoRo - Romanian Lottery Results for Laravel

LotoRo is a Laravel package that automates the retrieval, processing, and storage of Romanian lottery results for the 6/49 and 5/40 draw types. The package fetches official draw data, extracts winning numbers and prize distributions, and provides structured access to historical lottery results via an API and CLI commands.

### Key Features
* Fetches **6/49** and **5/40** lottery results from official sources
* Parses and stores draw dates, numbers, categories, and prize distributions
* Provides **API endpoints** to retrieve the most and least drawn numbers
* Offers **CLI commands** to fetch results efficiently
* Supports filtering by **date range** and **draw type**
* Includes **denomination adjustments** for pre-2005 Romanian LEU values

### Ideal For
* Developers needing **structured lottery data**
* Analysts tracking **number frequencies** and **historical trends**
* Websites displaying **Romanian lottery results**

## Table of Contents

* [Requirements](#requirements)
* [Installation](#installation)
* [Usage](#usage)
  * [CLI Commands](#cli-commands)
    * [Fetch Lottery Results](#fetch-lottery-results)
    * [Export Lottery Results](#export-lottery-results)
  * [API Endpoints](#api-endpoints)
    * [Fetch the lottery results from the source](#fetch-the-lottery-results-from-the-source)
    * [Export the lottery results](#export-the-lottery-results)
    * [Get the draw dates](#get-the-draw-dates)
    * [Get the draw data](#get-the-draw-data)
    * [Get the most drawn numbers](#get-the-most-drawn-numbers)
    * [Get the least drawn numbers](#get-the-least-drawn-numbers)
    * [Get the prizes distribution](#get-the-prizes-distribution)
    * [Get the total prize fund](#get-the-total-prize-fund)
    * [Get the total winners](#get-the-total-winners)
    * [Get not drawn numbers](#get-not-drawn-numbers)
    * [Generate random numbers](#generate-random-numbers)
  * [Custom Controllers](#custom-controllers)
* [Uninstallation](#uninstallation)
* [License](#license)
* [Disclaimer](#disclaimer)
* [Gambling problems](#the-gambling-problems-can-be-solved)

## Requirements
* PHP >= 8.3
* [Laravel](https://github.com/laravel/laravel) >= 11.0
* ext-dom PHP extension
  * it provides access to the [DOMDocument](https://www.php.net/manual/en/class.domdocument.php) and [DOMXPath](https://www.php.net/manual/en/class.domxpath.php) classes

## Installation

You can install the **LotoRo Laravel package** via Composer. Run the following command in your terminal:

```bash
composer require liviu-hariton/loto-ro
```
Laravel will automatically register the package.

Publish and run the package migrations with the following commands:

```bash
php artisan vendor:publish --tag="loto-ro-migrations"
php artisan migrate
```
The migrations will create the following tables in your database: `lotoro_draws`, `lotoro_results` and `lotoro_totals`.

Run the installation command to chose how the routes should be registered:

```bash
php artisan lotoro:install
```

First, you will be asked if you want to automatically register the default routes. If you chose `no`, you'll have the option to publish them in the next question. If you chose to publish the routes, you'll find them in the `routes` directory of your Laravel application, in the `lotoro_routes.php` file.

Otherwise, you can manually register the routes in your `routes/web.php` or `routes/api.php` (learn more about the [Laravel API Routes](https://laravel.com/docs/11.x/routing#api-routes)) file:

```php
<?php

use Illuminate\Support\Facades\Route;
use LHDev\LotoRo\Http\Controllers\LotoRo;

/**
* These routes are for fetching data from the source and save it to the local database
*/
Route::get('/lotoro-649', [LotoRo::class, 'fetch649'])->name('lotoro-649');
Route::get('/lotoro-540', [LotoRo::class, 'fetch540'])->name('lotoro-540');

/**
* These routes are for loading the data from the local database
*/
Route::get('/lotoro-export', [LotoRo::class, 'exportData'])->name('lotoro-export');
Route::get('/lotoro-draws', [LotoRo::class, 'getDrawsDates'])->name('lotoro-draws');
Route::get('/lotoro-draw', [LotoRo::class, 'getDraw'])->name('lotoro-draw');
Route::get('/lotoro-most-drawn-numbers', [LotoRo::class, 'getMostDrawnNumbers'])->name('lotoro-most-drawn-numbers');
Route::get('/lotoro-least-drawn-numbers', [LotoRo::class, 'getLeastDrawnNumbers'])->name('lotoro-least-drawn-numbers');
Route::get('/lotoro-prizes-distribution', [LotoRo::class, 'getPrizesDistribution'])->name('lotoro-prizes-distribution');
Route::get('/lotoro-total-prize-fund', [LotoRo::class, 'getPrizeFund'])->name('lotoro-total-prize-fund');
Route::get('/lotoro-total-winners', [LotoRo::class, 'getWinners'])->name('lotoro-total-winners');
```
## Usage

You can use the **LotoRo Laravel package** to fetch, store, and access Romanian lottery results in various ways:

* **CLI Commands**: Fetch and access the lottery results via the `php artisan` command lines
  * [Fetch Lottery Results](#fetch-lottery-results)
  * [Export Lottery Results](#export-lottery-results)
* **API Endpoints**: Access the lottery results via HTTP requests
  * [Fetch the lottery results from the source](#fetch-the-lottery-results-from-the-source)
  * [Export the lottery results](#export-the-lottery-results)
  * [Get the draw dates](#get-the-draw-dates)
  * [Get the draw data](#get-the-draw-data)
  * [Get the most drawn numbers](#get-the-most-drawn-numbers)
  * [Get the least drawn numbers](#get-the-least-drawn-numbers)
  * [Get the prizes distribution](#get-the-prizes-distribution)
  * [Get the total prize fund](#get-the-total-prize-fund)
  * [Get the total winners](#get-the-total-winners)
  * [Get not drawn numbers](#get-not-drawn-numbers)
  * [Generate random numbers](#generate-random-numbers)
* **[Custom Controllers](#custom-controllers)**: Use your custom controllers to handle the lottery results

### CLI Commands

The package provides two CLI commands to fetch and manage the Romanian lottery results:

#### Fetch Lottery Results

You can fetch the lottery results from the official source by using the following command:

```bash
php artisan lotoro:fetch
```
The command accepts the following options:
* `--type=649` or `--type=540` or `--type=all`: Specify the draw type (6/49 or 5/40 or both), **mandatory**
* `--from-year=YYYY`: The year from which to fetch data (4 digits), optional. Default is the year 1998
* `--from-month=M`: The month from which to fetch data (without leading zeros), optional. Default is the month 1 (January)
* `--to-year=YYYY`: The year to fetch data up to (4 digits), optional. Default is the current year
* `--to-month=M`: The month to fetch data up to (without leading zeros), optional. Default is the current month

For example, to fetch all 6/49 lottery results from 2000 to 2020, you can run the following command:

```bash
php artisan lotoro:fetch --type=649 --from-year=2000 --to-year=2020
```
Note that any previously fetched data will be updated by the new data.

#### Export Lottery Results

You can export the previously saved lottery results by using the following command:

```bash
php artisan lotoro:export
```
The command accepts the following options:

* `--from-year=YYYY`: The starting year for the data export (4 digits), optional. Default is the year 1998
* `--from-month=M`: The starting month for the data export (without leading zeros), optional. Default is the month 1 (January)
* `--to-year=YYYY`: The ending year for the data export (4 digits), optional. Default is the current year
* `--to-month=M`: The ending month for the data export (without leading zeros), optional. Default is the current month
* `--format=JSON` : The format of the export (JSON or CSV), optional - default is **JSON**
* `--mode=save `: The mode of the export (view, download, save) - default is **save**
  * if you chose **save** then the file will be saved in the chosen format - JSON or CSV - in the `storage/app/exports` directory, under the name `lotoro_data.json` or `lotoro_data.csv`

For example, to export all 6/49 lottery results from 2000 to 2020 in CSV format, you can run the following command:

```bash
php artisan lotoro:export --from-year=2000 --to-year=2020 --format=CSV
```
Sample data for the **JSON format**:

```json
[
  {
    "date": "1998-01-04",
    "numbers": [
      "17",
      "23",
      "5",
      "35",
      "34",
      "24"
    ],
    "results": [
      {
        "category": "I (6/6)",
        "winners": "11",
        "prize": 36669.0941,
        "report": 0
      },
      {
        "category": "II (5/6)",
        "winners": "1.511",
        "prize": 336.4935,
        "report": 0
      },
      {
        "category": "III (4/6)",
        "winners": "82.457",
        "prize": 6.0448,
        "report": 0
      },
      {
        "category": "IV (3/6)",
        "winners": "REPORT",
        "prize": 0,
        "report": 0
      }
    ],
    "total_prize": 1410243.4298
  }
]
```

Sample data for the **CSV format**:

```csv
"Draw Date",Numbers,Category,Winners,Prize,Report,"Total Prize Fund"
1998-01-04,"17,23,5,35,34,24","I (6/6)",11,36669.0941,0,
1998-01-04,"17,23,5,35,34,24","II (5/6)",1.511,336.4935,0,
1998-01-04,"17,23,5,35,34,24","III (4/6)",82.457,6.0448,0,
1998-01-04,"17,23,5,35,34,24","IV (3/6)",REPORT,0,0,
1998-01-04,"17,23,5,35,34,24",,,,,1410243.4298
```

### Api Endpoints

Given the default routes, the package provides several API endpoints to access the Romanian lottery results, via GET requests. Feel free to use the [sample Postman collection](LotoRo.postman_collection.json) provided in this package.

#### Fetch the lottery results from the source

Endpoint: `/lotoro-649` or `/lotoro-540`

Available parameters:

* `from-year`: The starting year for the data export (4 digits), optional. Default is the year 1998
* `from-month`: The starting month for the data export (without leading zeros), optional. Default is the month 1 (January)
* `to-year`: The ending year for the data export (4 digits), optional. Default is the current year
* `to-month`: The ending month for the data export (without leading zeros), optional. Default is the current month

#### Export the lottery results

Endpoint: `/lotoro-export`

Available parameters:

* `from-year`: The starting year for the data export (4 digits), optional. Default is the year 1998
* `from-month`: The starting month for the data export (without leading zeros), optional. Default is the month 1 (January)
* `to-year`: The ending year for the data export (4 digits), optional. Default is the current year
* `to-month`: The ending month for the data export (without leading zeros), optional. Default is the current month
* `format`: The format of the export (JSON or CSV), optional - default is **JSON**
* `mode`: The mode of the export (view, download, save) - default is **save**

#### Get the draw dates

Load available draws dates from the database

Endpoint: `/lotoro-draws`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49

Response sample:

```json
[
    "1998-01-25",
    "1998-01-18",
    "1998-01-11",
    "1998-01-04"
]
```

#### Get the draw data

Load a specific draw from the database

Endpoint: `/lotoro-draw`

Available parameters:

* `draw_date`: The date of the draw (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (6/49 or 5/40). Default is 6/49

Response sample:

```json
{
  "id": 3,
  "draw_type": "6/49",
  "draw_date": "1998-01-18",
  "numbers": [
    "16",
    "33",
    "21",
    "8",
    "23",
    "43"
  ],
  "created_at": "2025-01-31T20:38:15.000000Z",
  "updated_at": "2025-02-02T20:33:23.000000Z",
  "results": [
    {
      "id": 9,
      "draw_id": 3,
      "category": "I (6/6)",
      "winners": "4",
      "prize": 41158.5998,
      "report": 0
    },
    {
      "id": 10,
      "draw_id": 3,
      "category": "II (5/6)",
      "winners": "805",
      "prize": 253.51,
      "report": 0
    },
    {
      "id": 11,
      "draw_id": 3,
      "category": "III (4/6)",
      "winners": "41.233",
      "prize": 4.9493,
      "report": 0
    },
    {
      "id": 12,
      "draw_id": 3,
      "category": "IV (3/6)",
      "winners": "REPORT",
      "prize": 0,
      "report": 0
    }
  ],
  "total": {
    "id": 3,
    "draw_id": 3,
    "total_prize": 572785.5338
  }
}
```

#### Get the most drawn numbers

Get the most drawn numbers for a specific draw type and in a given time interval.

Endpoint: `/lotoro-most-drawn-numbers`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49
* `limit`: The number of most drawn numbers to return, optional. Default is 6

Response sample:

```json
{
  "23": 2,
  "27": 2,
  "17": 1,
  "5": 1,
  "35": 1,
  "34": 1,
  "24": 1,
  "32": 1,
  "20": 1,
  "28": 1
}
```

#### Get the least drawn numbers

Get the least drawn numbers for a specific draw type and in a given time interval.

Endpoint: `/lotoro-least-drawn-numbers`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 649
* `limit`: The number of most drawn numbers to return, optional. Default is 6

Response sample:

```json
{
  "32": 1,
  "24": 1,
  "34": 1,
  "35": 1,
  "5": 1,
  "17": 1
}
```

#### Get the prizes distribution

Get the prizes amount distribution for a specific draw type and in a given time interval.

Endpoint: `/lotoro-prizes-distribution`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49

Response sample:

```json
[
  {
    "category": "I (6/6)",
    "amount": 210777.2749
  },
  {
    "category": "II (5/6)",
    "amount": 1194.5241
  },
  {
    "category": "III (4/6)",
    "amount": 21.9716
  },
  {
    "category": "IV (3/6)",
    "amount": 0
  }
]
```

#### Get the total prize fund

Get the total prize fund for a specific draw type and in a given time interval.

Endpoint: `/lotoro-total-prize-fund`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49

Response sample:

```json
[
  "2647776.87"
]
```

#### Get the total winners

Get the total number of winners for a specific draw type and in a given time interval.

Endpoint: `/lotoro-total-winners`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49

Response sample:

```json
{
  "total_winners": 1874.266,
  "winners_with_prizes": [
    {
      "category": "I (6/6)",
      "total_winners": 15,
      "prize_per_winner": "1405181.83"
    },
    {
      "category": "II (5/6)",
      "total_winners": 1686,
      "prize_per_winner": "70.85"
    },
    {
      "category": "III (4/6)",
      "total_winners": 171,
      "prize_per_winner": "12.85"
    },
    {
      "category": "IV (3/6)",
      "total_winners": 0,
      "prize_per_winner": 0
    }
  ]
}
```

#### Get not drawn numbers

Numbers that were not drawn in a given time interval.

Endpoint: `/lotoro-not-drawn-numbers`

Available parameters:

* `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
* `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date
* `draw_type`: The draw type (649 or 540), optional. Default is 6/49

Response sample:

```json
[
  2,
  3,
  4,
  6,
  7,
  9,
  10,
  11,
  14,
  15,
  18,
  19,
  22,
  25,
  31
]
```

#### Generate random numbers

Generate random numbers for a specific draw type.

Endpoint: `/lotoro-generate-numbers`

Available parameters:

* `probability`: Optional. If set, the numbers will be generated based on the probability of each number to be drawn, based on the historical data set with the following parameters:
  * `from-date`: The starting date for the data export (YYYY-MM-DD), optional. Default is the date 1998-01-01
  * `to-date`: The ending date for the data export (YYYY-MM-DD), optional. Default is the current date

Response sample for basic draw:

```json
[
  17,
  47,
  35,
  40,
  46,
  2
]
```

Response sample for draw with probability:

```json
{
  "numbers": [
    30,
    27,
    9,
    3,
    47,
    11
  ],
  "probability": 3.964734314986958e-11
}
```

### Custom Controllers

You can create custom controllers to handle the lottery results in your Laravel application.

Here is an example of a custom controller that fetches the 6/49 lottery draws dates from 1998, the whole year:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LHDev\LotoRo\Http\Controllers\LotoRo;

class Testing extends Controller
{
    public function test(Request $request)
    {
        $lotoRo = new LotoRo();

        $data = [
            'to_date' => '1998-12-31',
        ];

        $request->replace($data);

        $draw_dates = json_decode($lotoRo->getDrawsDates($request)->content(), true);

        dump($draw_dates);
    }
}
````

## Uninstallation

You can uninstall the **LotoRo Laravel package** via Composer. Run the following command in your terminal:

```bash
composer remove liviu-hariton/loto-ro
```
Also, make sure to remove the following files created by the package in your Laravel root directory:
* `/config/lotoro.php`
* `/database/migrations/[Y_m_d_His]_create_lotoro_tables.php`
  * where `[Y_m_d_His]` is the timestamp of when the migration file was created. For example, `2025_01_29_212602_create_lotoro_tables.php`
* `/routes/lotoro_routes.php`

### License
This library is licensed under the MIT License. See the [LICENSE](LICENSE.md) file for more details.

### Disclaimer
I am not affiliated with the Romanian Lottery institution. This package is a personal project and is not endorsed by or associated with the official lottery organization. It was developed for educational purposes only and as a demonstration of Laravel package development. See the [DISCLAIMER](DISCLAIMER.md) file for more details.

### The gambling problems can be solved
Gambling is a form of entertainment, but it can become a problem if you lose control. [Find out how you can get help](GAMBLING_ADDICTION.md).

You can find more details about the Romanian Lottery on the [official website](https://www.loto.ro/).