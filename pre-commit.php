#!/usr/bin/php
<?php

$app = new PreCommit();

class PreCommit
{
    CONST WARNING = 1;
    CONST ERROR = 2;

    protected $projectName;
    protected $projectFiles;

    protected $options = [
        'phpunit_config' => 'phpunit.xml',
        'file_endings' => ['php', 'php5'],
        'project_root' => '.',
        'folder_exceptions' => [
            'vendor'
        ]
    ];

    public function __construct()
    {
        // Set properties
        $this->projectName = ucfirst(basename(getcwd()));

        // Start things running...
        if ($this->checkEnv()) {
            $this->findProjectFiles();
            $this->init();
        }
    }

    public function checkEnv()
    {
        if (version_compare(phpversion(), '5.4', '<')) {
            Tools::text("Insufficient PHP Version (skipping pre-commit tests)", "red");
            return false;
        }
        return true;
    }

    public function findProjectFiles()
    {
        $this->projectFiles = Tools::findFiles(
            $this->options['project_root'],
            $this->options['file_endings'],
            $this->options['folder_exceptions']
        );
        Tools::text("Found " . count($this->projectFiles) . " project files");
    }

    public function init()
    {
        $this->checkProjectFor(" dd(", "dd()", self::WARNING);
        $this->checkProjectFor("<<<<<<<", "git conflict", self::ERROR);
        $this->runTests();
    }

    public function checkProjectFor($lookFor, $whatIsIt, $severity)
    {
        $files = Tools::flatten($this->projectFiles);
        foreach ($files as $fileName => $content) {
            if (strpos($content, $lookFor) !== false) {
                $message = $whatIsIt . " found in " . $fileName;
                if ($severity === self::WARNING) {
                    Tools::text("Warning: " . $message, "brown");
                } else if ($severity === self::ERROR) {
                    Tools::text("Error: " . $message, "red");
                }
            }
        }
    }

    public function runTests()
    {
        Tools::ask("Would you like to run tests before commiting?", function () {
            Tools::text("Running Tests...", "green");

            list($output, $returnCode) = $this->runPhpUnit();
            if ($returnCode !== 0) {
                // Tests Failed
                Tools::text("Tests failed for {$this->projectName} with message:", "red");
                Tools::text($output);
                Tools::abortWithoutCommit();
            }

        }, function () {
            Tools::text("Skipping Tests.", "red");
        });
    }

    public function runPhpUnit()
    {
        exec("phpunit --configuration " . $this->options['phpunit_config'], $output, $returnCode);
        return [
            $output,
            $returnCode
        ];
    }
}

class Tools
{
    public static function ask($question, closure $positive, closure $negative)
    {
        self::text($question . " [Y/n] ", null, false);
        $option = trim(exec('exec < /dev/tty && read input && echo $input'));
        if (strtolower($option) !== "n") {
            return call_user_func($positive);
        } else {
            return call_user_func($negative);
        }
    }

    public static function text($text, $color = null, $newLine = true)
    {
        $colors = [
            'red' => 31,
            'green' => 32,
            'brown' => 33
        ];
        $validColor = (!is_null($color) && isset($colors[$color]));
        if ($validColor) {
            echo "\033[1;" . $colors[$color] . "m";
        }

        foreach ((array)$text as $line) {
            echo $line;
        }

        if ($validColor) {
            // Reset back to default colour.
            echo "\033[0m";
        }
        if ($newLine) {
            echo PHP_EOL;
        }
    }

    public static function abortWithoutCommit()
    {
        exit(1);
    }

    public static function findFiles($folder = ".", $fileEndings = [], $exceptions = [])
    {
        if (in_array($folder, $exceptions)) {
            return false;
        }

        $projectFiles = [];
        $files = scandir($folder);
        foreach ($files as $fileName) {
            if ($fileName !== "." && strpos($fileName, '.') !== 0) {
                $fullDir = $folder . "/" . $fileName;
                if (is_dir($fullDir)) {
                    $projectFiles[$fullDir] = self::findFiles($fullDir, $fileEndings, $exceptions);
                } else {
                    $extension = end(explode('.', $fileName));
                    if (in_array($extension, $fileEndings)) {
                        $projectFiles[$fullDir] = file_get_contents($fullDir);
                    }
                }
            }
        }

        return $projectFiles;
    }

    public static function flatten($array)
    {
        // http://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array
        $return = [];
        array_walk_recursive($array, function ($a, $key) use (&$return) {
            $return[$key] = $a;
        });
        return $return;
    }
}