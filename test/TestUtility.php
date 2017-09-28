<?php

class TestUtility
{
    private $testRunner = null;

    /**
     * TestUtility constructor.
     * @param $testRunner PHPUnit_Extensions_Selenium2TestCase The test utility
     */
    public function __construct($testRunner)
    {
        $this->testRunner = $testRunner;
    }

    /***
     * @param $selector string The css selector of the element to find
     * @return \PHPUnit_Extensions_Selenium2TestCase_Element The visible element with the matching css selector
     */
    public function getDisplayedElementByCssSelector($selector)
    {
        $elements = $this->testRunner->elements($this->testRunner->using('css selector')->value($selector));
        foreach ($elements as $element)
        {
            if ($element->displayed())
            {
                return $element;
            }
        }
        $this->testRunner->fail('There is no visible elements for selector ' . $selector);
    }

    /***
     * @param $name string The name of the element to find
     * @return \PHPUnit_Extensions_Selenium2TestCase_Element The visible element with the matching name
     */
    public function getDisplayedElementByName($name)
    {
        return $this->getDisplayedElementByCssSelector('[name="' . $name . '"]');
    }

    public function loginToAdminSite() {
        $this->testRunner->url('http://local.wordpress.dev/wp-admin');
        $this->waitForPageLoad('#user_login');
        sleep(.5);

        // Fill in user credentials
        $this->testRunner->byId('user_login')->click();
        sleep(1);
        $this->testRunner->byId('user_login')->value('admin');
        $this->testRunner->byId('user_pass')->click();
        sleep(.5);
        $this->testRunner->byId('user_pass')->value('password');
        $this->testRunner->byId('wp-submit')->click();
        $this->waitForPageLoad();
    }

    public function tryByCssSelector($selector) {
        try {
            return $this->testRunner->byCssSelector($selector);
        }
        catch (PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) { /*Ignore*/ }

        return null;
    }

    public function waitForPageLoad($selector = '.wp-admin', $timeout = 50000) {
        sleep(.5);
        $testRunner = $this->testRunner;
        $testRunner->waitUntil(
            function() use ($testRunner, $selector, $timeout) {
                if ($testRunner->byCssSelector($selector)) {
                    return true;
                }
                return null;
            },
            $timeout
        );
    }
    public function waitForPageLoadByXpath($xpath, $timeout = 5000) {
        //sleep(500);
        $testRunner = $this->testRunner;
        $testRunner->waitUntil(
            function() use ($testRunner, $xpath, $timeout) {
                if ($testRunner->byXPath($xpath)) {
                    return true;
                }
                return null;
            },
            $timeout
        );
    }
}