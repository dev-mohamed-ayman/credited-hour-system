<?php

if (! function_exists('isActiveRoute')) {
    function isActiveRoute(string|array $routeName, bool $isOpenOutput = false): string
    {
        $output = 'active';
        if (request()->routeIs($routeName)) {
            return $isOpenOutput ? $output.' open' : $output;
        }

        return '';
    }
}

if (! function_exists('currentYear')) {
    function currentYear(): ?\App\Models\Year
    {
        return \App\Models\Year::current();
    }
}

if (! function_exists('currentSemester')) {
    function currentSemester(): ?\App\Enums\Semester
    {
        return \App\Models\Year::currentSemester();
    }
}
