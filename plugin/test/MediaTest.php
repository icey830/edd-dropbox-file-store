<?php
require_once __DIR__ . '\TestUtility.php';

class MediaTest extends PHPUnit_Extensions_Selenium2TestCase {
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

    public function testSelectDownloadFromDropboxLibrary() {
        // Navigate to the extension settings
        $this->testUtility->loginToAdminSite();
        $this->byCssSelector('a.menu-icon-download')->click();
        $this->testUtility->waitForPageLoad();
        $this->byCssSelector('a[href$="post-new.php?post_type=download"]')->click();
        $this->testUtility->waitForPageLoad();

        // Click the Upload a File link to load the Media modal dialog
        $this->byCssSelector('.edd_upload_file_button')->click();
        $this->testUtility->waitForPageLoad('.media-modal-content');

        // Select the Dropbox Library link
        $this->byLinkText('Dropbox Library')->click();

        // Need to look in the iframe for the media items
        $currentWindowHandle = $this->windowHandle();
        $this->testUtility->waitForPageLoad('iframe[src$="tab=dropbox_lib"]');
        $mediaFrame = $this->byCssSelector('iframe[src$="tab=dropbox_lib"]');
        $this->frame($mediaFrame);

        // Verify we see file1.txt controls
        $this->byXPath('//a[@data-dbfs-filename=\'file1.txt\']');
        $this->byXPath('//span[contains(.,\'file1.txt\')]');

        // Navigate in and out of the folder containing special characters
        $this->byXPath('//span[contains(.,\'/-+-subfolderWithSymbol\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'/testSub\')]');
        $this->byXPath('//span[contains(.,\'../\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'/subfolder\')]');

        // Navigate to the sub folder and select file2.txt
        $this->byXPath('//span[contains(.,\'/subfolder\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'file2.txt\')]');
        $this->byXPath('//a[@data-dbfs-filename=\'file2.txt\']')->click();

        // Switch back to the main page frame
        $this->window($currentWindowHandle);

        // Verify the File Name and File URL are correct
        $fileNameTextBox = $this->byName('edd_download_files[1][name]');
        $fileUrlTextBox = $this->byName('edd_download_files[1][file]');
        $this->assertEquals('file2.txt', $fileNameTextBox->value(), 'Incorrect value for File Name text box');
        $this->assertEquals('edd-dbfs://subfolder/file2.txt', $fileUrlTextBox->value(), 'Incorrect value for File Url text box');
    }

    public function testUploadFileToDropbox() {
        // Navigate to the extension settings
        $this->testUtility->loginToAdminSite();
        $this->byCssSelector('a.menu-icon-download')->click();
        $this->testUtility->waitForPageLoad();
        $this->byCssSelector('a[href$="post-new.php?post_type=download"]')->click();
        $this->testUtility->waitForPageLoad();

        // Click the Upload a File link to load the Media modal dialog
        $this->byCssSelector('.edd_upload_file_button')->click();
        $this->testUtility->waitForPageLoad('.media-modal-content');

        // Select the Dropbox Library link
        $this->byLinkText('Upload to Dropbox')->click();

        // Need to look in the iframe for the media items
        $this->testUtility->waitForPageLoad('iframe[src$="tab=dropbox_upload"]');
        $mediaFrame = $this->byCssSelector('iframe[src$="tab=dropbox_upload"]');
        $this->frame($mediaFrame);

        // Navigate in and out of the folder containing special characters
        $this->byXPath('//span[contains(.,\'/-+-subfolderWithSymbol\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'/testSub\')]');
        $this->byXPath('//span[contains(.,\'../\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'/subfolder\')]');

        // Navigate to the upload folder and upload a test file
        $this->byXPath('//span[contains(.,\'/upload\')]')->click();
        $this->testUtility->waitForPageLoadByXpath('//span[contains(.,\'Current directory: /upload\')]');

        // TODO Add the rest of this test in once a text box exists that can be populated with the file name
//        $this->execute(array(
//           'script' => "jQuery('#edd_dbfs_file').val('C:\\temp\\file1.txt');",
//            'args'  => array()
//        ));
//
//        this->byName('edd_dbfs_file')->click();
//        sleep(.5);
//        $this->keys('C:\\temp\file1.txt' . Key::ENTER);
//        $this->testUtility->waitForPageLoadByXpath('//input[@value=\'file1.txt \']');
//        $this->byXPath('//input[@value=\'Upload\']')->click();
//
//        // Determine the actual file name and select to use it
//        $successMessage = $this->byXPath('//p[contains(.,\'File uploaded successfully: /upload/\')]')->text();
//        $fileName = 'file1 ().txt';
//        $this->byId('edd_dbfs_save_link')->click();
//
//        // Switch back to the main page frame
//        $this->window($currentWindowHandle);
//
//        // Verify the File Name and File URL are correct
//        $fileNameTextBox = $this->byName('edd_download_files[1][name]');
//        $fileUrlTextBox = $this->byName('edd_download_files[1][file]');
//        $this->assertEquals($fileName, $fileNameTextBox->value(), 'Incorrect value for File Name text box');
//        $this->assertEquals("edd-dbfs://subfolder/$fileName", $fileUrlTextBox->value(), 'Incorrect value for File Url text box');
    }
}