<?php

if (! function_exists('isActiveRoute')) {
    function isActiveRoute(string|array $routeName, string|bool $type = 'active'): string
    {
        if (! request()->routeIs($routeName)) {
            return '';
        }

        return match ($type) {
            'open' => 'open',
            'active' => 'active',
            'both', true => 'active open',
            default => '',
        };
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
