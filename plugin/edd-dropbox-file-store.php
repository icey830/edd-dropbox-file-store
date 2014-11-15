<?php
/*
Plugin Name: Easy Digital Downloads - Dropbox File Store
Plugin URL: http://easydigitaldownloads.com/extension/dropbox_file_store
Description: Adds support for storing and sharing your digital goods via Dropbox.
Version: 1.5
Author: Adam Kreiss
Author URI: N/A
*/

// Instantiate the licensing / updater. Must be placed in the main plugin file
if(class_exists('EDD_License') && is_admin() ) {
    $license = new EDD_License( __FILE__, 'EDD Dropbox File Store', '1.5', 'AlphaKilo Development Services' );
}

// Load Dropbox API
require_once 'dropbox-sdk/Dropbox/autoload.php';
use \Dropbox as dbx;

class EDDDropboxFileStore {
    
    private $_debug = false;
    
    private $_hook = 'edd-dbfs';
    private $clientIdentifier = 'edd-dbshare/1.0'; 
    
    // NOTE TO DEVELOPERS / USERS
    // The values below are the encrypted versions of the Dropbox app key used for this add-on.  Please
    // do not use these values for other apps.  If you would like to use your own key for this plug-in
    // please contact the EDD support team on the EDD forums for details on how to do so.
    private $db_1 = '/;C%F:VAW:&,Q,S4W>78Y\n\'\n';
    private $db_2 = '/9V8P-S%S,3=Y8F]H,VQN\n\'\n';
    
    private $KEY_ACCESS_TOKEN = 'edd_dbfs_authToken';
    private $KEY_FORCE_DL   = 'edd_force_dl';
    private $PATH_ROOT = '/';
    private $URL_PREFIX = 'edd-dbfs://';
    
    private $POSTTYPE_DOWNLOAD = 'download';
    
    /*
     * Constructor for class.  Performs setup / integration with Wordpress
     */
    public function __construct() {   
        // Load the default language files
        add_action('init', array($this, 'dbfsInit'));
        
        // Settings / Authorization hooks
        add_filter('edd_settings_extensions', array($this, 'addSettings'));
        
        add_action('edd_dbfs_authorization', array($this, 'registerAuthorization'));
        add_action('template_redirect', array( $this, 'handleAuthActions' ));
        
        // Media hooks
        add_filter( 'media_upload_tabs', array( $this, 'addDropboxTabs' ) );
        add_filter( 'edd_requested_file', array( $this, 'generateUrl' ), 11, 3 );
        add_filter( 'edd_dbfs_upload'  , array( $this, 'performFileUpload' ), 10, 2 );
        
		add_action( 'admin_head', array( $this, 'setupAdminJS' ) );
        add_action( 'media_upload_dropbox_lib' , array( $this, 'registerDBLibTab' ) );
        add_action( 'media_upload_dropbox_upload' , array( $this, 'registerDBUploadTab' ) );
    }
    
    public function dbfsInit() {
        $edd_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
        load_plugin_textdomain( 'edd_dbfs', false, $edd_lang_dir );
    }
    
     /*
	 * Activation function fires when the plugin is activated.
	 *
	 * This function is fired when the activation hook is called by WordPress,
	 * 
	 */
	public static function activation() {
        // Check for required PHP version
        if (version_compare(PHP_VERSION, '5.3', '<'))
        {
            deactivate_plugins( basename( __FILE__ ) );
            wp_die('<p>The <strong>Easy Digital Downloads - Dropbox File Store</strong> plugin requires PHP 5.3 or greater.  You are currently running PHP ' 
                    . PHP_VERSION . '</p>','Plugin Activation Error',  array( 'response'=>200, 'back_link'=>TRUE ) );
        }
    }
    
    public function setupAdminJS() {
		?>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(function($){
				$('body').on('click', '.edd_upload_file_button', function(e) {
					window.edd_fileurl = $(this).parent().prev().find('input');
					window.edd_filename = $(this).parent().parent().parent().prev().find('input');
				});
			});
			//]]>
		</script>
		<?php
	}
    
    /***************************************************************************
     * EDD DBShare Media Download Integration
     **************************************************************************/
    
    public function addDropboxTabs($default_tabs) {
        global $edd_options;
        
        if (array_key_exists($this->KEY_ACCESS_TOKEN, $edd_options) && $edd_options[$this->KEY_ACCESS_TOKEN] != null) {
            $post_type = get_post_type(get_the_ID());
            if ($post_type == $this->POSTTYPE_DOWNLOAD) {            
                $default_tabs['dropbox_upload'] = __( 'Upload to Dropbox', 'edd_dbfs' );
                $default_tabs['dropbox_lib'] = __( 'Dropbox Library', 'edd_dbfs' );
            }
        }
        return $default_tabs; 
    }
    
    public function registerDBLibTab() {
        if (!empty($_POST)) {
            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }

        wp_iframe(array($this, 'renderDBFilesTab'));
    }
    
    public function renderDBFilesTab( $type = 'file', $errors = null, $id = null ) {
        media_upload_header();
        wp_enqueue_style('media');

        $path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
        if (!isset($path)) {
            $path = $this->PATH_ROOT;
        }

        $metadata = $this->getPathMetadata($path);
        $files = $metadata['contents'];
?>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(function($){
            $('.save-db-file').click(function() {
                $(parent.window.edd_filename).val($(this).data('dbfs-filename'));
                $(parent.window.edd_fileurl).val('<?php echo $this->URL_PREFIX ?>' + $(this).data('dbfs-link'));
                parent.window.tb_remove();
            });
        });
        //]]>
    </script>
    <div style="background-color: #fff; margin-top: -21px; padding-left: 20px; width: inherit;" id="media-items">
        <h3 class="media-title" style="padding-top: 21px;"><?php __('Select a file from Dropbox', 'edd_dbfs'); ?></h3>
<?php
        if( is_array( $files ) ) {
?>
        <ul>
<?php
            $baseURL = admin_url( 'media-upload.php?chromeless=1&post_id=' . absint(filter_input(INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT)) . '&tab=dropbox_lib' );            
            if ($path != $this->PATH_ROOT) {
                $lastSlashPos = strrpos($metadata['path'], '/', -1);
                
                $folderURL = '/';
                if ($lastSlashPos > 0) {
                    $upFolder = substr($metadata['path'], 0, $lastSlashPos);    
                    $folderURL = add_query_arg(array('path' => $upFolder), $baseURL);
                }
                else {
                    $folderURL = add_query_arg(array('path' => '/'), $baseURL);
                }
                
                $this->outputFolderLI('../', $folderURL);
            }

            // Folder loop
            foreach($files as $file) {
                if($file['is_dir'] != "1" ) {
                    continue; // Ignore non-folders
                }
                $filePath = $this->getFilenameFromDBPath($file['path'], true);
                
                $fileURL = add_query_arg(array('path' => $file['path']), $baseURL);
                $this->outputFolderLI($filePath, $fileURL);
            } 
           
            // File loop
            foreach($files as $file) {
                if($file['is_dir'] == "1" ) {
                    continue; // Ignore folders this time
                }
                
                $filePath = $this->getFilenameFromDBPath($file['path'], false);
                $this->outputContentLI($file['path'], $filePath, null, false);
            }
?>      
        </ul>
<?php
        }
?>
    </div>
<?php
    }
    
    private function getPathMetadata($path) {
        $dbClient = $this->getClient();
        if ($dbClient == null) {
            $this->debug('Dropbox client was not created, dropping out');
            return null;
        }

        $folderMetadata = $dbClient->getMetadataWithChildren($path);
        if ($folderMetadata == null) {
            return null;
        }

        return $folderMetadata;
    }
        
    private function getFilenameFromDBPath($path, $isDir) {
        $slashPos = strrpos($path, '/', -1);
        if ($slashPos === false) {
            return $path;
        }

        return substr($path, $slashPos + ($isDir ? 0 : 1));
    }
     
    private function outputFolderLI($filename, $url) {
?>
        <li class="media-item">
            <a href="<?php echo $url ?>">
                <span style="line-height: 36px; margin-left: 10px;"><?php echo $filename ?></span>
            </a>
        </li>
<?php
     }
    
    private function outputContentLI($fullPath, $filename) {
?>
        <li class="media-item">
            <a class="save-db-file button-secondary" href="javascript:void(0)" style="margin:4px;" data-dbfs-filename="<?php echo $filename ?>" data-dbfs-link="<?php echo substr($fullPath, 1) ?>"><?php _e('Select', 'edd_dbfs') ?></a>
            <span style="line-height: 36px; margin-left: 10px;"><?php echo $filename ?></span>
        </li>
<?php  
     }
     
    /***************************************************************************
     * EDD DBShare Media Upload Integration
     **************************************************************************/
    
    public function registerDBUploadTab() {
        if (!empty($_POST)) {
            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }

        wp_iframe(array($this, 'renderDBUploadTab'));
    }
    
    public function renderDBUploadTab( $type = 'file', $errors = null, $id = null ) {
        wp_enqueue_style('media');

        $path = filter_input(INPUT_GET, 'path', FILTER_SANITIZE_STRING);
        if (!isset($path)) {
            $path = $this->PATH_ROOT;
        }

        $metadata = $this->getPathMetadata($path);
        $files = $metadata['contents'];
?>
    <style>
		.edd_errors { -webkit-border-radius: 2px; -moz-border-radius: 2px; border-radius: 2px; border: 1px solid #E6DB55; margin: 0 0 21px 0; background: #FFFFE0; color: #333; }
		.edd_errors p { margin: 10 15px; padding: 0 10px; }
	</style>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(function($){
            $('#edd_dbfs_save_link').click(function() {
                $(parent.window.edd_filename).val($(this).data('db-fn'));
                $(parent.window.edd_fileurl).val('<?php echo $this->URL_PREFIX ?>' + $(this).data('db-path'));
                parent.window.tb_remove();
            });
        });
        //]]>
    </script>
    <div style="background-color: #fff; margin-top: -21px; padding-left: 20px; width: inherit;" id="media-items">
        <h3 class="media-title" style="padding-top: 21px;"><?php _e('Select a folder from Dropbox to upload to:', 'edd_dbfs'); ?></h3>
        <span class="media-title" style="padding-top: 21px;"><strong><?php _e('Current directory: ', 'edd_dbfs') ?></strong><?php echo $path ?></span>
        <?php 
            $successFlag = filter_input(INPUT_GET, 'edd_dbfs_success', FILTER_UNSAFE_RAW);
            if (!empty($successFlag) && '1' == $successFlag) {
                $savedPathAndFilename = filter_input(INPUT_GET, 'edd_dbfs_filename', FILTER_UNSAFE_RAW);
                $this->debug($savedPathAndFilename);
                $lastSlashPos = strrpos($savedPathAndFilename, '/', -1);
                $savedFilename = substr($savedPathAndFilename, $lastSlashPos > 0 ? $lastSlashPos + 1 : 0);
?>
            <div class="edd_errors">
                <p class="edd_success">File uploaded successfully: /<?php echo $savedPathAndFilename ?> </p>
                <p>
                    <a href="javascript:void(0)" 
                       id="edd_dbfs_save_link" data-db-fn="<?php echo $savedFilename ?>" data-db-path="<?php echo $savedPathAndFilename ?>">Use this file in your Download</a>
                </p>
            </div>
<?php                                                                                              
            }
?>
<?php
        if( is_array( $files ) ) {
?>
        <ul>
<?php
            $baseURL = admin_url( 'media-upload.php?chromeless=1&post_id=' . absint(filter_input(INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT)) . '&tab=dropbox_upload' );            
            if ($path != $this->PATH_ROOT) {
                $lastSlashPos = strrpos($metadata['path'], '/', -1);
                
                $folderURL = '/';
                if ($lastSlashPos > 0) {
                    $upFolder = substr($metadata['path'], 0, $lastSlashPos);    
                    $folderURL = add_query_arg(array('path' => $upFolder), $baseURL);
                }
                else {
                    $folderURL = add_query_arg(array('path' => '/'), $baseURL);
                }
                
                $this->outputFolderLI('../', $folderURL);
            }

            // Folder loop
            foreach($files as $file) {
                if($file['is_dir'] != "1" ) {
                    continue; // Ignore non-folders
                }
                $filePath = $this->getFilenameFromDBPath($file['path'], true);
                
                $fileURL = add_query_arg(array('path' => $file['path']), $baseURL);
                $this->outputFolderLI($filePath, $fileURL);
            } 
?>      
        </ul>
<?php
        }
        
        $formAction = add_query_arg(array( 'edd_action' => 'dbfs_upload' ), admin_url());
?>
        <form enctype="multipart/form-data" method="post" action="<?php echo esc_attr($formAction); ?>">
            <p><input type="file" name="edd_dbfs_file" style="padding-left: 0px;"/></p>
            <p><input type="submit" class="button-secondary" value="<?php _e('Upload', 'edd_dbfs') ?>"/></p>
            <input type="hidden" name="edd_dbfs_path" value="<?php echo $path ?>" />
        </form>
    </div>
<?php
    }    
    
    public  function performFileUpload() {
        $this->debug($_POST);
        if(!is_admin()) {
			return;
		}

		$uploadCapability = apply_filters( 'edd_dbfs_upload_cap', 'edit_products' );
		if(!current_user_can($uploadCapability)) {
			wp_die(__( 'You do not have permission to upload files to Dropbox.', 'edd_dbfs' ) );
		}

		if(empty($_FILES['edd_dbfs_file'] ) || empty( $_FILES['edd_dbfs_file']['name'] ) ) {
			wp_die(__( 'Please select a file to upload.', 'edd_dbfs' ), __( 'Error', 'edd_dbfs' ), array( 'back_link' => true ));
		}

        $path = filter_input(INPUT_POST, 'edd_dbfs_path', FILTER_SANITIZE_URL);
        if (substr($path, -1) !== '/') {
            $path = $path . '/';
        }
        $filename = $path . $_FILES['edd_dbfs_file']['name'];
        $this->debug('Upload path: ' . $filename);
        
        try {
            $resultFilename = $this->uploadFile($filename, $_FILES['edd_dbfs_file']['tmp_name']);
            if($resultFilename != null) {            
                $redirectURL = add_query_arg(
                    array(
                        'edd_dbfs_success' => '1',
                        'edd_dbfs_filename' => rawurlencode(substr($resultFilename, 1))
                    ),
                    $_SERVER['HTTP_REFERER']
                );

                $this->debug('Upload redirect URL: ' . $redirectURL);
                wp_safe_redirect($redirectURL); 
                exit;
            } else {
                wp_die(__( 'An error occurred while attempting to upload your file.', 'edd_dbfs_file' ), __( 'Error', 'edd_dbfs_file' ), array( 'back_link' => true ));
            }
        } catch (Exception $e) {
            $this->debug('File upload error: ' . $e->getMessage());
            wp_die(__( 'An error occurred while attempting to upload your file.', 'edd_dbfs_file' ), __( 'Error', 'edd_dbfs_file' ), array( 'back_link' => true ));
        }
	}
    
    private function uploadFile($filename, $filepath) {
        $dbClient = $this->getClient();
       
        $inStream = @fopen($filepath, 'rb');
        
        $result = $dbClient->uploadFile($filename, dbx\WriteMode::add(), $inStream);
        $this->debug('Upload result: ' . print_r($result, true));
        
        if ($result == null || !array_key_exists('path', $result)) { return null; }
        return $result['path'];
    }
    
    /***************************************************************************
     * EDD DBShare Media Download Integration
     **************************************************************************/
     
    public function generateUrl($file, $downloadFiles, $fileKey) {        
        $fileData = $downloadFiles[$fileKey];
        $filename = $fileData['file'];

        // Check whether thsi is file we should be paying attention to
        if (strpos($filename, $this->URL_PREFIX) === false) {
            return $file;
        }

        add_filter( 'edd_file_download_method', array( $this, 'setFileDownloadMethod' ) );
        return $this->getDownloadURL($filename);
    }
    
    private function getDownloadURL($filename) {
        global $edd_options;
        
        $this->debug('Download filename: ' . $filename);
        
        // Remove the prefix
        $path = '/' . substr($filename, strlen($this->URL_PREFIX));
        
        $dbClient = $this->getClient();
        list($url, $expires) = $dbClient->createTemporaryDirectLink($path);
        $this->debug('Download URL: ' . $url);
        
        if (array_key_exists($this->KEY_FORCE_DL, $edd_options) && $edd_options[$this->KEY_FORCE_DL]) {
            $this->debug('Forcing download');
            $url = add_query_arg('dl', '1', $url);
        }
        
        add_filter('edd_file_download_method', array($this, 'setFileDownloadMethod'));
        return $url;
    }
    
    private function setFileDownloadMethod( $method ) {
        return 'redirect';
    }
     
    /***************************************************************************
     * EDD DBShare Settings
     **************************************************************************/
    
    /*
    * Adds the settings to the Add-On section
    */
    public function addSettings($settings) {
        $dbfs_settings = array(
            array(
                'id' => 'edd_dbfs_header',
                'name' => '<strong>' . __('Dropbox File Store', 'edd_dbfs') . '</strong>',
                'desc' => '',
                'type' => 'header',
                'size' => 'regular'
            ),
            array(
                'id' => 'dbfs_authorization',
                'type' => 'hook'
            ),
            array (
                'id'     => $this->KEY_FORCE_DL,
                'name'   => 'Force Download of Files',
                'desc'   => 'This will force the browser to save the download rather than opening it within the browswer (such as video or audio files)',
                'type'   => 'checkbox',
                'size'   => 'regular'
            )
       );

       return array_merge( $settings, $dbfs_settings );
   }

    public function registerAuthorization() {
        global $edd_options;
        
        // Always provide the option to auth (or reauth) credentials
        $authToken = null;
        if (array_key_exists($this->KEY_ACCESS_TOKEN, $edd_options)) {
            $authToken = $edd_options[$this->KEY_ACCESS_TOKEN];
        }
        $authorize_url = wp_nonce_url( 
            add_query_arg( 
                array( 
                   'action' => 'authorize',
                   'page' => $this->_hook
                ), 
                get_home_url() . '/'
            ), 
            'authorize' 
        );
        $authorized_url = wp_nonce_url( 
            add_query_arg( 
                array( 
                   'action' => 'authorized',
                   'page' => $this->_hook
                ), 
                get_home_url() . '/'
            ), 
            'authorized' 
        );

        ob_start();
        
        // If we don't have a token then provide the instructions / options to get it
        if ($authToken == null) {
        ?>
            <script type="text/javascript">
                //<![CDATA[
                jQuery(function($){
                    $('#edd_dbfs_register_link').click(function(e) {
                        e.preventDefault();

                        var code = $('#edd_dbfs_auth_code').val();
                        var url = $(this).attr('href');
                        document.location.href = url + '&code=' + code;
                    });
                    $('#edd_dbfs_getCode_link').click(function() {
                        $('#edd_dbfs_auth_code,#edd_dbfs_register_link').show();
                    });
                });
                //]]>
            </script>
            <label><strong><?php _e('To authorize your account', 'edd_dbfs') ?>:</strong>
                <ol>
                    <li><?php _e('Click the "Get Code" button below to get an authorization code for EDD from Dropbox.', 'edd_dbfs') ?></li>
                    <li><?php _e('Copy the code you get from Dropbox into the text box that appears to the right.', 'edd_dbfs') ?></li>
                    <li><?php _e('Click the "Register Code" button to complete the authorization process.', 'edd_dbfs') ?></li>
                </ol>
            </label>
            <a href="<?php echo esc_url( $authorize_url );?>" id="edd_dbfs_getCode_link" class="button button-large button-primary" target="edd_dbfs_auth"><?php _e('Get Code', 'edd_dbfs') ?></a>
            <a href="<?php echo esc_url( $authorized_url );?>" id="edd_dbfs_register_link" style="display:none;" class="button button-large button-primary"><?php _e('Register Code', 'edd_dbfs') ?></a>
            <input type="text" id="edd_dbfs_auth_code" class="regular-text" style="display: none; margin-left: 5px;" value="" />
        <?php
        }
        echo ob_get_clean();
        
        // If we have a token then provide the option to delete it
        if ($authToken != null) {
            $deauthorize_url = wp_nonce_url( 
                add_query_arg( 
                    array( 
                       'action' => 'deauthorize',
                       'page' => $this->_hook
                    ), 
                    get_home_url() . '/'
                ), 
                'deauthorize' 
            );
            
            ob_start();
            ?>
            <a href="<?php echo esc_url( $deauthorize_url );?>" class="button button-large button-secondary delete"><?php _e('Remove Authorization', 'edd_dbfs') ?></a>    
            <?php
            echo ob_get_clean();
        }
        
    }

    public function handleAuthActions() {
        global $edd_options;
        
        $actionParam = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
        $pageParam = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_STRING);
        if ( empty( $actionParam ) || empty( $pageParam ) 
                || $pageParam != $this->_hook ) {
            return;
        }
        
        if ( 'authorize' == $actionParam ) {
            $webAuth = $this->getWebAuth();
            
            $authorizeUrl = $webAuth->start();
            wp_redirect($authorizeUrl);
            exit;
        }
        else if ( 'authorized' == $actionParam ) {            
            $webAuth = $this->getWebAuth();
            
            // Wrap in a try catch to handle where the user declines the authorization (or other random errors)
            try {
                $authCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
                $this->debug('Auth Code: ' . $authCode);
                list($accessToken, $dropboxUserId) = $webAuth->finish($authCode);            
                $this->debug('Auth Token: ' . $accessToken . ', DropBox UserID: ' . $dropboxUserId);

                $edd_options[$this->KEY_ACCESS_TOKEN] = $accessToken;
                update_option( 'edd_settings', $edd_options );
                $this->debug('Auth token saved to settings');
            }
            catch (Exception $e) {
                $this->debug('Error occurred while authorizing account: ' . $e->getMessage());
                wp_die(__( 'An error occurred while attempting to complete the authorization process with Dropbox using the code you provided: ' . $e->getMessage(), 'edd_dbfs_file' ), __( 'Error', 'edd_dbfs_file' ), array( 'back_link' => true ));
            }
            wp_safe_redirect($this->getSettingsUrl());
            exit;
        }
        else if ('deauthorize' == $actionParam) {
            $edd_options[$this->KEY_ACCESS_TOKEN] = null;
            update_option( 'edd_settings', $edd_options );
            $this->debug('Auth token cleared');
            
            wp_safe_redirect($this->getSettingsUrl());
        }
    }
    
    /*
     * Get the URL to the EDD settings page focused on the extensions tab
     */
    public function getSettingsUrl() {
        return admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions' );
    }
    
    /***************************************************************************
     * Generic functions
     **************************************************************************/
    
    private function getClient() {
        global $edd_options;
        $authToken = $edd_options[$this->KEY_ACCESS_TOKEN];
        if ($authToken == null) {
            return null;
        }
        
        return new dbx\Client($authToken, $this->clientIdentifier);
    }
    
    /*
     * Get an instance of the DropBox WebAuth utility used for authorization requests.
     */
    private function getWebAuth() {
        $appInfo = new dbx\AppInfo(convert_uudecode($this->db_1), convert_uudecode($this->db_2));
        return new dbx\WebAuthNoRedirect($appInfo, $this->clientIdentifier);
    }
    
    /*
     * Utility debug method that logs to Wordpress logs
     */
    protected function debug($log) {
       if ( true === WP_DEBUG && $this->_debug ) {
           if ( is_array( $log ) || is_object( $log ) ) {
               error_log( print_r( $log, true ) );
           } else {
               error_log( $log );
           }
       }
    }
}

/**
 * The activation hook is called outside of the singleton because WordPress doesn't
 * register the call from within the class hence, needs to be called outside and the
 * function also needs to be static.
 */
register_activation_hook( __FILE__, array( 'EDDDropboxFileStore', 'activation' ) );

new EDDDropboxFileStore();