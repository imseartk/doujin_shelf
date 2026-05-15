<?php

echo 'ERROR: ' . ($code ?? 404) . PHP_EOL;
echo ($message ?? 'Page not found') . PHP_EOL;
