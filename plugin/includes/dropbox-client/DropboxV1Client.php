<?php
namespace cpm\edd\dbfs;

use \Dropbox;
use Dropbox\AppInfo;
use Dropbox\Client;
use Dropbox\WebAuthNoRedirect;
use Dropbox\WriteMode;

/***
 * Implementation of a client for the v1 Dropbox API.
 *
 * @deprecated The Dropbox V1 API has been deprecated
 * @see https://www.dropbox.com/developers-v1/core/docs
 *
 * @since 1.9.0
 */
class DropboxV1Client implements IEDDDropboxClient
{
    /**
     * @var null|string
     */
    private $authToken  = null;

    /**
     * @var Client
     */
    private $client = null;

    /**
     * @var string
     */
    private $clientId = null;

    /**
     * @var string
     */
    private $key = null;

    /**
     * @var string
     */
    private $secret = null;

    /**
     * @var WebAuthNoRedirect
     */
    private $webAuth = null;

    /**
     * DropboxV1Client constructor.
     *
     * @param $clientIdentifier string The app identifier for EDD DBFS
     * @param $key string The private key to authorize the EDD DBFS app
     * @param $secret string The private secret to authorize the EDD DBFS app
     * @param $authToken string An existing authorization token
     */
    public function __construct($clientIdentifier, $key, $secret, $authToken)
    {
        // Load Dropbox API if not already loaded
        if (!class_exists('Dropbox\\Client')) {
            require_once plugin_dir_path(__FILE__) . '../../dropbox-v1-sdk/Dropbox/autoload.php';
        }

        $this->authToken    = $authToken;
        $this->clientId     = $clientIdentifier;
        $this->key          = $key;
        $this->secret       = $secret;
    }

    /**
     * Begin an authorization request with the user.
     *
     * @return string The URL to display the authorization request at
     */
    public function beginAuthorization()
    {
        return $this->getAuthorizationClient()->start();
    }

    /**
     * Complete an authorization request.
     *
     * @param $authorizationCode string The code provided by the user to authorize with
     * @return array The authorization token and user ID
     */
    public function finishAuthorization($authorizationCode)
    {
        return $this->getAuthorizationClient()->finish($authorizationCode);
    }

    /**
     * Retrieves metadata (including child info) for a specific folder
     *
     * @since 1.9.0
     *
     * @param $folderPath string The path to retrieve data on
     * @return array Metadata for the folder
     */
    public function getFolderMetadata($folderPath)
    {
        $response = $this->getClient()->getMetadataWithChildren($folderPath);
        return $response['contents'];
    }

    /**
     * Creates a direct file link with an expiration.
     *
     * @param $filePath string The path of the file to create the link for
     * @return array The URL and expiration time
     * @throws \Dropbox\Exception_BadResponseCode
     * @throws \Dropbox\Exception_InvalidAccessToken
     * @throws \Dropbox\Exception_RetryLater
     * @throws \Dropbox\Exception_ServerError
     */
    public function getTemporaryLink($filePath)
    {
        list($url, $expires) = $this->getClient()->createTemporaryDirectLink($filePath);
        return $url;
    }

    /**
     * Upload a file to Dropbox
     *
     * @param $filename string The name of the file
     * @param $fileStream resource The file to upload
     * @param $fileSize int The size of the file being uploaded
     * @return mixed If successful an array containing the path to the file.  If failure then empty
     */
    public function uploadFile($filename, $fileStream, $fileSize)
    {
        return $this->getClient()->uploadFile($filename, WriteMode::add(), $fileStream);
    }

    private function getClient() {
        if ($this->client == null) {
            if ($this->authToken == null)
            {
                throw new \Exception('Could not create client because no authorization token exists');
            }

            $this->client = new Client($this->authToken, $this->clientId);
        }

        return $this->client;
    }

    private function getAuthorizationClient() {
        if ($this->webAuth == null) {
            $appInfo = new AppInfo($this->key, $this->secret);
            $this->webAuth = new WebAuthNoRedirect($appInfo, $this->clientId);
        }

        return $this->webAuth;
    }
}