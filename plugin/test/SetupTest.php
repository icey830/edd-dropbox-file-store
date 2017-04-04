<?php
require_once __DIR__ . '\TestUtility.php';

class SetupTest extends PHPUnit_Extensions_Selenium2TestCase {
    /**
     * @var TestUtility
     */
    private $testUtility = null;

    protected function setUp() {
        $this->testUtility = new TestUtility($this);

        $this->setBrowser('chrome');
        $this->setBrowserUrl('http://local.wordpress.dev/');
        $this->prepareSession()->currentWindow()->maximize();
    }

    public function testActivateDeactivate() {
        $this->testUtility->loginToAdminSite();
        $this->byCssSelector('a[href="plugins.php"]')->click();
        $this->testUtility->waitForPageLoad();

        $pluginBlock = $this->byCssSelector('tr[data-slug="easy-digital-downloads-dropbox-file-store"]');
        $this->assertNotNull($pluginBlock, 'Could not find EDD Dropbox File Store plugin on Plugin page');

        // De-activate the plugin
        $deactivateLink = $pluginBlock->byLinkText('Deactivate');
        $deactivateLink->click();
        $this->testUtility->waitForPageLoad();
        $message = $this->byCssSelector('#message p');
        $this->assertEquals('Plugin deactivated.', $message->text(), 'Plugin did not deactivate correctly');

        // Activate the plugin and make sure we get a success message
        $pluginBlock = $this->byCssSelector('tr[data-slug="easy-digital-downloads-dropbox-file-store"]');
        $this->assertNotNull($pluginBlock, 'Could not find EDD Dropbox File Store plugin on Plugin page');

        $activateLink = $pluginBlock->byLinkText('Activate');
        $activateLink->click();
        $this->testUtility->waitForPageLoad();
        $message = $this->byCssSelector('#message p');
        $this->assertEquals('Plugin activated.', $message->text(), 'Plugin did not activate correctly');
    }

    public function testRegistrationWithDropbox() {
        // Navigate to the extension settings
        $this->testUtility->loginToAdminSite();
        $this->byCssSelector('a.menu-icon-download')->click();
        $this->testUtility->waitForPageLoad();
        $this->byCssSelector('a[href="edit.php?post_type=download&page=edd-settings"]')->click();
        $this->testUtility->waitForPageLoad();
        $this->byCssSelector('a[href$="tab=extensions"]')->click();
        $this->testUtility->waitForPageLoad();

        // Look for the title text and Remove Authorization button
        $removeAuthButton = $this->byCssSelector('#edd-dbfs-deauth');
        $this->assertNotNull($removeAuthButton, 'Could not find "Remove Authorization" button');

        // Remove the authorization and confirm the Get Code button and instruction text is displayed
        $removeAuthButton->click();
        $this->testUtility->waitForPageLoad('#edd_dbfs_getCode_link');
        $installInstructions = $this->byCssSelector('#edd-dbfs-install-instructions');
        $this->assertEquals("To authorize your account:", $installInstructions->text(), 'Could not find authorization instructions');
        $getCodeButton = $this->byId('edd_dbfs_getCode_link');

        // Click Get Code to open the Dropbox tab
        $getCodeButton->click();

        $testRunner = $this;
        $this->waitUntil(function () use($testRunner) {
            $windowHandles = $testRunner->windowHandles();
            $testRunner->assertEquals(2, count($windowHandles), 'Incorrect # of windows open');
            return true;
        }, 5000);

        // Switch to the new tab
        $eddHandle = $this->windowHandle();
        $handle = $this->windowHandles()[1];
        $this->window($handle);

        // Need to log in
        $this->testUtility->waitForPageLoad('.login-button');
        sleep(1);
        $email = $this->testUtility->getDisplayedElementByName('login_email');
        $email->click();
        $email->value('adam.kreiss+test@gmail.com');
        $password = $this->testUtility->getDisplayedElementByName('login_password');
        $password->value('iWw8uhdWokXTxqbtfdEL');
        $loginButton = $this->byCssSelector('.login-button');
        $loginButton->click();

        // Verify the instructions look good and that we can copy out the key
        $this->testUtility->waitForPageLoad('#auth-text');
        $this->assertEquals('API Request Authorization - Dropbox', $this->title(), 'Incorrect title for Dropbox Auth window');
        $this->assertEquals('Easy Digital Downloads - File Store would like access to the files and folders in your Dropbox. Learn more',
            $this->byId('auth-text')->text(), 'Incorrect permission ask on Dropbox Auth window');
        sleep(1);
        $this->byName('allow_access')->click();
        $this->testUtility->waitForPageLoad('#auth-code');

        $this->assertStringStartsWith('Enter this code into Easy Digital Downloads - File Store to finish the process.',
            $this->byId('auth-text')->text(), 'Incorrect copy instructions on Dropbox Auth window');
        $authKey = $this->byCssSelector('.auth-box')->value();
        $this->closeWindow();
        $this->window($eddHandle);

        // Copy the auth key into the box in EDD and verify we can save successfully
        $registerButton = $this->testUtility->tryByCssSelector('#edd_dbfs_register_link');
        $authCodeTextBox = $this->testUtility->tryByCssSelector('#edd_dbfs_auth_code');
        $this->assertNotNull($registerButton, 'Could not find Register Code button');
        $this->assertNotNull($authCodeTextBox, 'Could not find Auth Code text box');
        $authCodeTextBox->value($authKey);
        $registerButton->click();
        $this->testUtility->waitForPageLoad('#edd-dbfs-deauth');

        $removeAuthButton = $this->testUtility->tryByCssSelector('#edd-dbfs-deauth');
        $this->assertNotNull($removeAuthButton, 'Could not find "Remove Authorization" button');
    }
}


?>