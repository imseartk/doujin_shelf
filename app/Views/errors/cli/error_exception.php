<?php

echo '[' . ($exception::class ?? 'Exception') . ']' . PHP_EOL;
echo ($message ?? ($exception->getMessage() ?? 'Unknown error')) . PHP_EOL;
if (isset($exception)) {
    echo 'at ' . clean_path($exception->getFile()) . ':' . $exception->getLine() . PHP_EOL;
}
