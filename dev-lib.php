<?php

function dd() {
    if (php_sapi_name() === 'cli') {
        $args = func_get_args();
        foreach ($args as $arg) {
            var_dump($arg);
        }
    } else {
        echo '<pre>';
        $args = func_get_args();
        foreach ($args as $arg) {
            var_dump($arg);
        }
        echo '</pre>';
    }

    die();
}

function bench($callable, $times = 1000) {
    $start = microtime(true);
    for ($i = 0; $i < $times; $i++) {
        $callable();
    }
    $end = microtime(true);

    $time = $end - $start;

    $time = $time * 1000;

    $time = round($time, 8);

    echo "Time: {$time}ms\n";
    echo "Times: {$times}\n";
    echo "Average: " . round($time / $times, 8) . "ms\n";

    die();
}
