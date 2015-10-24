# PHP Pre-mistake Catcher

A Git hook script to check your project just as you're about to commit, warning you if any problems are detected - saving you from angry employees.

### What does it check for?

At the moment, the script will check for:

* any stray `dd()` functions
* remaining git conflicts (by looking for `<<<<<<<`)
* any syntax errors (by using `php -l`)

### And.. Tests

The script will also ask you if you'd like to run your tests, with a Y/n option, which will run your PHPUnit tests for you. If any of the tests fail the commit will be aborted.
