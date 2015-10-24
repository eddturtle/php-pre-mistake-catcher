#!/usr/bin/php
<?php

$reset = "\033[0m";
$green = "\033[1;32m";
$red = "\033[1;31m";
$brown = "\033[1;33m";

$projectName = ucfirst(basename(getcwd()));
echo $brown . "Committing to " . $projectName . $reset . PHP_EOL;


// ====================
// Check Code
// ====================

$phpFileDirs = [
    'app'
];
foreach ($phpFileDirs as $fileDir) {

    $files = scandir($fileDir);
    foreach ($files as $fileName) {

        if (strpos($fileName, '.php') !== false) {

            $content = file_get_contents($fileDir . '/' . $fileName);

            // Check for dd()
            if (strpos($content, ' dd(') !== false || strpos($content, PHP_EOL . 'dd(') !== false) {
                echo $red . "Warning: dd() detected in " . $fileName . $reset . PHP_EOL;
            }

            // Check for git conflicts
            if (strpos($content, '<<<<<<<') !== false) {
                echo $red . "Warning: git conflict (<<<<<<<) detected in " . $fileName . $reset . PHP_EOL;
            }
        }
    }
}


// ====================
// Check for syntax errors
// ====================

exec('cd public/app/classes && find . -name \*.php -exec php -l "{}" \;', $output);
foreach ($output as $line) {
   if (strpos($line, 'Errors parsing ') === 0) {
       echo $red . $line . $reset . PHP_EOL;
   }
}


// ====================
// Tests
// ====================

echo "Would you like to run tests before commiting? [Y/n] ";

$option = trim(exec('exec < /dev/tty && read input && echo $input'));
if (strtolower($option) !== "n") {
    
    echo $green . "Running Tests..." . $reset . PHP_EOL;
    exec("phpunit --configuration phpunit.xml", $output, $returnCode);

    if ($returnCode !== 0) {
        $minimalTestSummary = array_pop($output);
        printf($red . "Test suite for %s failed: " . $reset, $projectName);
        printf("( %s ) %s%2\$s", $minimalTestSummary, PHP_EOL);
        exit(1);
    }
    printf($green . "All tests for %s passed.%s%2\$s" . $reset, $projectName, PHP_EOL);

} else {
    echo $red . "Skipping Tests" . $reset . PHP_EOL;
}

exit(0);