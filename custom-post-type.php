<?php

/**
 * This file contains the class Custom_Post_Type
 *
 * A custom new post type can be declared by simply
 * entering the name of the post.
 *
 * From there users can add custom taxonomies (as tags or categories).
 * Default entries can also be added to the taxonomies.
 * Custom meta boxes can be added to posts (text only)
 * Custom submenu pages can be added to the post type. (Function taken as parameter)
 *
 * @todo Check custom submenu page is actually working.
 *
 * @package WordPress
 */

class Custom_Post_Type
{
    public $post_type_name;
    public $post_type_args;
    public $post_type_labels;
    public $post_type_pos;

    /* Class constructor */
    public function __construct($name, $pos = 5, $args = array(), $labels = array() )
    {
        $this->post_type_name   = strtolower( str_replace( ' ', '_', $name ) );  //name of the post type
        $this->post_type_pos = $pos; //position in menu
        $this->post_type_args   = $args;  //arguments for post type
        $this->post_type_labels = $labels;  //labels for post type

        // Add action to register the post type, if the post type does not already exist
        if( ! post_type_exists( $this->post_type_name ) ){
            add_action( 'init', array( &$this, 'register_post_type' ) );
            add_filter('post_updated_messages', array( &$this,'post_type_updated_messages'));
        }

        // Listen for the save post hook
        $this->save();
    }
/**
 * Method which registers the post type
 *
 * It converts the name to plural to make things prettier.
 *
 * @since   1.0
 *
 * @param   string    $post_type_name    The post type name
 * @param   int       $post_type_pos    The position the post type appears in the menu http://codex.wordpress.org/Function_Reference/add_menu_page#Parameters
 * @return  none
 *
 */
    public function register_post_type(){
        //Capitilize the words and make it plural
        $name    =      ucwords( str_replace( '_', ' ', $this->post_type_name ) );
        $plural  =      $name . 's';
        $menupos =      $this->post_type_pos;

        // We set the default labels based on the post type name and plural. We overwrite them with the given labels.
        $labels = array_merge(

            // Default
            array(
                'name'                  => _x( $plural, 'post type general name' ),
                'singular_name'         => _x( $name, 'post type singular name' ),
                'add_new'               => _x( 'Add New', strtolower( $name ) ),
                'add_new_item'          => __( 'Add New ' . $name ),
                'edit_item'             => __( 'Edit ' . $name ),
                'new_item'              => __( 'New ' . $name ),
                'all_items'             => __( 'All ' . $plural ),
                'view_item'             => __( 'View ' . $name ),
                'search_items'          => __( 'Search ' . $plural ),
                'not_found'             => __( 'No ' . strtolower( $plural ) . ' found'),
                'not_found_in_trash'    => __( 'No ' . strtolower( $plural ) . ' found in Trash'),
                'parent_item_colon'     => '',
                'menu_name'             => $plural
            ),

            // Given labels
            $this->post_type_labels

        );

        // Same principle as the labels. We set some defaults and overwrite them with the given arguments.
        $args = array_merge(

            // Default
            array(
                'capability_type'    => 'post',
                'hierarchical'       => false,
                'label'              => $plural,
                'labels'             => $labels,
                'menu_position'      => $menupos,
                'public'             => true,
                'publicly_queryable' => true,
                'query_var'          => true,
                'rewrite'            => array('slug' => $plural),
                'show_in_nav_menus'  => true,
                'show_ui'            => true,
                'supports'           => array( 'title', 'editor'),
                '_builtin'           => false,
            ),

            // Given args
            $this->post_type_args

        );

        // Register the post type
        register_post_type( $this->post_type_name, $args );
    }

/**
 * Method which adds "Presentations Saved" and "Presentations Updated",
 * instead of "Post Saved" messages.
 *
 * @since   1.0
 *
 * @return  $messages  An array of messages with the $name added
 *
 */
    public function post_type_updated_messages( $messages ) {
        global $post, $post_ID;

        $name    =      ucwords( str_replace( '_', ' ', $this->post_type_name ) );
        $plural  =      $name . 's';

        $messages[$this->post_type_name] = array(
                0  => '', // Unused. Messages start at index 1.
                1  => sprintf( __("$name updated. <a href='%s'>View $name</a>"), esc_url( get_permalink($post_ID) ) ),
                2  => __("Custom field updated."),
                3  => __("Custom field deleted."),
                4  => __("$name updated."),
                // translators: %s: date and time of the revision
                5  => isset($_GET['revision']) ? sprintf( __("$name restored to revision from %s"), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
                6  => sprintf( __("$name published. <a href='%s'>View $name</a>"), esc_url( get_permalink($post_ID) ) ),
                7  => __("$name saved."),
                8  => sprintf( __("$name submitted. <a target='_blank' href='%s'>Preview book</a>"), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
                9  => sprintf( __("$name scheduled for: <strong>%1$s</strong>. <a target='_blank' href='%2$s'>Preview book</a>"),
                // translators: Publish box date format, see php.net/date
                date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
                10 => sprintf( __("$name draft updated. <a target='_blank' href='%s'>Preview book</a>"), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
        );

        return $messages;
    }

/**
 * Method to attach the taxonomy to the post type
 *
 * @since   1.0
 *
 * @param   string    $name          The post type name (i.e. parent post type)
 * @param   bool      $hierarchy     Whether taxonomy is like categories (TRUE) or tags (FALSE)
 * @return  none
 *
 */
    public function add_taxonomy( $name, $hierarchy, $args = array(), $labels = array() ){
       if( ! empty( $name ) ){
            // We need to know the post type name, so the new taxonomy can be attached to it.
            $post_type_name = $this->post_type_name;

            // Taxonomy properties
            $taxonomy_name      = strtolower( str_replace( ' ', '_', $name ) );
            $taxonomy_labels    = $labels;
            $taxonomy_args      = $args;

            if( ! taxonomy_exists( $taxonomy_name ) ){
                //Capitilize the words and make it plural
                $name       = ucwords( str_replace( '_', ' ', $name ) );
                $plural     = $name . 's';

                // Default labels, overwrite them with the given labels.
                $labels = array_merge(

                    // Default
                    array(
                        'name'                  => _x( $plural, 'taxonomy general name' ),
                        'singular_name'         => _x( $name, 'taxonomy singular name' ),
                        'search_items'          => __( 'Search ' . $plural ),
                        'all_items'             => __( 'All ' . $plural ),
                        'parent_item'           => __( 'Parent ' . $name ),
                        'parent_item_colon'     => __( 'Parent ' . $name . ':' ),
                        'edit_item'             => __( 'Edit ' . $name ),
                        'update_item'           => __( 'Update ' . $name ),
                        'add_new_item'          => __( 'Add New ' . $name ),
                        'new_item_name'         => __( 'New ' . $name . ' Name' ),
                        'menu_name'             => __( $plural ),
                    ),

                    // Given labels
                    $taxonomy_labels

                );

                // Default arguments, overwritten with the given arguments
                $args = array_merge(

                    // Default
                    array(
                        'hierarchical'      => $hierarchy,
                        'label'             => $plural,
                        'labels'            => $labels,
                        'public'            => true,
                        'show_ui'           => true,
                        'show_in_nav_menus' => true,
                        '_builtin'          => false,
                    ),

                    // Given
                    $taxonomy_args

                );

                // Add the taxonomy to the post type
                add_action( 'init',
                    function() use( $taxonomy_name, $post_type_name, $args ){
                        register_taxonomy( $taxonomy_name, $post_type_name, $args );
                    }
                );
            }
            else{
                add_action( 'init',
                    function() use( $taxonomy_name, $post_type_name ){
                        register_taxonomy_for_object_type( $taxonomy_name, $post_type_name );
                    }
                );
            }
        }
    }

/**
 * Method to add default taxonomies to the post type
 * Note - these are called on admin_init - so once
 * created they are un-deleteable.
 *
 * @since   1.0
 *
 * @param   string    $term         The term you would like to add.
 * @param   string    $taxonomy     Name of the taxonomy to attach the default term to
 * @return  none
 *
 * @todo    add support for parent/child terms.
 */
    public function add_default_taxonomy( $term, $taxonomy, $args = array() ){
        if( ! empty( $taxonomy ) ){

            $post_type_name = $this->post_type_name;

            $name    =      ucwords( str_replace( '_', ' ', $this->post_type_name ) );
            $plural  =      $name . 's';

            $category_term     = $term;
            $category_slug     = sanitize_title($term);
            $category_taxonomy = strtolower( str_replace( ' ', '_', $taxonomy ) );
            $category_args     = $args;

            $args = array_merge(

                // Default
                array(
                'description'=> 'The '. $term . ' ' .$plural,
                'slug' => $category_slug
                ),

                // Given
                $category_args

            );

            if(!term_exists($category_term, $category_taxonomy)){//check the term hasnt been set up already
                add_action( 'admin_init',
                function() use( $category_term, $category_taxonomy, $args ){
                            wp_insert_term( $category_term, $category_taxonomy, $args );
                        }
                );
            }
        }

    }//end add_default_taxonomy

/**
 * Attaches meta boxes to the post type
 *
 * @since   1.0
 *
 * @param   string         $title      The title of the box surrounding the form field meta boxes
 * @param   array/string   $fields     The lable for the actual form field meta boxes.
 * @param   string         $context    The box can appear 'normal', 'advanced', or 'side'
 * @param   string         $priority   The box may be 'high', 'core', 'default' or 'low'
 *
 * @return  none
 */
    public function add_meta_box($title, $fields = array(), $context = 'normal', $priority = 'default'){
        if( ! empty( $title ) ){
            // We need to know the Post Type name again
            $post_type_name = $this->post_type_name;

            // Meta variables
            $box_id         = strtolower( str_replace( ' ', '_', $title ) );
            $box_title      = ucwords( str_replace( '_', ' ', $title ) );
            $box_context    = $context;
            $box_priority   = $priority;

            // Make the fields global
            global $custom_fields;
            $custom_fields[$title] = $fields;

            add_action( 'admin_init',
                function() use( $box_id, $box_title, $post_type_name, $box_context, $box_priority, $fields ){
                    add_meta_box(
                        $box_id,
                        $box_title,
                        function( $post, $data ){
                            global $post;

                            // Nonce field for some validation
                            wp_nonce_field( plugin_basename( __FILE__ ), 'custom_post_type' );

                            // Get all inputs from $data
                            $custom_fields = $data['args'][0];

                            // Get the saved values
                            $meta = get_post_custom( $post->ID );

                            // Check the array and loop through it
                            if( ! empty( $custom_fields ) ){
                                /* Loop through $custom_fields */

                                echo '<table>';//start table before loop

                                foreach( $custom_fields as $label => $type ){

                                    $field_id_name  = strtolower( str_replace( ' ', '_', $data['id'] ) ) . '_' . strtolower( str_replace( ' ', '_', $label ) );
                                    echo '<tr><td><label for="' . $field_id_name . '">' . $label . '</label></td><td><input type="text" name="custom_meta[' . $field_id_name . ']" id="' . $field_id_name . '" value="' . $meta[$field_id_name][0] . '" /></td>';
                                }

                                echo '</table>';//end table after loop
                            }

                        },
                        $post_type_name,
                        $box_context,
                        $box_priority,
                        array( $fields )
                    );
                }
            );
        }
    }//end add_meta_box

/**
 * Listens for when the post type being saved
 *
 * @since   1.0
 *
 * @param   none
 *
 * @todo    make private?
 */
    public function save(){
        // Need the post type name again
        $post_type_name = $this->post_type_name;

        add_action( 'save_post',
            function() use( $post_type_name ){
                // Deny the WordPress autosave function
                if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

                if ( ! wp_verify_nonce( $_POST['custom_post_type'], plugin_basename(__FILE__) ) ) return;

                global $post;

                if( isset( $_POST ) && isset( $post->ID ) && get_post_type( $post->ID ) == $post_type_name ){
                    global $custom_fields;

                    // Loop through each meta box
                    foreach( $custom_fields as $title => $fields ){
                        // Loop through all fields
                        foreach( $fields as $label => $type ){
                            $field_id_name  = strtolower( str_replace( ' ', '_', $title ) ) . '_' . strtolower( str_replace( ' ', '_', $label ) );
                            if($_POST['custom_meta'][$field_id_name] != ''){//Check Entry is not empty
                                update_post_meta( $post->ID, $field_id_name, $_POST['custom_meta'][$field_id_name] );
                            }
                        }

                    }
                }
            }
        );
    }//end save

/**
 * Creates a new submenu page for the post type
 *
 * @since    1.0
 *
 * @param    string         $title      The title of new page
 * @param    string         $function   The name of the function executed on the page.
 *
 * @return   none
 *
 * @todo     check this actually works.
 */
    public function add_new_admin_page($title,$function){//Takes page title as ref. parent is assumed as post above.

        if( ! empty( $title) ){
            // We need to know the Post Type name again
            $post_type_name = $this->post_type_name;
            $parent_page    = 'edit.php?post_type='.$post_type_name;
            $page_title     = $title;
            $page_slug      = strtolower( str_replace( ' ', '_', $page_title ) );
            $page_function  = $function;


        add_action( 'admin_menu',
                function() use( $parent_page, $page_title, $page_slug, $page_function ){
                             add_submenu_page(  $parent_page, $page_title, $page_title, 'manage_options', $page_slug, $page_function );
                        }
                );

        }
    }//end add_new_admin_page
}

?>