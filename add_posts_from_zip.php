<?php
/**
 * This file contains the class Add_Posts_From_Zip
 *
 * This class takes a zip file uploaded by a user to the wordpress back end.
 * It takes each file in the zip and creates a post (of a type specified)
 * using the name of the file. The file is then added to the post as an
 * attachment, and also added to the media library.
 *
 * Currently this class will only work for custom post types
 *
 * @todo Make class work for post/pages, not just custom post type
 *
 * @package WordPress
 */

class Add_Posts_From_Zip extends Zip_All_Post_Attachments
{
    public $post_type_name;
    public $file_names;


    /* Class constructor */
    public function __construct( $parent ){
        parent::__construct( $parent );
        $this->post_type_name   = strtolower( str_replace( ' ', '_', $parent ) );  //name of the post type
        add_action( 'admin_menu', array( &$this, 'add_new_admin_page' ) );// Sets the upload zip page

    }

/**
 * Creates a new (sub) menu page for the post type given as a parameter
 *
 * @since 1.0
 *
 * @param    string    $post_type_name    The post type to attach the page to
 * @return   none
 * @uses     upload_zip_form()
 *
 * @todo inherit from custom post type, as it's currently declared twice
 * @todo allow parent page to optionally be set as post/page, not just custom post type.
 */
    public function add_new_admin_page(){//Takes page title as ref (parent is assumed as post above.

            $post_type_name = $this->post_type_name;
            $parent_page      = 'edit.php?post_type='.$post_type_name;
            $page_title       = 'Upload Zip';
            $page_slug        = strtolower( str_replace( ' ', '_', $page_title ) );

            add_submenu_page(  $parent_page, $page_title, $page_title, 'manage_options', $page_slug,  array( &$this,'upload_zip_form') );
    }

/**
 * Creates the Upload Box for the zip file. Checks for valid Zip file.
 *
 * For readability this function uses two other functions contained
 * in this class to add the posts and add the attachments.
 *
 * @since    1.0
 *
 * @param    string    $post_type_name    The post type to attach the page to
 * @return   none
 *
 * @uses     add_files_as_posts
 * @uses     add_files_as_attachment
 * @uses     list_files from Zip_All_Post_Attachments
 *
 * @todo     make accepted file mimetypes a parameter - currently hardcoded as pdf
 */
    public function upload_zip_form(){

        $post_type_name = $this->post_type_name;

        if ( !current_user_can( 'manage_options' ) )  {//check user can edit page
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        if(isset($_FILES['fupload'])) {

            $filename       = $_FILES['fupload']['name'];
            $source         = $_FILES['fupload']['tmp_name'];
            $type           = $_FILES['fupload']['type'];
            $name           = explode('.', $filename);
            $wp_upload_dir     = wp_upload_dir();
            $target         = $wp_upload_dir['path'].'/' . $name[0] . '-' . time() . '/';//dir to copy temp files into
            $accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/s-compressed');

            foreach($accepted_types as $mime_type) {
                if($mime_type == $type) {
                    $okay = true;
                    break;
                }
            }

            $okay = strtolower($name[1]) == 'zip' ? true: false;  //for chrome

            if(!$okay) {
                die("Please choose a zip file!");
            }

            mkdir($target);//make temp dir
            $saved_file_location = $target . $filename;

            if(move_uploaded_file($source, $saved_file_location)) {//move files to temp dir

                //READ THE FILE CONTENTS
                $open = zip_open($saved_file_location);
                if (is_numeric($open)) {
                     die("Zip Open Error #: $open");
                }
                else {

                    while ($zip = zip_read($open)) {
                        //store file name ready to link to posts
                        $newfile = basename(zip_entry_name($zip));//timed to stop duplicate overwrites
                         //check file type
                        $arr_file_type = wp_check_filetype($newfile, null);
                        $newfile_type = $arr_file_type['type'];

                            if (zip_entry_open($open, $zip, "r")) {
                              $buf = zip_entry_read($zip, zip_entry_filesize($zip));//get the bits

                              if (!empty($newfile_type)){//folders have no filetype, if entry is not a folder, add as file

                                  if(in_array($newfile_type, array('application/pdf','application/PDF'))){//check for pdf, must be after check for empty file (folders)

                                      $new_post_id = $this->add_files_as_posts($newfile);//take each filename and add as post
                                      $file_count ++;//count up for purposes of confirmation message
                                  }
                                  else{
                                      die("$newfile is not a valid filetype. $newfile_type");
                                  }
                              }

                              $this->add_files_as_attachment($newfile, $buf, $new_post_id);//Take each file, upload to folder and attach to post.
                              zip_entry_close($zip );
                            }

                    }

                    echo "<div id='message' class='updated below-h2'>";
                    echo "<p>$file_count New ". ucwords($post_type_name) ."'s Added. </p>";//echo nice wordpress confirmation
                    echo "</div>";
                    zip_close($open);
                    unlink($saved_file_location);//DELETE TEMP FILE

                    //compiles zip on server
                    $this->list_files();
                }

            } else {
              die("There was a problem. Sorry!");
            }
        rmdir($target);//remove temp dir
    }

    ?>
    <div class='wrap'>
    <div id="icon-edit" class="icon32"></div><h2>Upload Zip</h2>
    <table>
        <tr>
            <th></th>
            <form enctype="multipart/form-data" action="" method="post">
                <tr>
                    <td>Use this page to add a zip file of all your pdfs to the site</td>
                </tr>
                <td>
                    <input type="file" name="fupload" /><br />
                </td>
                <td>
                    <input type="submit" value="Upload Zip File" class="button-primary"/>
                </td>
            </form>
        </tr>
    </table>
    </div><!--wrap-->
    <?php
    }

/**
 * Takes a single file from an open zip and adds it to wordpress as a custom post type
 *
 * @since    1.0
 *
 * @param    string    $file           The name of the file to be attached. (part of an open zip file)
 * @return   int       $post_atten_id  The post_id of the new post, used by add_files_as_attachment to set parent_id
 *
 * @todo     make accepted file mimetypes a parameter - currently hardcoded as pdf
 */
    public function add_files_as_posts($file){

        $post_type_name = $this->post_type_name;

        if(! empty($file)){//check empty

            $file = substr($file, 0,strrpos($file,'.'));//remove file extension_loaded(name)
            $post_atten = array(
                  'comment_status' => 'closed',
                  'ping_status'    => 'closed' ,
                  'post_author'    => 1,
                  'post_date'      => date('Y-m-d H:i:s'),
                  'post_name'      => $file,
                  'post_status'    => 'publish' ,
                  'post_title'     => $file,
                  'post_type'      => $post_type_name,
                  'post_parent'    => $post_home_id,
            );
            //insert page and save the id
            $post_atten_id = wp_insert_post( $post_atten, false );
            return $post_atten_id;
      }
    }

/**
 * Takes a single file from an open zip and adds it to wordpress as a custom post type
 *
 * @since 1.0
 *
 * @param    string    $newfile       The (basename) of the file to be attached. Part of an open zip.
 * @param    string    $buf           The bits to be attached, generated by zip_entry_read.
 * @param    int       $new_post_id   The post to which the attachment will be linked (parent id).
 *
 * @return   none
 *
 * @todo     make accepted file mimetypes a parameter - currently hardcoded as pdf
 */
    public function add_files_as_attachment($newfile, $buf, $new_post_id){

      $wp_upload_dir     = wp_upload_dir();

      // Setup the array of supported file types. In this case, it's just PDF.
      $supported_types = array('application/pdf');

      // Get the file type of the upload
      $arr_file_type = wp_check_filetype(basename($newfile), null);
      $uploaded_type = $arr_file_type['type'];

      $uploaded_file = wp_upload_bits( $newfile, null, $buf );//add file to wordpress upload folder. returns server file location and url

      // Check if the type is supported. If not, throw an error.
      if(in_array($uploaded_type, $supported_types)) {

        //do post attachment
        $attachment = array(
       'guid'           => $uploaded_file['url'],
       'post_mime_type' => $arr_file_type['type'],
       'post_title'     => preg_replace('/\.[^.]+$/', '', basename($uploaded_file['file'])),
       'post_content'   => '',
       'post_status'    => 'inherit');

        $attach_id = wp_insert_attachment( $attachment, $uploaded_file['file'],$new_post_id );//wp_insert_attachment converts filename to correct string
        $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file'] );

        if(isset($attach_id['error']) && $attach_id['error'] != 0) {
            wp_die('There was an error uploading your file. The error is: ' . $attach_id['error']);
        } else {
          require_once(ABSPATH . 'wp-admin/includes/image.php');
          wp_update_attachment_metadata( $attach_id,  $attach_data );
        } // end if/else
      }
    }

}
?>