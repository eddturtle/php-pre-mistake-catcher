# PHP Pre-mistake Catcher

A Git hook script to check your project just as you're about to commit, warning you if any problems are detected - saving you from angry employees.

### What does it check for?

At the moment, the script will check for:

* any stray `dd()` functions
* remaining git conflicts (by looking for `<<<<<<<`)

With future features to:
*  check forany syntax errors (by using `php -l`)
* find any deprecated fns and warn.
* run tests from inside a vagrant box properly.

### And.. Tests

The script will also ask you if you'd like to run your tests, with a Y/n option, which will run your PHPUnit tests for you. If any of the tests fail the commit will be aborted.

### How to install

In the root of your PHP project run these commands to pull down the latest script and add it as a git hook.

    wget https://raw.githubusercontent.com/eddturtle/php-pre-mistake-catcher/master/PreCommit.php
    mv PreCommit.php .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
