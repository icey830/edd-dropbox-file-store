<?php
namespace cpm\edd\dbfs;

use Alorel\Dropbox\Operation\AbstractOperation;
use Alorel\Dropbox\Operation\Files\GetTemporaryLink;
use Alorel\Dropbox\Operation\Files\ListFolder\ListFolder;
use Alorel\Dropbox\Operation\Files\ListFolder\ListFolderContinue;
use Alorel\Dropbox\Operation\Files\UploadSession\Append;
use Alorel\Dropbox\Operation\Files\UploadSession\Finish;
use Alorel\Dropbox\Operation\Files\UploadSession\Start;
use Alorel\Dropbox\Options\Builder\ListFolderOptions;
use Alorel\Dropbox\Parameters\CommitInfo;
use Alorel\Dropbox\Parameters\UploadSessionCursor;
use GuzzleHttp\Client;

class DropboxV2Client implements IEDDDropboxClient
{
    /**
     * @var string
     */
    private $URL_Authorization  = "https://www.dropbox.com";

    private $URL_API            = "https://api.dropboxapi.com";

    /**
     * @var null|string
     */
    private $authToken  = null;

    /**
     * @var string
     */
    private $clientId = null;

    /**
     * @var \GuzzleHttp\Client
     */
    private static $guzzleClient = null;

    /**
     * @var string
     */
    private $key = null;

    /**
     * @var string
     */
    private $secret = null;

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
        if (!class_exists('Alorel\\Dropbox\\Util')) {
            /** @noinspection PhpIncludeInspection */
            require_once EDD_DBFS_PLUGIN_DIR . '/vendor/autoload.php';
        }

        $this->authToken    = $authToken;
        $this->clientId     = $clientIdentifier;
        $this->key          = $key;
        $this->secret       = $secret;

        $this->setupClient();
        if (!self::$guzzleClient) {
            self::$guzzleClient = new Client();
        }
    }

    /**
     * Begin an authorization request with the user.
     *
     * @return string The URL to display the authorization request at
     */
    public function beginAuthorization()
    {
        return $this->URL_Authorization . '/oauth2/authorize?response_type=code&client_id=' . $this->key;
    }

    /**
     * Complete an authorization request.
     *
     * @param $authorizationCode string The code provided by the user to authorize with
     * @return array The authorization token and user ID
     * @throws \Exception
     */
    public function finishAuthorization($authorizationCode)
    {
        $response = self::$guzzleClient->request(
            'POST',
            $this->URL_API . '/oauth2/token',
            [
                'form_params' => [
                    'code' => $authorizationCode,
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->key,
                    'client_secret' => $this->secret
                ]
            ]
        );

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($statusCode !== 200) {
            throw new \Exception("HTTP status $statusCode\n$body");
        }

        $parts = json_decode($body, true, 10);
        if ($parts == null) {
            throw new \Exception("Invalid response message");
        }

        if (!array_key_exists('token_type', $parts) || !is_string($parts['token_type'])) {
            throw new \Exception("Missing \"token_type\" field.");
        }
        $tokenType = $parts['token_type'];

        if (!array_key_exists('access_token', $parts) || !is_string($parts['access_token'])) {
            throw new \Exception("Missing \"access_token\" field.");
        }
        $accessToken = $parts['access_token'];

        if (!array_key_exists('uid', $parts) || !is_string($parts['uid'])) {
            throw new \Exception("Missing \"uid\" string field.");
        }
        $userId = $parts['uid'];

        if ($tokenType !== "Bearer" && $tokenType !== "bearer") {
            throw new \Exception("Unknown \"token_type\"; expecting \"Bearer\", got  " . $this->prettify($tokenType));
        }

        return array($accessToken, $userId);
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
        $this->validateIsAuthorized();

        if ($folderPath == "/" || $folderPath == null) {
            $folderPath = "";
        }

        $operation = new ListFolder();
        $options = new ListFolderOptions();

        // Loop to get all files 2k at a time
        $response = null;
        $results = array();
        do {
            if ($response == null) {
                $response = json_decode($operation->raw($folderPath, $options)->getBody()->getContents(), true);
            }
            else {
                $response = json_decode($operation->raw($response['cursor'])->getBody()->getContents(), true);
            }

            foreach ($response['entries'] as $i => $entry) {
                $results[] = array(
                    'is_dir' => $entry['.tag'] == 'folder',
                    'path' => $entry['path_display']
                );
            }
            $operation = new ListFolderContinue();
        } while ($response['has_more']);

        return $results;
    }

    /**
     * Creates a direct file link with an expiration.
     *
     * @param $filePath string The path of the file to create the link for
     * @return array The URL and expiration time
     * @throws \Exception
     */
    public function getTemporaryLink($filePath)
    {
        $this->validateIsAuthorized();

        $response = json_decode((new GetTemporaryLink())->raw($filePath)->getBody()->getContents(), true);

        $url = self::getField($response, "link");
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
        $this->validateIsAuthorized();

        $buffer = 1024 * 1024 * 10; //Send 10MB at a time - increase or lower this based on your setup

        $fh = \GuzzleHttp\Psr7\stream_for($fileStream);

        $append = new Append();
        $sessionID = json_decode((new Start())->raw()->getBody()->getContents(), true)['session_id']; //Get a session ID
        $cursor = new UploadSessionCursor($sessionID); //Create a cursor from the session ID
        $offset = 0;
        $finished = false;

        // Keep appending until we're at the last segment
        $response = null;
        while (!$finished) {
            $cursor->setOffset($offset);
            $data = \GuzzleHttp\Psr7\stream_for($fh->read($buffer));
            $offset += $buffer;

            if ($data->getSize() == $buffer || $offset < $fileSize) {
                // Haven't scanned the entire file
                $append->raw($data, $cursor);
            } else {
                //Send the last segment
                $finished = true;
                $commit = new CommitInfo($filename);
                $response = json_decode((new Finish())->raw($data,$cursor,$commit)->getBody()->getContents(), true);
            }
        }
        return array(
            'path' => $this->getField($response, "path_display")
        );
    }

    /**
     * Safely gets a field from the array representing a JSON response.
     * @param $response array An array containing the key-value pairs in a JSON object
     * @param $fieldName string The name of the field to get
     * @return string | array The value of the field
     * @throws \Exception Thrown if there is no record for $fieldName in the $response
     */
    private function getField($response, $fieldName)
    {
        if (!array_key_exists($fieldName, $response))
        {
            throw new \Exception("Missing field \"$fieldName\" in " . $this->prettify($response));
        }
        return $response[$fieldName];
    }

    /**
     * Convert a response object into debug text
     *
     * @param $response array An array representing the JSON response
     * @return string
     */
    private function prettify($response) {
        return var_export($response, true);
    }

    /**
     * Configures credentials and default options for communicating with Dropbox
     */
    private function setupClient() {
        AbstractOperation::setDefaultAsync(false);
        AbstractOperation::setDefaultToken($this->authToken);
    }

    /**
     * Tests that the client is configured properly to make requests to Dropbox that require authorization
     * @throws \Exception If the client is not authorized
     */
    private function validateIsAuthorized() {
        if ($this->authToken == null) {
            throw new \Exception("Authorization with Dropbox has not been completed");
        }
    }
}