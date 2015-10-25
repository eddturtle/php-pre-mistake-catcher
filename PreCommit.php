#!/usr/bin/php
<?php

/*
 * Ideas: replace version_id with tag no, find deprecated fns.
 */

$app = new PreCommit();

/**
 * The logic behind to entire pre-commit PHP checking script,
 * it all starts here.
 */
class PreCommit
{
    CONST WARNING = 1;
    CONST ERROR = 2;

    protected $projectName;
    protected $projectFiles;

    /**
     * @var array
     */
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
        $this->projectName = ucfirst(basename(getcwd()));
        $this->init();
    }

    /**
     * All the pre-processing should be complete by now, so start the script.
     */
    public function init()
    {
        if ($this->checkEnv()) {
            $this->findProjectFiles();

            $this->checkProjectFor(["  dd(", PHP_EOL . "dd("], "dd()", self::WARNING);
            $this->checkProjectFor("<<<<<<<", "git conflict", self::WARNING);

            if ($this->projectHasPhpUnit()) {
                $this->runTests();
            }
        }
    }

    /**
     * Check to make sure this skip can run by checking environment for programs
     * installed and their respective versions.
     *
     * @return bool if the environment is capable.
     */
    public function checkEnv()
    {
        if (version_compare(phpversion(), '5.4', '<')) {
            Tools::text("Insufficient PHP Version (skipping pre-commit tests)", "red");
            return false;
        }
        return true;
    }

    /**
     * Populate $this->projectFiles with all the php files in your project.
     *
     * This will find all files with the file extensions listed in options['file_endings'] if
     * they are not in any of the restricted folders listed in options['folder_exceptions'].
     */
    public function findProjectFiles()
    {
        $this->projectFiles = Tools::findFiles(
            $this->options['project_root'],
            $this->options['file_endings'],
            $this->options['folder_exceptions']
        );
        $this->projectFiles = Tools::flatten($this->projectFiles);
        Tools::text("Found " . count($this->projectFiles) . " project files");
    }

    /**
     * Look in every project file to see if a string exists.
     *
     * @param string $lookFor the exact string to look for.
     * @param string $whatIsIt what the string should be called in the message if found.
     * @param int $severity whether it's a warning or error.
     *
     * @return int the number of times the string is found.
     */
    public function checkProjectFor($lookFor, $whatIsIt, $severity)
    {
        $occurrences = 0;
        foreach ($this->projectFiles as $fileName => $content) {
            if (Tools::contains($lookFor, $content)) {
                $message = $whatIsIt . " found in " . $fileName;
                if ($severity === self::WARNING) {
                    Tools::text("Warning: " . $message, "brown");
                } else if ($severity === self::ERROR) {
                    Tools::text("Error: " . $message, "red");
                }
                $occurrences++;
            }
        }
        return $occurrences;
    }

    public function projectHasPhpUnit()
    {
        return isset($this->projectFiles['./' . $this->options['phpunit_config']]);
    }

    /**
     * Begin the testing process by running phpunit, this will ask you if you would
     * like to run tests before doing it.
     */
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
            } else {
                // Tests Succeeded
                Tools::text("Tests Passed.", "green");
            }

        }, function () {
            Tools::text("Skipping Tests.", "red");
        });
    }

    /**
     * Run PHPUnit through the cli and return any output and exit status.
     *
     * @return array [$output, $returnCode]
     */
    public function runPhpUnit()
    {
        exec("phpunit --configuration " . $this->options['phpunit_config'], $output, $returnCode);
        return [
            $output,
            $returnCode
        ];
    }
}

/**
 * This is a very a utility class, to aid the PreCommit class while
 * not containing any specifics on how the script is being run.
 */
class Tools
{

    /**
     * Ask a question to the user through the cli.
     *
     * @param string $question a message to display before the input.
     * @param closure $positive the function to run if the input was yes.
     * @param closure $negative the function to run if the input was no.
     *
     * @return mixed the result of the closure being run.
     */
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

    /**
     * Print text to screen with a new line at the end with the option to
     * choose text color.
     *
     * @param string $text the text to print.
     * @param string $color the color name (if it exists in the colors array).
     * @param bool $newLine whether to add a new line at the end.
     */
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

    /**
     * Exit the application with an unsuccessful state.
     */
    public static function abortWithoutCommit()
    {
        exit(1);
    }

    /**
     * A recursive function to find all the project files within a directory.
     *
     * @param string $folder the starting point, your root folder.
     * @param array $fileEndings a list of file endings you are look for (empty will find all).
     * @param array $exceptions a list of folders to ignore.
     *
     * @return array of files with their name as the key and content as value.
     */
    public static function findFiles($folder = ".", $fileEndings = [], $exceptions = [])
    {
        // Make sure the folder isn't in the exceptions list
        // (and early return if it is).
        foreach (explode('/', $folder) as $subfolder) {
            if (in_array($subfolder, $exceptions)) {
                return false;
            }
        }

        $projectFiles = [];
        $files = scandir($folder);
        foreach ($files as $fileName) {
            if ($fileName !== "." && $fileName !== ".." && strpos($fileName, '.') !== 0) {
                $fullDir = $folder . "/" . $fileName;
                if (is_dir($fullDir)) {
                    $foundFiles = self::findFiles($fullDir, $fileEndings, $exceptions);
                    if ($foundFiles) {
                        $projectFiles[$fullDir] = $foundFiles;
                    }
                } else {
                    $extension = end(explode('.', $fileName));
                    if (empty($fileEndings) || in_array($extension, $fileEndings)) {
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

    public static function contains($needle, $haystack)
    {
        foreach ((array)$needle as $item) {
            if (strpos($haystack, $item) !== false) {
                return true;
            }
        }
        return false;
    }
}