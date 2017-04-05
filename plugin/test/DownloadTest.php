<?php
require_once __DIR__ . '\TestUtility.php';

class DownloadTest extends PHPUnit_Extensions_Selenium2TestCase {
    /**
     * @var TestUtility
     */
    private $testUtility = null;

    protected function setUp() {
        $this->testUtility = new TestUtility($this);

        $this->setBrowser('chrome');
        $this->setupSpecificBrowser(array('-incognito'));
        $this->setBrowserUrl('http://local.wordpress.dev/');
        $this->prepareSession()->currentWindow()->maximize();
    }

    public function testSelectDownloadFromDropboxLibrary() {
        // Add the download to a cart
        $this->url('http://local.wordpress.dev/downloads/test-download/');
        $this->byCssSelector('a.edd-add-to-cart')->click();
        sleep(1);
        $this->testUtility->getDisplayedElementByCssSelector('a.edd_go_to_checkout')->click();
        $this->testUtility->waitForPageLoad('#edd_checkout_cart');

        // Fill in required info and submit purchase
        $this->byName('edd_email')->clear();
        $this->byName('edd_email')->value('adam.kreiss+test@gmail.com');
        $this->byName('edd_first')->value('Adam');
        $this->byName('edd_last')->value('Kreiss');
        $this->byId('edd-purchase-button')->click();
        $this->testUtility->waitForPageLoad('#edd_purchase_receipt');

        // Select the Dropbox Library link
        $this->byLinkText('file1.txt')->click();
        sleep(1);

        // Make sure we've loaded the file correctly
        $fileURL = $this->url();
        $this->assertEquals('http://local.wordpress.dev/checkout/purchase-confirmation/', $fileURL, "File link (Force Download) shouldn't have changed the URL");
    }
}