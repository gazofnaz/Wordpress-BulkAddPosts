<?php
/**
 * This file contains the class Add_Post_Upload_Box
 *
 * This class adds a custom meta box to the specified post type.
 * The box allows the user to attach a single file to the post.
 *
 * Attaching a new/second file to a post will overwrite the first
 * in both the media library, and the uploads folder.
 *
 * This is the desired behaviour for the Presentations upload software, as each post
 * will be used for a single presentation.
 *
 * @todo Make class work for post/pages, not just custom post type
 * @todo Make multiple file attachments per post an option?
 * @todo Make all the function and html ID's dynamic.
 *
 * @package WordPress
 */

class Add_Post_Upload_Box extends Zip_All_Post_Attachments {

  public $post_type_name;

  public function __construct( $parent ) {//called when object is created
      parent::__construct($parent);
      $this->post_type_name = strtolower( str_replace( ' ', '_', $parent ) );
      add_action('save_post',array(&$this,'save_custom_meta_data'));//saves the uploaded pdf file
      add_action('post_edit_form_tag', array(&$this,'update_edit_form'));//allow files to be saved with post
      add_action('add_meta_boxes', array(&$this,'add_pres_custom_meta_boxes'));//add custom meta box to presentaion page
  }

/**
 * This hooks into save_post and attaches the users file to the post in question.
 *
 * Lots of checks are required to ensure the post is saved correctly, and not
 * saved twice as a result of autosaves and revisions.
 *
 * @since   1.0
 *
 * @param   int    $id    The post id in question
 * @return  none
 * @uses    remove_existing_attachments()
 * @uses    list_files from Zip_All_Post_Attachments
 *
 * @todo    allow parent page to optionally be set as post/page, not just custom post type.
 * @todo    allow supported type to be set dynamically.
 */

  public function save_custom_meta_data($id) {//Saves the uploaded file

      $post_type_name = $this->post_type_name;

      $filename=$_FILES['wp_presentation_file']['name'];
      $wp_upload_dir = wp_upload_dir();
      global $post;

      if ( is_int( wp_is_post_revision( $id ) ) )
          return;

      if( is_int( wp_is_post_autosave( $id ) ) )
          return;
      // --- security verification ---
      if(!wp_verify_nonce($_POST['wp_presentation_file_nonce'], plugin_basename(__FILE__))) {
          return $id;
      } // end if

      //IF AUTOSAVE, DO NOTHING
      if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
          return $id;
      } // end if

      //CHECK USER CAN EDIT PAGE
      if('page' == $_POST['post_type']) {
          if(!current_user_can('edit_page', $id)) {
              return $id;
          } // end if
      } else {
          if(!current_user_can('edit_page', $id)) {
              return $id;
          } // end if
      } // end if
      // - end security verification -

      // Make sure the file array isn't empty
      if(!empty($_FILES['wp_presentation_file']['name'])) {

          // Setup the array of supported file types. In this case, it's just PDF.
          $supported_types = array('application/pdf');

          // Get the file type of the upload
          $arr_file_type = wp_check_filetype(basename($filename), null);
          $uploaded_type = $arr_file_type['type'];

          // Check if the type is supported. If not, throw an error.
          if(in_array($uploaded_type, $supported_types)) {

              $this->remove_existing_attachments();//remove attachment, only allow one per post

              // Use the WordPress API to upload the file to uploads dir
              //result of function must be passed to insert_attachment to ensure files are not overwritten in media library
              $uploaded_file = wp_upload_bits($_FILES['wp_presentation_file']['name'], null, file_get_contents($_FILES['wp_presentation_file']['tmp_name']));

              //do post attachment
              $attachment = array(
             'guid' => $uploaded_file['url'],
             'post_mime_type' => $arr_file_type['type'],
             'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded_file['file'])),
             'post_content' => '',
             'post_status' => 'inherit');

              $attach_id = wp_insert_attachment( $attachment, $uploaded_file['file'],$post->ID);//wp_insert_attachment converts filename to correct string
              $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);

              //compiles zip on server
              $this->list_files();

              if(isset($attach_id['error']) && $attach_id['error'] != 0) {
                  wp_die('There was an error uploading your file. The error is: ' . $attach_id['error']);
              } else {
                  require_once(ABSPATH . 'wp-admin/includes/image.php');
                  wp_update_attachment_metadata($attach_id,  $attach_data);
              } // end if/else

          } else {
              wp_die("The file type that you've uploaded is not a PDF.");
          } // end if/else

      } // end if

  } // end save_custom_meta_data

/**
 * Remove any existing attachment on save
 *
 * As only one file per post is required, we need to remove any others on save.
 *
 * @since   1.0
 *
 * @param   none
 * @return  none
 *
 * @todo    Remove global post, pass the id from the function above.
 */

  public function remove_existing_attachments() {//Removes Attchment for current post id
      global $post;
      $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $post->ID );
      //Get current attachment
      $attachments = get_posts($args);
      if ($attachments) {
          foreach ( $attachments as $attachment ) {
              wp_delete_attachment($attachment->ID);
          }
      }
  }//end remove_existing_attachment

/**
 * By default wordpress does not allow files to be submitted by form.
 * This enables all files types to be attached to the edit post page.
 */
  public function update_edit_form() {//allow post save attachment file types
      echo ' enctype="multipart/form-data"';
  } // end update_edit_form

/**
 * Add the meta box to the post
 *
 * @since 1.0
 *
 * @param   none
 * @return  none
 *
 * @todo  Make post type declarable on in class instantiation
 * @todo  Make title declarable
 * @todo  Make this inherit from custom_post_type
 */
  public function add_pres_custom_meta_boxes() { //sets up the new meta box

    add_meta_box(
          'wp_presentation_metabox', // $id
           __('Presentation File'),// $title
          array(&$this,'show_upload_metabox'),// $callback
          'presentation',// $page
          'normal'// $context
      );

  }//end add_pres_custom_meta_boxes

/**
 * Front end code for the meta box.
 *
 * Uses get_posts to show the current file attachment, and provides a link to it
 *
 * @since   1.0
 *
 * @param   none
 * @return  none
 *
 * @todo    Make "presentation" dynamic, to be used for other post types.
 */

  public function show_upload_metabox() {//outputs the html for the new meta box

      global $post;

      wp_nonce_field(plugin_basename(__FILE__), 'wp_presentation_file_nonce');
      $html  = '<p class="description">';
      $html .= 'Upload your PDF here.';
      $html .= '';
      $html .= '<input type="file" id="wp_presentation_file" name="wp_presentation_file" value="" size="25">';
      echo $html;

      $args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $post->ID );

      //Display current attachment
      $attachments = get_posts($args);
      if ($attachments) {
          foreach ( $attachments as $attachment ) {

              if (get_attached_file($attachment->ID) != ''){//Check file is returned correctly.
                  $filesize = size_format(filesize(get_attached_file($attachment->ID)));
              }

              if (wp_get_attachment_url($attachment->ID) != ''){//Check url is returned correctly.
                  $fileurl = wp_get_attachment_url($attachment->ID);
              }

              echo "<br/>Current Attachment: ";
              echo "<a href='".$fileurl."'>";
              echo "<strong>".basename($fileurl)."</strong>";
              echo "</a>";
              echo " ".$filesize;
          }
      }
      else{//no attachments
          echo "<br/>No file attached";
      }
    }// end show_upload_metabox

}//end class
?>