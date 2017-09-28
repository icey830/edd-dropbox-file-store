<?php
namespace cpm\edd\dbfs;

/***
 * Defines an interface for differing versions of clients to the Dropbox SDK.
 */
interface IEDDDropboxClient
{
    public function beginAuthorization();
    public function finishAuthorization($authorizationCode);
    public function getFolderMetadata($folderPath);
    public function getTemporaryLink($filePath);
    public function uploadFile($filename, $fileStream, $fileSize);
}