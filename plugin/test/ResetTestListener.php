<?php
use PHPUnit\Framework\TestCase;

class ResetTestListener implements PHPUnit_Framework_TestListener
{
    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) { }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) { }

    public function startTest(PHPUnit_Framework_Test $test) { }

    public function endTest(PHPUnit_Framework_Test $test, $time) { }

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) {
        if ($suite->getName() == "Standard Test Suite") {
            echo "Restoring database";
            system("vagrant ssh 95b681d -c \"/usr/local/bin/wp db import /srv/www/wordpress-default/public_html/edd-dbfs-db.sql --path=/srv/www/wordpress-default/public_html\"");
        }
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) { }
}