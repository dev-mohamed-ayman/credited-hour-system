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
