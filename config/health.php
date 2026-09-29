<?php

use App\Support\Health\CacheCheck;
use App\Support\Health\DatabaseCheck;

return [

    /*
    | Dependencies reported by /readyz. Add a class implementing
    | App\Support\Health\Check to have it included.
    */
    'checks' => [
        'database' => DatabaseCheck::class,
        'cache' => CacheCheck::class,
    ],

];
