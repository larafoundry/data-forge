<?php

declare(strict_types=1);

use Carbon\Carbon;

require_once __DIR__.'/vendor/autoload.php';

$date = Carbon::createFromFormat('Y-m-d', '2022-01-01');
dd($date);
