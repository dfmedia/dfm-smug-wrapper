DFM Smug Wrapper 1.0 - PHP Wrapper for the SmugMug API v2.0
===========================================================

Contributors: Nick Fabrizio, Eric McAllister  
Sponsor: Digital First Media

DFM Smug Wrapper is a PHP wrapper class for the SmugMug API v2.0 and is based on work done by Colin Seymour in [phpSmug](http://phpsmug.com). Version 1.0 of the DFM Smug Wrapper uses the API v2.0 endpoints that are compatible with the old SmugMug UI. These endpoints will likely be deprecated in future SmugMug API releases. 

Released under [GNU General Public License version 3](http://www.gnu.org/licenses/gpl.html)

Copyright (C) 2015 Digital First Media

    This file is part of DFM Smug Wrapper.

     DFM Smug Wrapper is free software: you can redistribute it and/or modify it under
     the terms of the GNU General Public License as published by the Free
     Software Foundation, either version 3 of the License, or (at your option)
     any later version.

     DFM Smug Wrapper is distributed in the hope that it will be useful, but WITHOUT ANY
     WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
     FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
     details.

     You should have received a copy of the GNU General Public License along
     with DFM Smug Wrapper.  If not, see <http://www.gnu.org/licenses/>.


--------------------------------------------------------------------------------


Introduction
============

DFM Smug Wrapper was created to allow applications to utilize the SmugMug API v2.0 and to be backwards compatible with phpSmug. DFM Smug Wrapper is provided as a resource for the dev community, but is not currently being supported, so feel free to take the code we have provided and change it to suit your needs.

One of the biggest differences in using the SmugMug API v2.0 is that album and image IDs are no longer used. Instead, album and image keys are used. Categories, which are now called folders, no longer use IDs, but instead use the folder name. For these reasons, some of the arguments passed into the different methods have changed. If you intend to use DFM Smug Wrapper to allow an existing application to use the SmugMug API v2.0, you will need to check and adjust the arguments in your existing methods.


Requirements
============

DFM Smug Wrapper is written in PHP and utilises functionality supplied with PHP 5.3 and later.

From a PHP perspective, the only requirement is PHP 5.3 with cURL support enabled.


Installation
============

Copy the files from the installation package into a folder on your server. They need to be readable by your web server.  You can put them into an include folder defined in your php.ini file, if you like, though it's not required.


Usage
=====

To use DFM Smug Wrapper, all you have to do is include the file in your PHP scripts and
create an instance.  For example:

           require_once("dfm-smug-wrapper/dfm-smug-wrapper.php");
           $f = new DFM_Smug(... arguments go here ...);

The constructor takes seven arguments:

   * `oauth_consumer_key` - **Required for ALL endpoints**

     This is the API key you have obtained for your application from
     <https://api.smugmug.com/api/developer/apply>.

   * `oauth_secret` - **Required for OAuth authentication**  

     This is the secret assigned to your API key and is displayed in the
     Settings tab of the SmugMug Control Panel. If no secret is displayed,
     select "change" next to the API key your application will use and click
     "save".  A secret will be generated for you.

   * `token_id` - **Required for OAuth authentication**

     This is the access token id sent from SmugMug to your app when the user 
     authorized it in their SmugMug account.

   * `token_secret` - **Required for OAuth authentication**

     This is the access token secret sent from SmugMug to your app when the user 
     authorized it in their SmugMug account.

   * `app_name` - Optional  
     Default: NULL

     This is the name, version and URL of the application you have built using
     the DFM Smug Wrapper. There is no required format, but something like:

          "My Cool App/1.0 (http://my.url.com)"

     ... would be very useful.

     Whilst this isn't obligatory, it is recommended as it helps SmugMug
     identify the application that is calling the API in the event one of your
     users reporting a problem on the SmugMug forums.

   * `api_ver` - Optional  
     Default: 2.0

     Use this to set the endpoint you wish to use.  The default is 2.0 as
     this is the latest "Stable" endpoint provided by SmugMug.

   * `content_type` - Optional
     Default: application/json

     This is the content type for the data that you are sending and that you would
     like to receive back from SmugMug. Also accepts application/vnd.php.serialized
     and application/x-msgpack.
 

Arguments to all DFM Smug Wrapper methods must be provided as a series of strings or an
associative array. For example:

* *Arguments as strings:*

          $f = new DFM_Smug(
               "oauth_consumer_key=12345678",
               "oauth_secret=some_secret",
               "token_id=12345",
               "token_secret=some_other_secret",
               "app_name=My Cool App/1.0 (http://app.com)",
               "api_ver=2.0"
          );

* *Arguments as an associative array:*

          $f = new DFM_Smug(
               array(
                   "oauth_consumer_key" => "12345678",
                   "oauth_secret" => "some_secret",
                   "token_id" => "12345",
                   "token_secret" => "some_other_secret",
                   "app_name" => "My Cool App/1.0 (http://app.com)",
                   "api_ver" => "2.0"
               )
          );

Naturally, you can predefine the array before instantiating the object and just
pass the array variable.

DFM Smug Wrapper implements all methods and arguments as documented in the SmugMug API
[documentation](http://api.smugmug.com/api/v2/doc).

To call a method, use the method names from phpSmug and the old SmugMug API v1.3. To call a method, use the SmugMug API v1.3 method name and replace any fullstops with underscores. For example, instead of `smugmug.images.get`, you would call `images_get()`.

Remember: **ALL** function names and arguments **ARE** case sensitive.

`images_upload()` does not use the API v2.0 for uploading, but instead uses the SmugMug upload API at <http://api.smugmug.com/services/api/?method=upload>.


Authentication
==============

You must authenticate with SmugMug in order to use the API.

**Note: The 2.0 API endpoints only support OAuth authentication.**

   * OAuth:

     This is the most secure of all the methods as your username and password
     are only ever entered on the SmugMug website.

     Authenticating using OAuth is a 3 step process.

     * First, you need to request an unauthorised request token:

              $resp = $f->auth_getRequestToken();

       Once you've obtained the token, you need to use it to direct the user to
       SmugMug to authorise your application.  You can do this in a variety of
       ways. It's up to you as the application developer to choose which method
       suits you. Ultimately, you need to direct the user to
       <http://api.smugmug.com/services/oauth/authorize.mg> with the required
       "Access", "Permissions" and the "oauth_token" arguments.

       DFM Smug Wrapper provides a simple method that generates a link you can use for
       redirection or for the user to click (it also takes care of passing the
       OAuth token):

              echo '<a href="'.$f->authorize("Access=[Public|Full]", "Permissions=[Read|Add|Modify]").'">Authorize</a>';

       "Public" and "Read" are the default options for Access and Permissions
       respectively, so you can leave them out if you only need these permissions.

     * Once the user has authorized your application, you will need to request
       the access token and access token secret (once again DFM Smug Wrapper takes care of
       passing the relevant OAuth token):

              $token = $f->auth_getAccessToken();

       You will need to save the token and token secret returned by the
       `auth_getAccessToken()` call in your own location for later use.

     * Once you've saved the token and token secret, you will no longer need to
       use any of the authentication methods above.  Simply pass the token ID and token
       secret when instantiating your object instance.

       For example:

          $f = new DFM_Smug(
               array(
                   "oauth_consumer_key" => "12345678",
                   "oauth_secret" => "some_secret",
                   "token_id" => "12345",
                   "token_secret" => "some_other_secret",
                   "app_name" => "My Cool App/1.0 (http://app.com)",
                   "api_ver" => "2.0"
               )
          );

       DFM Smug Wrapper uses the HMAC-SHA1 signature method. This is the most
       secure signature method.


Display Unlinkable Images
=========================

By default, when you create a new gallery within SmugMug, you will be able to 
display/embed the images from within this gallery on external websites.  If you 
change the gallery settings and set "External links" to "No", you will no longer
be able to do that.


Uploading
=========

Uploading is very easy.  You can either upload an image from your local system,
or from a location on the web.

In order to upload, you will need the album ID of the album you wish to upload to.

Then it's just a matter of calling the method with the various optional
parameters.

For example, upload a local file using:

        $f->images_upload("AlbumID=123456", "File=/path/to/image.jpg");

If you want the file to have a specific name on SmugMug, then add the optional
"FileName" argument.  If you don't specify a filename, the source filename will
be used.

You can find a list of optional parameters, like caption and keywords on the
API documentation page at <http://api.smugmug.com/services/api/?method=upload>.


Replacing Photos
================

Replacing photos is identical to uploading.  The only difference is you need to
specify the ImageKey of the image you wish to replace.


Other Notes
===========

   * By default, DFM Smug Wrapper will check whether it can use wp_remote_get() to 
     communicate with the SmugMug API endpoint if it's available. If not, it will revert 
     to using cURL. 

   **DFM Smug Wrapper does not currently support communication using sockets.**

   **DFM Smug Wrapper does not currently support the use of proxy servers.**

   * If DFM Smug Wrapper encounters an error, or SmugMug returns a "Fail" response, an
     exception will be thrown and your application will stop executing. If
     there is either a problem with communicating with the endpoint or if an error is 
     detected elsewhere, a DFM_Smug_Exception will be thrown.

     It is recommended that you configure your application to catch exceptions
     from DFM Smug Wrapper.


Examples
========

DFM Smug Wrapper comes with several examples to help get started.

        $f->albums_get( // Get a list of all albums  
             "Username=$username"  
        );

        $f->albums_getInfo( // Get album info  
             "AlbumKey=$album_key"  
        );

        $f->images_get( // Get a list of all images for an album  
             "AlbumKey=$album_key"  
        );

        $f->images_getInfo( // Get image info  
             "ImageKey=$image_key"  
        );

        $f->images_getURLs( // Get image URLs  
             "ImageKey=$image_key"  
        );

        $f->categories_get( // Get a list of all categories  
             "Username=$username"  
        );

        $f->subcategories_get( // Get a list of all subcategories  
             "Username=$un",  
             "ParentCategory=$category_name"  
        );

        $f->subcategories_delete( // Delete a category  
             "Username=$un",  
             "ParentCategory=$category_name",  
             "ChildCategory=$subcategory_name"  
        );

        $f->categories_delete( // Delete a subcategory  
             "Username=$username",  
             "Category=$category_name"  
        );

        $f->images_delete( // Delete an image  
             "ImageKey=$image_key"  
        );

        $f->albums_delete( // Delete an album  
             "AlbumKey=$album_key"  
        );

        $f->images_changeSettings( // Change info about an image  
             "ImageKey=$image_key",  
             "ImageData=$image_data_json_array"  
        );

        $f->albums_changeSettings( // Change info about an album  
             "AlbumKey=$album_key",  
             "AlbumData=$album_data_json_array"  
        );

        $f->subcategories_rename( // Change the name of a category  
             "Username=$username",  
             "ParentCategory=$category_name",  
             "ChildCategory=$subcategory_name",  
             "SubcategoryData=$subcategory_data_json_array"  
        );

        $f->categories_rename( // Change the name of a subcategory   
             "Username=$username",  
             "Category=$category_name",  
             "CategoryData=$category_data_json_array"  
        );

        $f->categories_create( // Create a category  
             "Username=$username",  
             "CategoryData=$category_data_json_array"  
        );

        $f->subcategories_create( // Create a subcategory  
             "Username=$username",  
             "ParentCategory=$category_name",  
             "SubcategoryData=$subcategory_data_json_array"  
        );
        $f->albums_create( // Create an album  
             "Username=$username",  
             "Category=$category_name",  
             "AlbumData=$album_data_json_array"  
        );

        $f->images_changePositions( // Change the position of an image in an album  
             "AlbumKey=$album_key",  
             "ImageData=$image_data_json_array" // Must include Uri (image to reference), MoveUris (comma separated list of images to move) and MoveLocation (Before or After)  
        );

        $f->images_upload( // Upload an image  
             "AlbumID=$album_id",  
             "File=$file_path",  
             "FileName=$filename",  
             "Caption=$caption"  
        );

Contributing
============

DFM Smug Wrapper is provided as a resource to help the community, but is not currently being supported. Feel free to submit pull requests, but while we welcome input and feedback, we cannot guarantee if/when pull requests will be merged. 

Support
=======

DFM Smug Wrapper is provided as a resource to help the community, but is not currently being supported. If you have feature requests or bugs to report, you can send them to dfm-smug-wrapper@medianewsgroup.com, but we cannot make any guarantees that these requests will be addressed.
