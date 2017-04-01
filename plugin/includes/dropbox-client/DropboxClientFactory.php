<?php
namespace cpm\edd\dbfs;

/**
 * A factory to retrieve implementations of the IEDDDropboxClient
 */
class DropboxClientFactory
{
    private static $v2Prefix = "v2|";

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

    public static function getDBFSClient($authToken) {
        $key = convert_uudecode(DropboxClientFactory::$db_1);
        $secret = convert_uudecode(DropboxClientFactory::$db_2);

        // Use the V2 API
        //if ($authToken == null || DropboxClientFactory::isV2AuthorizationToken($authToken)) {
            return new DropboxV2Client(DropboxClientFactory::$clientIdentifier, $key, $secret, $authToken);
        //}
        //else {
        //    return new DropboxV1Client(DropboxClientFactory::$clientIdentifier, $key, $secret, $authToken);
        //}
    }

    /**
     * Determines whether or not the authorization token is a V1 or V2 token
     *
     * @param $authToken string The authorization token
     * @return bool Whether or not the authorization token is a V2 token
     */
    public static function isV2AuthorizationToken($authToken) {
        return $authToken != null && substr($authToken, 0, strlen(DropboxClientFactory::$v2Prefix)) === DropboxClientFactory::$v2Prefix;
    }
}