<?php

if (!function_exists('setActive')) {
    /**
     * @param string|array $routes
     * @param string $output
     * @return string
     */
    function setActive($routes, $output = 'active')
    {
        $routes = (array) $routes;

        foreach ($routes as $route) {
            if (request()->routeIs($route)) {
                return $output;
            }
        }

        return 'active';
    }
}
