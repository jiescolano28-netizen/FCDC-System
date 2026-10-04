<?php

namespace App\Support;

final class IllustrativeSales
{
    public const SERIES_BY_PERIOD = [
        'week' => [
            ['label' => 'Mon', 'total' => 690.1], ['label' => 'Tue', 'total' => 154.2],
            ['label' => 'Wed', 'total' => 875.3], ['label' => 'Thu', 'total' => 218.6],
            ['label' => 'Fri', 'total' => 1440.75], ['label' => 'Sat', 'total' => 708.4],
            ['label' => 'Sun', 'total' => 0],
        ],
        'month' => [
            ['label' => 'Wk 1', 'total' => 3120.4], ['label' => 'Wk 2', 'total' => 2840.1],
            ['label' => 'Wk 3', 'total' => 4087.25], ['label' => 'Wk 4', 'total' => 3612.9],
        ],
        'year' => [
            ['label' => 'Jan', 'total' => 9840.2], ['label' => 'Feb', 'total' => 8720.5],
            ['label' => 'Mar', 'total' => 10230.75], ['label' => 'Apr', 'total' => 9560.4],
            ['label' => 'May', 'total' => 11040.6], ['label' => 'Jun', 'total' => 10380.15],
            ['label' => 'Jul', 'total' => 12100.9], ['label' => 'Aug', 'total' => 13780.35],
            ['label' => 'Sep', 'total' => 0], ['label' => 'Oct', 'total' => 0],
            ['label' => 'Nov', 'total' => 0], ['label' => 'Dec', 'total' => 0],
        ],
    ];
}
