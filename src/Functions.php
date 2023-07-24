<?php

/**
 * console_log
 */
if (!function_exists('console_log')) {
    function console_log($tag = 'strarting', $content = '')
    {
        echo   "\n" . '----------------' . $tag . '----------------' . "\n" .
            'time:' . date("Y-m-d H:i:s")  . "\n" .
            $content . "\n";
    }
}
