<?php

/**
 * This file contains the class Zip_All_Post_Attachments
 *
 * This class iterates across all the custom post attachments of a designated type
 * and stores them as a single zip on the web server.
 *
 * Currently this class will only work for custom post types
 *
 * @todo Make class work for post/pages, not just custom post type
 *
 * @package WordPress
 */

class Zip_All_Post_Attachments
{
    public $post_type_name;
    public $zip_locale;
    public $zip_download_link;

    /* Class constructor */
    public function __construct( $parent ){

        $this->post_type_name = strtolower( str_replace( ' ', '_', $parent ) );
        $this->zip_locale = ABSPATH.trailingslashit(get_option('upload_path')).'all_'.$this->post_type_name.'s.zip';//where the zip is stored
        $this->zip_download_link = trailingslashit(get_site_url()).trailingslashit(get_option('upload_path')).'all_'.$this->post_type_name.'s.zip';//Zip location to pass to frontend

        add_action('admin_menu', array( &$this, 'add_new_admin_page' ) );
        add_action('wp_trash_post', array(&$this, 'delete_from_zip'));//for wordpress v3.3 onwards
        add_action('trash_post', array(&$this, 'delete_from_zip'));//added for backwards compatibility
        add_action('admin_init', array(&$this, 'remove_last_file'));//delete_from_zip can't delete the last file from the zip, this can.
        add_action('untrash_post', array(&$this, 'un_trash_post'));//make sure to also use trash_post to ensure backwards compatibility
    }

/**
 * Creates a new (sub) menu page for the post type given as a parameter
 *
 * @since   1.0
 *
 * @param   string    $post_type_name    The post type to attach the page to
 * @return  none
 *
 * @todo    inherit from custom post type, as it's currently declared twice
 * @todo    allow parent page to optionally be set as post/page, not just custom post type.
 */
    public function add_new_admin_page(){//Takes page title as ref (parent is assumed as post above.

            $post_type_name = $this->post_type_name;
            $parent_page      = 'edit.php?post_type='.$post_type_name;
            $page_title       = 'Current Attachments';
            $page_slug        = strtolower( str_replace( ' ', '_', $page_title  ) );

            add_submenu_page(  $parent_page, $page_title, $page_title, 'manage_options', $page_slug,  array( &$this,'current_files') );
    }//end add_new_admin_page


/**
 * @todo DELETE THIS ON LIVE
 */
    public function current_files(){

        $post_type_name = $this->post_type_name;

        $presentations = get_posts( array(//get all posts of correct type
            'post_type'      => $post_type_name,
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ) );

        if($presentations){

            foreach($presentations as $presentation){//Get ID of every presentation post
                 $pres_ids[] = $presentation->ID;
            }

            foreach($pres_ids as $pres_id){//Use ID of parent post to get each attachment
                $attachment = get_posts( array(
                    'post_type'      => 'attachment',
                    'posts_per_page' => -1,
                    'post_parent'    => $pres_id,
                ) );

                $parent_status = get_post_status($attachment[0]->post_parent);//child status is always inherit, need parent status.
                if($parent_status === 'publish'){//make sure post is no trash, pending, draft, private etc.

                    $file_path = get_attached_file($attachment[0]->ID);//ZipArchive needs file paths, not urls
                    echo $file_path.'<br/>';//REMOVE FOR LIVE

                }//endif

            }

        }
    }

/**
 * Generates a list of all the files attached to the posts of the given type in the database
 *
 * Will only take posts with status of "publish", so "trash" will be ignored.
 *
 * @since    1.0
 *
 * @param    string    $post_type_name      The post type to which the files are attached
 * @return   array     $all_file_paths      List of file paths passed to build_zip, which compiles them to a zip file
 * @uses     build_zip()
 *
 * @todo     Consider updating this to a SQL pull, rather than 2 get_post calls (inefficient)
 * @todo     Fix up the $attachment[0] loop, which is ugly
 */
    public function list_files(){

        $post_type_name = $this->post_type_name;

        $presentations = get_posts( array(//get all posts of correct type
            'post_type'      => $post_type_name,
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ) );

        if($presentations){

            foreach($presentations as $presentation){//Get ID of every presentation post
                 $pres_ids[] = $presentation->ID;
            }

            foreach($pres_ids as $pres_id){//Use ID of parent post to get each attachment
                $attachment = get_posts( array(
                    'post_type'      => 'attachment',
                    'posts_per_page' => -1,
                    'post_parent'    => $pres_id,
                ) );

                $parent_status = get_post_status($attachment[0]->post_parent);//child status is always inherit, need parent status.
                if($parent_status === 'publish'){//make sure post is no trash, pending, draft, private etc.

                    $file_path = get_attached_file($attachment[0]->ID);//ZipArchive needs file paths, not urls

                    if(file_exists($file_path)){
                        $all_file_paths[] = $file_path;//store all file paths
                    }//endif
                }//endif

            }

            if(!empty($all_file_paths)){//do not call function if array is empty
                $this->build_zip($all_file_paths);
            }//endif

        }//end if presentations

        else{
            echo "There are no $post_type_name's at present!";
        }
    }//end list_files

/**
 * Builds a zip from a list of filepaths
 *
 * This takes the list of path generated by list_files. ZipArchive is used to create the new zip on the server
 * There are no checks here for duplicate file names - because that was done earlier by wp_upload_bits.
 *
 * @since   1.0
 *
 * @param   array    $all_file_paths       The list of file paths. Note ZipArchive requires paths, not urls.
 * @return  none
 *
 * @todo    add check for PDF's
 * @todo    add safety check for duplicate file names
 */
    public function build_zip($all_file_paths){

        ini_set('max_execution_time', 0);

        $zip_path = $this->zip_locale;//location of zip on server. set in construct

        $files_to_zip = $all_file_paths;
        if(count($files_to_zip)){//check we have valid files

            $zip = new ZipArchive;
            $opened = $zip->open($zip_path, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);

            if( $opened !== true ){
                die("cannot open file.zip for writing. Please try again in a moment.");
            }//endif

            foreach ($files_to_zip as $file) {
                    $short_name = basename($file);
                    $zip->addFile($file,$short_name);
            }//end foreach
            $zip->close();
        }//endif
    }//end build_zip

/**
 * Checks when a post is moved to trash using wp_trash_post, and deletes it from the zip.
 *
 * Files in the zip are stored with a name and index. the index (int) changes as files get added and removed
 * so cannot be used as a valid indentifier.
 * There are no checks here for duplicate file names - because that was done earlier by wp_upload_bits.
 *
 * @since   1.0
 *
 * @param   int/array    $postid       The post ids of the files to be deleted. Generated by wp_trash_post in the construct.
 * @return  none
 *
 * @todo    add call to delete_post or before_delete_post. (may not be needed)
 * @todo    add check for valid filetype?
 */
    public function delete_from_zip($postid){

        ini_set('max_execution_time', 0);

        $zip_path = $this->zip_locale;//location of zip on server. set in construct

        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_parent'    => $postid,
        ) );

        $all_presentations = get_posts(array(
            'post_type'   => 'presentation',
            'post_status' => 'publish'
            ));

        if ( $attachments ) {
            foreach ( $attachments as $attachment ) {

                $file_path = get_attached_file($attachment->ID);//ZipArchive needs file paths, not urls

                if(file_exists($file_path)){
                    $all_file_paths[] = $file_path;//store all file paths
                }//end if

            }//end foreach

            if( count($all_presentations) > 1 ){//if the entry isn't the last in the zip
                $zip = new ZipArchive;
                $opened = $zip->open($zip_path);

                if( $opened !== true ){
                    die("cannot open file.zip for writing. Please try again in a moment.");
                }//endif

                foreach($all_file_paths as $path ){

                    $short_name = basename($path);
                    $result = $zip->deleteName($short_name);

                }//end foreach
                $zip->close();
            } elseif (is_file($zip_path)) {
                //we don't need to remove the last pdf, we can just delete the entire zip
                unset($zip_path);
            }//endif

        }//endif
        else{
            echo "There are no $post_type_name's at present!";
        }
    }//end delete_from_zip

 /**
 * Check if there are any published posts. If not it deletes the zip from the server.
 *
 * This is needed in case someone adds several files, then deletes them. delete_from_zip will not delete
 * the zip folder, and leaves one file remaining in the folder.
 *
 * @since   1.0
 *
 * @param   none
 * @return  none
 *
 * @todo    Add error checking
 */
    public function remove_last_file(){

        $post_type_name = $this->post_type_name;

        $zip_path = $this->zip_locale;//location of zip on server. set in construct

        $attachments = get_posts( array(
            'post_type'      => $post_type_name,
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ) );

        if ( ! $attachments ) {

            if(file_exists($zip_path)){
                unlink($zip_path);
            }
        }

    }//end remove_last_file


/**
 * Checks when a post is moved from Trash back to Published. Restores file in Zip
 *
 * Called with untrash_post in the construct
 *
 * @since   1.0
 *
 * @param   int/array    $postid       The post ids of the files to be un-deleted. Generated by untrash_post in the construct.
 * @return  none
 *
 * @todo    rebuild this and combine with "build zip" to remove redundancy.
 */
    public function un_trash_post($postid){

        ini_set('max_execution_time', 0);

        $zip_path = $this->zip_locale;//location of zip on server. set in construct

        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_parent'    => $postid,
        ) );

        if ( $attachments ) {
            foreach ( $attachments as $attachment ) {

                $file_path = get_attached_file($attachment->ID);//ZipArchive needs file paths, not urls
                if(file_exists($file_path)){
                    $all_file_paths[] = $file_path;//store all file paths
                }//end if

            }//end foreach

            if(!empty($all_file_paths)){//do not call function if array is empty
                $files_to_zip = $all_file_paths;
                if(count($files_to_zip)){//check we have valid files

                    $zip = new ZipArchive;
                    $opened = $zip->open($zip_path, ZIPARCHIVE::CREATE);//Create added in case all files were previously deleted

                    if( $opened !== true ){
                        die("cannot open file.zip for writing. Please try again in a moment.");
                    }//endif

                    foreach ($files_to_zip as $file) {
                            $short_name = basename($file);
                            $zip->addFile($file,$short_name);

                    }//end foreach
                    $zip->close();
                }//endif
            }//end if

        }//endif
        else{
            echo "There are no $post_type_name's at present!";
        }
    }//end un_trash_post


}//end class
?>