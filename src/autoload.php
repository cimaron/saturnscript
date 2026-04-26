<?php

spl_autoload_register(function($class) {

    $prefixes = [
        'SaturnScript\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});
