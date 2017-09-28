<?php
namespace cpm\edd\dbfs;

/**
 * A factory to retrieve implementations of the IEDDDropboxClient
 */
class DropboxClientFactory
{
    private static $clientIdentifier = 'edd-dbshare/1.0';

    // NOTE TO DEVELOPERS / USERS
    // The values below are the encrypted versions of the Dropbox app key used for this add-on.  Please
    // do not use these values for other apps.  If you would like to use your own key for this plug-in
    // please contact the EDD support team on the EDD forums for details on how to do so.
    private static $db_1 = '/;C%F:VAW:&,Q,S4W>78Y\n\'\n';
    private static $db_2 = '/9V8P-S%S,3=Y8F]H,VQN\n\'\n';

    public static function autoloader() {
        require_once 'IEDDDropboxClient.php';
        require_once 'DropboxV1Client.php';
        require_once 'DropboxV2Client.php';
    }

    public static function getDBFSClient($authToken)
    {
        $key = convert_uudecode(DropboxClientFactory::$db_1);
        $secret = convert_uudecode(DropboxClientFactory::$db_2);

        $useAPIV1 = false;
        if (defined('EDD_DBFS_USE_API_V1')) {
            /** @noinspection PhpUndefinedConstantInspection */
            if (EDD_DBFS_USE_API_V1) {
                $useAPIV1 = true;
            }
        }

        // Use the V2 API
        if ($useAPIV1) {
            return new DropboxV1Client(DropboxClientFactory::$clientIdentifier, $key, $secret, $authToken);
        } else {
            return new DropboxV2Client(DropboxClientFactory::$clientIdentifier, $key, $secret, $authToken);
        }
    }
}