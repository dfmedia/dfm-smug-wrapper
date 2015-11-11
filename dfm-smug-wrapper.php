<?php
 /*
  * The DFM Smug Wrapper is a PHP wrapper class to allow HTTP headers to be sent to SmugMug using OAuth
  *
  * Accepts most of the methods from SmugMug API 1.3 PHP endpoint for backwards compatibility. Uses a switch
  * statement to properly set the methods and endpoints for each of the old methods, and then encodes all of 
  * data correctly for OAuth for sending it to SmugMug and returns a response from SmugMug. This wrapper will 
  * use some of the built in WP functions for sending and receiving the requests, if those WP functions exist.
  * 
  * Version: 1.0
  * Contributors: Nick Fabrizio, Eric McAllister
  * Sponsor: Digital First Media
  * License: GPL 3 http://www.gnu.org/copyleft/gpl.html
  * Copyright: Copyright (c) 2015 Nick Fabrizio, Eric McAllister
  * 
  * DFM Smug Wrapper is free software: you can redistribute it and/or modify it under the terms of the GNU
  * General Public License as published by the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  *
  * DFM Smug Wrapper is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even 
  * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public 
  * License for more details.
  *
  * You should have received a copy of the GNU General Public License along with DFM Smug Wrapper.  If not, see 
  * <http://www.gnu.org/licenses/>.
  * 
  * For installation and usage instructions, open the README.txt file packaged with this class. If you don't have 
  * a copy, you can refer to the documentation at: http://phpsmug.com/docs/
  * 
  * DFM Smug Wrapper is inspired by phpSmug (http://phpsmug.com) by Colin Seymour <lildood@gmail.com>
  * 
  */
/*

The SmugMug functions we need to replicate:
Old Function Name						Method		Endpoint
	**albums_delete						DELETE		/api/v2/album/[albumKey]
	**albums_getInfo					GET			/api/v2/album/[albumKey]
	**albums_changeSettings				PATCH		/api/v2/album/[albumKey]
	**images_get						GET			/api/v2/album/[albumKey]!images
	**images_changePositions			POST		/api/v2/album/[albumKey]!sortImages
	**categories_delete					DELETE		/api/v2/folder/user/[folderName]
	**categories_rename					PATCH		/api/v2/folder/user/[folderName]
	**subcategories_rename				PATCH		/api/v2/folder/user/[parentFolder]/[folderName]
	**categories_create					POST		/api/v2/folder/user/[username]!folders
	**categories_get					GET			/api/v2/folder/user/[username]!folders
	**subcategories_get					GET			/api/v2/folder/user/[username]/[parentFolder]!folders
	**subcategories_create				POST		/api/v2/folder/user/[username]/[parentFolder]!folders
	**subcategories_delete				DELETE		/api/v2/folder/user/[username]/[parentFolder]/[folderName]
	**images_changeSettings				PATCH		/api/v2/image/[imageKey]
	**images_delete						DELETE		/api/v2/image/[imageKey]
	**images_getInfo					GET			/api/v2/image/[imageKey]
	**images_getURLs					GET			/api/v2/image/[imageKey]!sizedetails
	**albums_get						GET			/api/v2/user/[username]!albums
	**albums_create						POST		/api/v2/[username]/[folderName]!albums
	**images_uploadFromURL				POST		http://upload.smugmug.com
*/

if ( !class_exists('DFM_Smug_Exception') ) {
	/*
	 * A class that extends the PHP Exception class
 	 * 
	 * Allows the code to handle, throw and log errors when appropriate. It will generally add the errors
	 * and any error messages to the apache error log. Messages can be customized when the
	 * DFM_Smug_Excpetion class is declared and thrown.
	 *
	 */
	class DFM_Smug_Exception extends Exception {}
}

class DFM_Smug {
	/*
	 * Declares our variables for our DFM_Smug class.
	 * 
	 * Sets certain endpoints for use throughout this class so that we can easily communicate with SmugMug
	 * and get the return data that we want. Also sets our oauth parameters so that we do not need to keep
	 * declaring for use in our methods. 
	 *
	 * @return void
	 */
	function __construct() {
		$args = DFM_Smug::process_args( func_get_args() );
		$this->smug_api_base = 'https://api.smugmug.com';
		$this->smug_access_base = $this->smug_api_base . '/services/oauth/1.0a';
		$this->endpoint_base = $this->smug_api_base . '/api/v2/';
		$this->album_base = $this->endpoint_base . 'album/';
		$this->folder_base = $this->endpoint_base . 'folder/user/';
		$this->image_base = $this->endpoint_base . 'image/';
		$this->upload_base = 'http://upload.smugmug.com/';

		$this->api_ver = ( isset( $args['api_ver'] ) ) ? $this->dfm_filter_var( $args['api_ver'] ) : '2.0';
		$this->app_name = ( isset( $args['app_name'] ) ) ? $this->dfm_filter_var( $args['app_name'] ) . ' using DFM SmugMug Wrapper v1.0' : 'DFM SmugMug Wrapper v1.0';
		$this->content_type = ( isset( $args['content_type'] ) ) ? $this->dfm_filter_var( $args['content_type'] ) : 'application/json';
		$this->oauth_signature_method = ( isset( $args['sig_method'] ) ) ? $this->dfm_filter_var( $args['sig_method'] ) : 'HMAC-SHA1';
		$this->oauth_timestamp = time();
		$this->oauth_version = ( isset( $args['oauth_version'] ) ) ? $this->dfm_filter_var( $args['oauth_version'] ) : '1.0';
		$this->token_id = $this->dfm_filter_var( $args['token_id'] );
		$this->token_secret = $this->dfm_filter_var( $args['token_secret'] );	
		$this->oauth_consumer_key = $this->dfm_filter_var( $args['oauth_consumer_key'] );
		$this->oauth_secret = $this->dfm_filter_var( $args['oauth_secret'] );
		$this->oauth_callback = ( isset( $args['oauth_callback'] ) ) ? $this->dfm_filter_var( $args['oauth_callback'] ) : 'oob';

	}

	/*
	 * Dynamic method handler.  
	 * 
	 * This function handles all SmugMug method calls not explicitly implemented by our class. Uses a 
	 * switch statememt to handle the methods from the old SmugMug API (1.3 and earlier) for backwards
	 * compatibility. Each switch case sets the method, endpoint and any arguments that need to be 
	 * passed and then passes them to the dfm_wp_remote_request method to actually be sent to SmugMug.
	 * Renaming variables for simplicity in all switch cases. Combining a few cases to cut down on 
	 * repitive code.
	 *
	 * @see dfm_wp_remote_request()
`	 *
	 * @global string $endpoint_base Endpoint containing initial portion of URL for generic construction.
	 * @global string $album_base    Endpoint for album data.
	 * @global string $image_base    Endpoint for image data.
	 * @global string $folder_base   Endpoint for folder/category data.
	 * 
	 * @param string $method The SmugMug method you want to call, which will use the methods from 
	 * 			 SmugMug API 1.3 and prior.
	 * @param array	 $arguments {
	 *     Array containing data needed to create the appropriate API call.
	 *
	 *     @type string $oauth_callback URL to have SmugMug send user to after authorization. 
	 *     @type integer $OauthVerifier The verification code sent back from SmugMug after user authorizes 
	 *                                  the app.
	 *     @type string $Username Username for the SmugMug account.
	 *     @type string $AlbumKey Album key for the SmugMug album.
	 *     @type string $ImageKey Image key for the SmugMug album.
	 *     @type string $ParentCategory Parent category/folder name used for subcategories.
	 *     @type string $ChildCategory Category/Folder name used for subcategories.
	 *     @type string $Category Category/Folder name.
	 *     @type array $CategoryData Array of data related to category/folder to be sent to SmugMug.
	 *     @type array $SubcategoryData Array of data related to subcategory/folder to be sent to SmugMug.
	 *     @type array $AlbumData Array of data related to album to be sent to SmugMug.
	 *     @type array $ImageData Array of data related to image to be sent to SmugMug.
	 * }
	 * @return array Data array returned from SmugMug.
	 */
	public function __call( $method, $arguments ) {
		$args = DFM_Smug::process_args( $arguments );
		switch( $method ) {
			case 'auth_getRequestToken':
			case 'auth_getAccessToken':
				// Split the case method apart so we can get what we need to create the endpoint
				$endpoint_base = explode( '_', $method );
				$endpoint_base = $endpoint_base[1];
				$endpoint_base = $this->dfm_filter_var( $endpoint_base );
				// Set this variable so that we do not send an oauth_token for initial requests when there is no oauth token
				$this->token_check = ( $endpoint_base == 'getRequestToken' ) ? $endpoint_base : false;
				$method = 'GET';
				$endpoint = $this->smug_access_base . '/' . $endpoint_base;

				$this->oauth_callback = $this->dfm_filter_var( $args['oauth_callback'] );
				$this->oauth_verifier = $this->dfm_filter_var( $args['OauthVerifier'] );
				break;
			case 'albums_get':
				$username = $this->dfm_filter_var( $args['Username'] ); // Simplicity, over and over!
				$this->var_exists_check( $username, $method ) ? $username : false;
				$method = 'GET';
				$endpoint = $this->endpoint_base . 'user/' . $username . '!albums';
				break;
			case 'albums_getInfo':
			case 'images_get':
				$album_key = $this->dfm_filter_var( $args['AlbumKey'] );
				$this->var_exists_check( $album_key, $method ) ? $album_key : false;
				$endpoint = $this->album_base . $album_key;
				if( $method === 'images_get' ){
					$endpoint = $endpoint . '!images';
				}
				$method = 'GET';
				break;
			case 'images_getInfo':
			case 'images_getURLs':
				$image_key = $this->dfm_filter_var( $args['ImageKey'] );
				$this->var_exists_check( $image_key, $method ) ? $image_key : false;
				$endpoint = $this->image_base . $image_key;
				if( $method === 'images_getURLs' ){
					$endpoint = $endpoint . '!sizedetails';
				}
				$method = 'GET';
				break;
			case 'categories_get':
				$username = $this->dfm_filter_var( $args['Username'] );
				$this->var_exists_check( $username, $method ) ? $username : false;
				$method = 'GET';
				$endpoint = $this->folder_base . $username . '!folders';
				break;
			case 'subcategories_get':
				$username = $this->dfm_filter_var( $args['Username'] );
				$parent_cat = $this->dfm_filter_var( $args['ParentCategory'] );
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $parent_cat, $method ) ? $parent_cat : false;
				$method = 'GET';
				$endpoint = $this->folder_base . $username . '/' . $parent_cat . '!folders';
				break;
			case 'subcategories_delete':
				$username = $this->dfm_filter_var( $args['Username'] );
				$parent_cat = $this->dfm_filter_var( $args['ParentCategory'] );
				$child_cat = $this->dfm_filter_var( $args['ChildCategory'] );
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $parent_cat, $method ) ? $parent_cat : false;
				$this->var_exists_check( $child_cat, $method ) ? $child_cat : false;
				$method = 'DELETE';
				$endpoint = $this->folder_base . $username . '/' . $parent_cat . '/' . $child_cat;
				break;
			case 'categories_delete':
				$username = $this->dfm_filter_var( $args['Username'] );
				$category = $this->dfm_filter_var( $args['Category'] );
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $category, $method ) ? $category : false;
				$method = 'DELETE';
				$endpoint = $this->folder_base . $username . '/' . $category;
				break;
			case 'images_delete':
				$image_key = $this->dfm_filter_var( $args['ImageKey'] );
				$this->var_exists_check( $image_key, $method ) ? $image_key : false;
				$method = 'DELETE';
				$endpoint = $this->image_base . $image_key;
				break;
			case 'albums_delete': 
				$album_key = $this->dfm_filter_var( $args['AlbumKey'] );
				$this->var_exists_check( $album_key, $method ) ? $album_key : false;
				$method = 'DELETE';
				$endpoint = $this->album_base . $album_key;
				break;
			case 'images_changeSettings':
				$image_key = $this->dfm_filter_var( $args['ImageKey'] );
				$this->var_exists_check( $image_key, $method ) ? $image_key : false;
				$method = 'PATCH';
				$endpoint = $this->image_base . $image_key;
				$item_args = $args['ImageData'];
				break;
			case 'albums_changeSettings':
				$album_key = $this->dfm_filter_var( $args['AlbumKey'] );
				$this->var_exists_check( $album_key, $method ) ? $album_key : false;
				$method = 'PATCH';
				$endpoint = $this->album_base . $album_key;
				$item_args = $args['AlbumData'];
				break;
			case 'categories_rename':
				$username = $this->dfm_filter_var( $args['Username'] );
				$category = $this->dfm_filter_var( $args['Category'] );			
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $category, $method ) ? $category : false;
				$method = 'PATCH';
				$endpoint = $this->folder_base . $username . '/' . $category;
				$item_args = $args['CategoryData'];
				break;
			case 'subcategories_rename':
				$username = $this->dfm_filter_var( $args['Username'] );
				$parent_cat = $this->dfm_filter_var( $args['ParentCategory'] );
				$child_cat = $this->dfm_filter_var( $args['ChildCategory'] );				
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $parent_cat, $method ) ? $parent_cat : false;
				$this->var_exists_check( $child_cat, $method ) ? $child_cat : false;
				$method = 'PATCH';
				$endpoint = $this->folder_base . $username . '/' . $parent_cat . '/' . $child_cat;
				$item_args = $args['SubcategoryData'];
				break;
			case 'categories_create':
				$username = $this->dfm_filter_var( $args['Username'] );
				$this->var_exists_check( $username, $method ) ? $username : false;
				$method = 'POST';
				$endpoint = $this->folder_base . $username . '!folders';
				$item_args = $args['CategoryData'];
				break;
			case 'subcategories_create':
				$username = $this->dfm_filter_var( $args['Username'] );
				$parent_cat = $this->dfm_filter_var( $args['ParentCategory'] );			
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $parent_cat, $method ) ? $parent_cat : false;
				$method = 'POST';
				$endpoint = $this->folder_base . $username . '/' . $parent_cat . '!folders';
				$item_args = $args['SubcategoryData'];
				break;
			case 'albums_create':
				$username = $this->dfm_filter_var( $args['Username'] );
				$category = $this->dfm_filter_var( $args['Category'] );			
				$this->var_exists_check( $username, $method ) ? $username : false;
				$this->var_exists_check( $category, $method ) ? $category : false;
				$method = 'POST';
				$endpoint = $this->folder_base . $username . '/' . $category . '!albums';
				$item_args = $args['AlbumData'];
				break;
			case 'images_changePositions':
				$album_key = $this->dfm_filter_var( $args['AlbumKey'] );
				$this->var_exists_check( $album_key, $method ) ? $album_key : false;
				$method = 'POST';
				$endpoint = $this->album_base . $album_key . '!sortimages';
				$item_args = $args['ImageData'];
				break;

			default:
				throw new DFM_Smug_Exception( 'Something went wrong!' );
				return;
		}
		// Use the WP function, if it exists, instead of cURL so we don't run into any issues on WP sites.
		if( function_exists( 'wp_remote_get' ) ) {
			$request = $this->dfm_wp_remote_request( $method, $endpoint, $item_args );
		} else {
			$request = $this->dfm_curl_remote_request( $method, $endpoint, $item_args );
		}
		return $request;
	}

	/**
	 * Uploads local files to SmugMug.
	 *
	 * Uses the put method of the SmugMug upload API to upload local files to the SmugMug account. Uses
	 * the basename of the local file URL as the file name if none is explicitly provided. Generates it's
	 * own request as the upload API requires some different parameters to be sent in the HTTP request.
	 * 
	 * @see process_args()
	 * @see url_encode_RFC3986()
	 * @see authorization_header()
	 * @see wp_remote_get()
	 * @see wp_remote_retrieve_body()
	 *
	 * @param integer $AlbumID The ID of the album to which the image is to be uploaded.
	 * @param string $File The path to the local file that is being uploaded.
	 * @param string $FileName (Optional) The filename to give the file on upload.
	 * @param string $ResponseType (Optional) Response type expected from SmugMug. Default JSON.
	 * @param string $Caption (Optional) The caption to give the file on upload. Default NULL.
	 * @param string $Keywords (Optional) The keywords to give the file on upload. Default NULL.
	 * @param string $Latitude (Optional) The latitude to give the file on upload. Default NULL.
	 * @param string $Longitude (Optional) The longitude to give the file on upload. Default NULL.
	 * @param string $Altitude (Optional) The altitude to give the file on upload. Default NULL.
	 * @param string $Hidden (Optional) Whether or not the image should be hidden on upload. Default FALSE.
	 * @param string $ImageID (Optional) The ID of the image to replace on upload. Default NULL.
	 * @link http://api.smugmug.com/services/api/?method=upload
	 * @return array
	 **/
	public function images_upload() {
		$method = 'PUT';
		$args = DFM_Smug::process_args( func_get_args() );
		if ( !array_key_exists( 'File', $args ) ) {
			throw new DFM_Smug_Exception( 'No upload file specified.' )
		;
			return;
		}
		
		// Set FileName, if one isn't provided in the method call
		if ( !array_key_exists( 'FileName', $args ) ) {
			$args['FileName'] = basename( $args['File'] );
		}

		// Ensure the FileName is url_encode_RFC3986 encoded - caters for strange chars and spaces
		$args['FileName'] = $this->url_encode_RFC3986( $args['FileName'] );

		$endpoint = $this->upload_base . $args['FileName'];
		
		// Check that this is a file and prepare it for being sent to SmugMug in a json array 
		if ( is_file( $args['File'] ) ) {
			// Make sure this is an image! ^^^
			// file_get_contents ??
			$fp = fopen( $args['File'], 'r' );
			$data = fread( $fp, filesize( $args['File'] ) );
			fclose( $fp );
		} else {
			throw new DFM_Smug_Exception( "File does not exist: {$args['File']}" );
			return;
		}


		$this->url = $endpoint;
		$auth_header = $this->authorization_header( $method );
		// We need to set up the HTTP headers to send with our request
		$http_header = array(
			'method' => $method,
			'headers' => array(
				'Host' => 'upload.smugmug.com',
				'User-Agent' => $this->app_name,
				'Content-MD5' => md5_file( $args['File'] ),
				'Connection' => 'keep-alive',
				'X-Smug-Version' => $this->api_ver,
				'X-Smug-ResponseType' => ( isset( $args['ResponseType'] ) ) ? $args['ResponseType'] : 'JSON',
				'X-Smug-AlbumID' => $args['AlbumID'],
				'X-Smug-Filename'=> basename( $args['FileName'] ),
				'X-Smug-ImageID' => ( isset( $args['ImageID'] ) ) ? $args['ImageID'] : null,
				'X-Smug-Caption' => ( isset( $args['Caption'] ) ) ? $args['Caption'] : null,
				'X-Smug-Keywords' => ( isset( $args['Keywords'] ) ) ? $args['Keywords'] : null,
				'X-Smug-Latitude' => ( isset( $args['Latitude'] ) ) ? $args['Latitude'] : null,
				'X-Smug-Longitude' => ( isset( $args['Longitude'] ) ) ? $args['Longitude'] : null,
				'X-Smug-Altitude' => ( isset( $args['Altitude'] ) ) ? $args['Altitude'] : null,
				'X-Smug-Hidden' => ( isset( $args['Hidden'] ) ) ? $args['Hidden'] : false,
				'Authorization' => $auth_header
			),
			'body' => $data
		);

		// Use the WP function instead of cURL so we don't run into any issues.
		// ^^^ Use if/else for cUrl
		$response = wp_remote_get( $this->url, $http_header );

		// Only use the body of the response rather than the whole thing.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check the returned status and if it is fail, throw an exception
		if ( $data['stat'] == 'fail' ) {
			$this->error_code = $data['code'];
           	$this->error_msg = $data['message'];
			$data = FALSE;
			throw new DFM_Smug_Exception( "SmugMug API Error for method image_upload: {$this->error_msg}", $this->error_code );
			return;
		} else {
			$this->error_code = FALSE;
            $this->error_msg = FALSE;
		}
		if( $response['headers']['content-type'] == 'application/json' ) {
			return $data ? $data['Image'] : FALSE;	
		} else{
			return $body;
		}
		
	}

	/*
	 * Creates the HTTP header for the requests.
	 *
	 * Sets up the array to create an HTTP header to be sent to SmugMug using the data that is passed in
	 * to the parent function. Creates the authorization header by calling the appropriate method. Sets 
	 * redirection to 0 so that we can properly handle any redirects by signing and sending a new request.
	 *
	 * @see authorization_header()
	 * 
	 * @param string $method The method for our request (GET, POST, PATCH, DELETE, OPTIONS).
	 * @param array $item_args Arguments to be sent to SmugMug in the body of the HTTP request.
	 * @param string $content_type The content type we expect (application/json, plaintext, etc).
	 * @param string $app_name The name of the consumer app that is initiating the request.
	 */
	public function create_wp_http_header( $method, $item_args ) {
		$new_header = $this->authorization_header( $method );
		// We need to set up the HTTP headers to send with our request
		$http_header = array(
			'method' => $method,
			'headers' => array(
				'Host' => 'api.smugmug.com',
				'Accept' => $this->content_type,
				'Content-Type' => $this->content_type,
				'User-Agent' => $this->app_name,
				'Authorization' => $new_header
			),
			'redirection' => 0,
			'timeout' => 60000, // Set timeout to 1 minute for large requests
			'body' => $item_args
		);
		return $http_header;
	}

	/*
	 * Creates the cURL HTTP header for the requests.
	 *
	 * Sets up the array to create a cURL HTTP header to be sent to SmugMug using the data that is passed in
	 * to the parent function. Creates the authorization header by calling the appropriate method. Sets 
	 * redirection to 0 so that we can properly handle any redirects by signing and sending a new request.
	 *
	 * @see authorization_header()
	 * 
	 * @param string $method The method for our request (GET, POST, PATCH, DELETE, OPTIONS).
	 * @param array $item_args Arguments to be sent to SmugMug in the body of the HTTP request.
	 * @param string $content_type The content type we expect (application/json, plaintext, etc).
	 * @param string $app_name The name of the consumer app that is initiating the request.
	 */
	public function create_curl_http_header( $method, $item_args ) {
		$new_header = $this->authorization_header( $method );
		// We need to set up the HTTP headers to send with our request
		$http_header = array(
			'method: ' . $method,
			'Host: api.smugmug.com',
			'Accept: ' . $this->content_type,
			'Content-Type: ' . $this->content_type,
			'User-Agent: ' . $this->app_name,
			'Authorization: ' . $new_header,
			'body: ' . $item_args
		);
		return $http_header;
	}

	/*
	 * Creates the OAuth authorization header for the HTTP request.
	 *
	 * Converts the OAuth data into an HTTP header and returns it as an array of comma separated key-value 
	 * pairs with each pair separated by an equals sign with the value enclosed in double quotes.
	 *
	 * @see __construct()
	 * @see generate_signature()
 	 *
	 * @param string $oauth_version The OAuth version we are using.
	 * @param string $oauth_nonce The OAuth nonce to send with our request.
	 * @param string $oauth_timestamp The timestamp to send with our request.
	 * @param string $oauth_consumer_key The API key for our application.
	 * @param string $token_id The SmugMug access token.
	 * @param string $token_check A variable set in the construct method for the getRequestToken method
	 * @param string $oauth_signature_method The signature method used (HMAC-SHA1 or PLAINTEXT).
	 * @param string $method The method for our request (GET, POST, PATCH, DELETE, OPTIONS).
	 * @return array The HTTP header for the SmugMug request.
	 */
	public function authorization_header( $method ) {

		// Set up our oauth parameters so we can send them through to the signature generation function
		$params = array (
			'oauth_version'             => $this->oauth_version,
			'oauth_nonce'               => md5( time() . mt_rand() ),
			'oauth_timestamp'           => $this->oauth_timestamp,
			'oauth_consumer_key'        => $this->oauth_consumer_key,
			'oauth_signature_method'    => $this->oauth_signature_method
		);

		// Check whether the method is getRequestToken which will not have a token_id
		if ( $this->token_check != 'getRequestToken' ) {
			$params['oauth_token'] = $this->token_id;
		} elseif( $this->token_check == 'getRequestToken' ) {
			$params['oauth_callback'] = $this->oauth_callback;
		}
		if( isset( $this->oauth_verifier ) && !is_null( $this->oauth_verifier ) ) { 
			$params['oauth_verifier'] = $this->oauth_verifier;
		}

		// Generate the OAuth signature
		$sig = $this->generate_signature( $method, $params );
		$oauth_params = array (
			'oauth_signature'           => $sig
		);
		//Add the signature to our array
		$oauth_params = array_merge( $params, $oauth_params );

		$header = 'OAuth ';

		$oauth_params2 = array();
		// Convert the array into the required OAuth format for each key value pair in the form some_key="some value"
		foreach ( $oauth_params as $key => $value ) {
			$oauth_params2[] = "$key=\"" . rawurlencode( $value ) . "\"";
		}

		$header .= implode( ', ', $oauth_params2 );

		return $header;
	}

	/*
	 * Generates the OAuth signature.
	 * 
	 * Generates the OAuth signature for the HTTP request by encoding all of the oauth key-value pairs and 
	 * returns the encoded value as a string. 
	 *
	 * @see	url_encode_RFC3986()
	 *
	 * @param string $api_call The method for our request (GET, POST, PATCH, DELETE, OPTIONS).
	 * @param array $api_args OAuth parameters needed to encode in the signature.
	 * @param string $oauth_secret The API secret for the app.
	 * @param string $token_secret The token secret for the app to give access for each account.
	 * @param string $url Endpoint to include with the HTTP request.
	 * @return string The signature to be included with the OAuth HTTP request.
	 */
	private function generate_signature( $api_call, $api_args = NULL ) {
		// Set up our OAuth encoding key
		if( isset( $this->oauth_secret ) && !is_null( $this->oauth_secret ) && isset( $this->token_secret ) && !is_null( $this->token_secret ) ) {
				$enc_key = $this->url_encode_RFC3986( $this->oauth_secret ) . '&' . $this->url_encode_RFC3986( $this->token_secret );
		} elseif( !isset( $this->oauth_secret ) || is_null( $this->oauth_secret ) ) {
			throw new DFM_Smug_Exception( 'There is a problem with your OAuth secret. Either it is not set or it is returning NULL. Try fixing it and running your code again.' );
			return;
		} elseif( !isset( $this->token_secret ) || is_null( $this->token_secret ) ) {
			throw new DFM_Smug_Exception( 'There is a problem with your OAuth token. Either it is not set or it is returning NULL. Try fixing it and running your code again.' );
			return;
		}
		$endpoint = $this->url;
		$method = $api_call;
		// URL encode the array keys
		$keys = array_map( array( $this, 'url_encode_RFC3986' ), array_keys( $api_args ) );
		// URL encode the array values
		$values = array_map( array( $this, 'url_encode_RFC3986' ), array_values( $api_args ) );
		// Combine the keys and vlues back into one array
		$api_args = array_combine( $keys, $values );
		// Sort the array key value pairs alphabetically
		uksort( $api_args, 'strnatcmp' );
		// Count the items in the array
		$count = count( $api_args );
		$string = '';
		// Convert the array to string with each key and value separated by an = and each key-value pair separated by an & 
		foreach ( $api_args as $key => $value ) {
			$count--;
			$string .= $key . '=' . $value;
			// ^^^ Come back to this, more efficient way to write?
			if ( $count ) {
				$string .= '&';
			}
		}
		// Create our base string with all of the data we need for OAuth
		$base_string = $method . '&' . $this->url_encode_RFC3986( $endpoint ) . '&' .  $this->url_encode_RFC3986( $string );

		// HMAC-SHA1 encode our base string
		$sig = base64_encode( hash_hmac( 'sha1', $base_string, $enc_key, true ) );
		return $sig;
	}

	/*
	 * Creates and returns the authorization URL.
	 *
	 * This method allows easy creation of the authorization URL so that apps can send users to the correct 
	 * URL to grant authorization in their SmugMug account. It does not actually send anything to SmugMug, 
	 * but formats the URL properly based on the arguments supplied when it is called.
	 *
	 * @param string $Access The requested level of access. Default Public.
	 * @param string $Permissions The requested permissions.  Default Read.
	 * @param string $smug_access_base The base URL for SmugMug authorization and access requests.
	 * @return string
	 */
	 public function authorize() {
		$args = DFM_Smug::process_args( func_get_args() );
		$perms = ( array_key_exists( 'Permissions', $args ) ) ? $args['Permissions'] : 'Public';
		$access = ( array_key_exists( 'Access', $args ) ) ? $args['Access'] : 'Read';
		$this->oauth_token = $args['TokenID'];
 		return $this->smug_access_base . "/authorize?Access=$access&Permissions=$perms&oauth_token={$this->oauth_token}";
	 }
	 

	/*
	 * URL encodes the input.
	 * 
	 * URL encodes strings to RFC3986 for use with OAuth, and handles errors with encoding the "~" character.
	 *
	 * @param string $string The string to be RFC3986 encoded.
	 * @return string URL encoded string.
	 */
	private static function url_encode_RFC3986( $string ) {
		return str_replace( '%7E', '~', rawurlencode( $string ) );
	}
	
	/*
	 * Processes the arguments passed to the methods.
	 * 
	 * Running each arg through the dfm_filter_var method to make sure all input is safe.
	 * Separates the arguments at the equals sign and merges them into an array. Borrowed from phpSmug.
	 *
	 * @param array $arguments Arguments taken from a function/method by func_get_args()
	 * @return array
	 */
	 protected static function process_args( $arguments ) {
	 	foreach ($arguments as $unsafe_var) {
	 		DFM_Smug::dfm_filter_var( $unsafe_var );
	 		$unsafe_var = strip_tags( $unsafe_var );
	 	}

		// Check if the arguments are in an array format and if not, convert them to an array (especially helpful if there is only one argument item)
		if( !is_array( $arguments ) ) { 
			$arguments = array( $arguments );
		}
		$args = array();
		// Convert our arguments to an array by separating them at the = and turning them into key-value pairs
		foreach ( $arguments as $arg ) {
			if ( is_array( $arg ) ) {
				$args = array_merge( $args, $arg );
			} else {
				$exp = explode( '=', $arg, 2 );
				$args[$exp[0]] = $exp[1];
			}
		}
		return $args;
	  }

	/*
	 * Creates the WP HTTP request and sends it to SmugMug.
	 *
	 * Takes all of the data and puts it into a proper HTTP request format. Then, sends the request using 
	 * the built in WP remote call function rather than cURL. Also, checks for 300 redirects and sends a 
	 * new request for each one with a while loop.
	 * 
	 * @see wp_remote_get()
	 * 
	 * @param string $method The method to use in the HTTP request (GET, POST, PUT, PATCH, DELETE, OPTIONS).
	 * @param string $endpoint The endpoint to which to send the HTTP request.
	 * @param array $item_args Arguments to be sent to SmugMug in the body of the HTTP request.
	 * @return array Data array containing the response from SmugMug.
	 */
	public function dfm_wp_remote_request( $method, $endpoint, $item_args = false ) {
		$this->url = $endpoint;
		$http_header = $this->create_wp_http_header( $method, $item_args );
		$response = wp_remote_get( $this->url, $http_header );

		if( is_wp_error( $response ) ) {
			$response_message = $response->get_error_message();	
			throw new DFM_Smug_Exception( 'There was an error when processing your request: '. $response_message );
			return;
		} 
	
		$response_code = $response['response']['code'];
		$response_location = $response['headers']['location'];
		$response_code = (int) substr( $response_code, 0, 1 );

		// If the SmugMug response code starts with 3, SmugMug is redirecting our request. Generate a new request so that we do not get the oauth nonce_used error
		while( $response_code == 3 ) {
			$this->url = $this->smug_api_base . $response_location;
			$http_header = $this->create_wp_http_header( $method, $item_args );			
			$response = wp_remote_get( $this->url, $http_header );
			$response_code = $response['response']['code'];
			$response_location = $response['headers']['location'];
			$response_code = (int) substr( $response_code, 0, 1 );
		} 

		// Just get the first part of the content-type so that we can check against it
		$resp_content_type = $response['headers']['content-type'];
		$resp_content_type = explode( ';', $resp_content_type );
		$resp_content_type = $resp_content_type[0];

		// Only use the body of the response rather than the whole thing.
		$body = $response['body'];

		// Only json decode if the response is in json format
		if( $resp_content_type == 'application/json' ) {
			$data = json_decode( $body, true );
			return $data;	
		} else{
			return $body;
		}
	}

	/*
	 * Creates the cURL HTTP request and sends it to SmugMug.
	 *
	 * Takes all of the data and puts it into a proper cURL HTTP request format. Then, sends the request  
	 * using cURL. Also, checks for 300 redirects and sends a new request for each one with a while loop.
	 * 
	 * @param string $method The method to use in the HTTP request (GET, POST, PUT, PATCH, DELETE, OPTIONS).
	 * @param string $endpoint The endpoint to which to send the HTTP request.
	 * @param array $item_args Arguments to be sent to SmugMug in the body of the HTTP request.
	 * @return array Data array containing the response from SmugMug.
	 */
	public function dfm_curl_remote_request( $method, $endpoint, $item_args=false ) {
		$this->url = $endpoint;
		$http_header = $this->create_curl_http_header( $method, $item_args );

		$ch = curl_init();

		$options = array(
			CURLOPT_URL		=> $this->url,
			CURLOPT_MAXREDIRS	=> 0,
			CURLOPT_CONNECTTIMEOUT	=> 5,
			CURLOPT_TIMEOUT		=> 0,
			CURLOPT_SSL_VERIFYPEER	=> FALSE,
			CURLOPT_BUFFERSIZE	=> 16384,
			CURLOPT_HTTPHEADER	=> $http_header,
			CURLOPT_RETURNTRANSFER	=> TRUE, // TRUE returns the result or false, FALSE returns true or false
			CURLOPT_FOLLOWLOCATION	=> FALSE
		);

		if ( $method === 'POST' ) {
			$options[CURLOPT_POST] = TRUE; // POST mode.
			$options[CURLOPT_POSTFIELDS] = $item_args;
		}
		else if ( $method === 'PUT' || $method === 'DELETE' || $method === 'PATCH' ) {
			$options[CURLOPT_CUSTOMREQUEST] = $method; 
			$options[CURLOPT_POSTFIELDS] = $item_args; 
		}
		curl_setopt_array($ch, $options);
		// ^^^ do something with exec fn
		$response = curl_exec( $ch );
		
		if ( curl_errno( $ch ) !== 0 ) {
			throw new DFM_Smug_Exception( sprintf( '%s: CURL Error %d: %s', __CLASS__, curl_errno( $ch ), curl_error( $ch ) ), curl_errno( $ch ) );
		}
		if ( substr( curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 0, 1 ) != 2 ) {
			throw new DFM_Smug_Exception( sprintf( 'Bad return code (%1$d) for: %2$s', curl_getinfo( $ch, CURLINFO_HTTP_CODE ), $url ), curl_errno( $ch ) );
		}

		curl_close( $ch );

		$response_code = $response['response']['code'];
		$response_location = $response['headers']['location'];
		$response_code = (int) substr( $response_code, 0, 1 );

		// If the SmugMug response code starts with 3, SmugMug is redirecting our request. Generate a new request so that we do not get the oauth nonce_used error
		while( $response_code == 3 ) {
			$this->url = $this->smug_api_base . $response_location;
			$http_header = $this->create_curl_http_header( $method, $item_args );

			$ch = curl_init();

			$options = array(
				CURLOPT_URL		=> $this->url,
				CURLOPT_MAXREDIRS	=> 0,
				CURLOPT_CONNECTTIMEOUT	=> 5,
				CURLOPT_TIMEOUT		=> 0,
				CURLOPT_SSL_VERIFYPEER	=> FALSE,
				CURLOPT_BUFFERSIZE	=> 16384,
				CURLOPT_HTTPHEADER	=> $http_header,
				CURLOPT_RETURNTRANSFER	=> TRUE, // TRUE returns the result or false, FALSE returns true or false
				CURLOPT_FOLLOWLOCATION	=> FALSE
			);

			if ( $method === 'POST' ) {
				$options[CURLOPT_POST] = TRUE; // POST mode.
				$options[CURLOPT_POSTFIELDS] = $item_args;
			}
			elseif ( $method === 'PUT' || $method === 'DELETE' || $method === 'PATCH' ) {
				$options[CURLOPT_CUSTOMREQUEST] = $method; 
				$options[CURLOPT_POSTFIELDS] = $item_args; 
			}
			curl_setopt_array($ch, $options);
			$response = curl_exec( $ch );

			if ( curl_errno( $ch ) !== 0 ) {
				throw new DFM_Smug_Exception( sprintf( '%s: CURL Error %d: %s', __CLASS__, curl_errno( $ch ), curl_error( $ch ) ), curl_errno( $ch ) );
			}
			if ( substr( curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 0, 1 ) != 2 ) {
				throw new DFM_Smug_Exception( sprintf( 'Bad return code (%1$d) for: %2$s', curl_getinfo( $ch, CURLINFO_HTTP_CODE ), $url ), curl_errno( $ch ) );
			}

			curl_close( $ch );
			$response_code = $response['response']['code'];
			$response_location = $response['headers']['location'];
			$response_code = (int) substr( $response_code, 0, 1 );
		} 

		// Just get the first part of the content-type so that we can check against it
		$resp_content_type = $response['headers']['content-type'];
		$resp_content_type = explode( ';', $resp_content_type );
		$resp_content_type = $resp_content_type[0];

		// Only use the body of the response rather than the whole thing.
		$body = $response['body'];

		// Only json decode if the response is in json format
		if( $resp_content_type == 'application/json' ) {
			$data = json_decode( $body, true );
			return $data;
		} else{
			return $body;
		}
		
	}

	/*
	 * Makes sure variable passed isset and is not null.
	 *
	 * @param string $var The variable coming from smugmug
	 * @param string $method The method to pass to make the Exception easier to debug
	 * @return string $var The same variable from smugmug after checks
	 */
	public function var_exists_check( $var, $method ) {
		if( !isset( $var ) || is_null( $var ) ){
			throw new DFM_Smug_Exception( "Required argument(s) for $method method are not set. Something went wrong." );
			return;
		} else {
			return $var;
		}
		return;
	}

	/*
	 * Basic variable sanitization. Reading them all as strings for simplicity
	 *
	 * @see filter_var()
	 * @see trim()
	 * @see esc_js()
	 *
	 * @param string $var User inputted data/data from Top Secret
	 * @return string $safe_var param variable after being run through safety checks
	 */
	public function dfm_filter_var( $var ) {
		// There is probably a better way to check if WP or not ^^^
		if( function_exists( 'wp_remote_get' ) ){
			$var = esc_js( $var );
			$var = esc_html( $var );
		} 
		$safe_var = strip_tags( $var );
		$safe_var = filter_var( $var, FILTER_SANITIZE_STRING );
		if( $safe_var === FALSE ){
			throw new DFM_Smug_Exception( "The variable inputted, $var, is not a safe variable! Please make variable safe and retry." );
			return;
		}
		return trim( $safe_var );
	}
}
?>
