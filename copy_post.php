<?php

define( 'WPMUDEV_DEV_DIR', '/vagrant/www/wordpress-wpmudev/wp-content/plugins/' );
  
class MCC_Copy_CPT extends WP_UnitTestCase {  

    protected $plugin;  
    protected $orig_blog_id;  
    protected $dest_blog_id; 
    protected $copier;
    protected $images_array;
    protected $base_dir;
    protected $new_post_id;

    static $i = 0;
  
    function setUp() {  
          
        parent::setUp(); 

        if ( ! file_exists( WPMUDEV_DEV_DIR . 'multisite-content-copier/multisite-content-copier.php' ) ) {
            $this->markTestSkipped( 'MCC plugin is not installed.' );
        }

        include_once WPMUDEV_DEV_DIR . 'multisite-content-copier/multisite-content-copier.php';

        global $multisite_content_copier_plugin;
        $this->plugin = $multisite_content_copier_plugin; 
        $this->plugin->include_copier_classes();

        self::$i = self::$i + 1;
        if ( self::$i != 6 ) {
            $this->markTestSkipped( 'MCC plugin is not installed.' );
        }

        $this->setup_initial_data();




    } // end setup  
      
    

    function tearDown() {
        parent::tearDown();

        $files = glob( $this->base_dir . '/*');
        foreach ( $files as $image ) {
           unlink( $image );
        }

        $files = glob( $this->dest_base_dir . '/*');
        foreach ( $files as $image ) {
           unlink( $image );
        }
    }


    function test_copy_post() {
        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'post_type' => 'book',
            'copy_images' => false,
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $new_post_id = $copier->copy_item( $this->orig_post_id );

        $this->assertTrue( is_integer( $new_post_id ) && $new_post_id > 0 );

        $new_post = get_post( $new_post_id );
        $this->assertEquals( $new_post->post_name, 'book-child' );

        restore_current_blog();
    }

    function test_copy_post_and_parent() {

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'post_type' => 'book',
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => true,
            'copy_comments' => false
        );

        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $results = $copier->execute();

        $this->assertTrue( isset( $results[ $this->orig_post_id ] ) );
        $dest_post = get_post( $results[ $this->orig_post_id ] );
        $this->assertEquals( $orig_post->post_name, $dest_post->post_name );

        $dest_post_parent_id = wp_get_post_parent_id( $dest_post->ID );
        $this->assertTrue( isset( $results[ $this->orig_parent_post_id ] ) );
        $this->assertEquals( $results[ $this->orig_parent_post_id ], $dest_post_parent_id );        
      
        restore_current_blog();
      
    }

    function test_copy_post_update_date() {

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'post_type' => 'book',
            'keep_user' => true,
            'update_date' => true,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_post_id ), $args );

        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );

        $results = $copier->execute();

        $this->assertTrue( isset( $results[ $this->orig_post_id ] ) );

        $new_post = get_post( $results[ $this->orig_post_id ] );
        $this->assertGreaterThan( $orig_post->post_date, $new_post->post_date );
        restore_current_blog();

    }

    function test_copy_post_and_comments() {

        switch_to_blog( $this->orig_blog_id );
        $orig_comments_no = count( get_comments( array( 'post_id' => $this->orig_post_id ) ) );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'post_type' => 'book',
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => true
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_post_id ), $args );

        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );

        $results = $copier->execute();

        $new_comments = get_comments( array( 'post_id' => $results[ $this->orig_post_id ] ) );
        $this->assertEquals( count( $new_comments ), $orig_comments_no );

        foreach ( $new_comments as $comment ) {
            if ( $comment->comment_content == 'child comment' ) {
                $meta_value = get_comment_meta( $comment->comment_ID, 'a_meta_key', true );
                $this->assertEquals( 'meta_value', $meta_value );
            }
        }
        restore_current_blog();

    }

    function test_get_all_media() {

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => true,
            'post_type' => 'book',
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );
        $images = $copier->get_all_media_in_post( $this->orig_post_id );

        $attachments = array();
        foreach( $images['attachments'] as $attachment ) {
            $attachments[] = $attachment->post_title;
        }

        $this->assertContains( 'solo-cabeza2.png', $attachments, 'solo_cabeza2.png was not found in attachments' );
        $this->assertNotEmpty( $this->file_exists( 'solo-cabeza2.png' ), "solo-cabeza2.png file was not found in $this->base_dir" );

        $this->assertNOTContains( 'placekitten.com/150/150', $attachments, 'placekitten.com/150/150 was not found in attachments' );

        $no_attachments = array();
        foreach( $images['no_attachments'] as $no_attachment ) {
            $no_attachments[] = $no_attachment['name'];
        }

        $this->assertContains( 'fondos-paisajes-1024-7', $no_attachments, 'fondos-paisajes-1024-7 was not found in no-attachments' );
        $this->assertNotEmpty( $this->file_exists( 'fondos-paisajes-1024-7' ), "fondos-paisajes-1024-7 file was not found in $this->base_dir" );

        $this->assertContains( 'IMG_2301-768x1024', $no_attachments, 'IMG_2301-768x1024 was not found in no-attachments' );
        $this->assertNotEmpty( $this->file_exists( 'IMG_2301-768x1024' ), "IMG_2301-768x1024 file was not found in $this->base_dir" );

        $this->assertNotContains( 'logo9w.png', $no_attachments, 'logo9w.png was found in no-attachments' );
        $this->assertEmpty( $this->file_exists( 'logo9w.png' ), "logo9w.png file was found in $this->base_dir. WHY???" );

        restore_current_blog();

    }

    function file_exists( $filename ) {
        return glob( $this->base_dir . '/' . $filename . '*' );
    }

    function test_copy_post_and_media() {
        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'copy_images' => true,
            'post_type' => 'book',
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );

        $new_post_id = $copier->copy_post( $this->orig_post_id );

        $post = get_post( $new_post_id );
        // Post created ??
        $this->assertTrue( ! empty( $post ) );

        $copier->copy_media( $this->orig_post_id, $new_post_id );

        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );
        $orig_author = $orig_post->post_author;
        $orig_date = $orig_post->post_date;

        $post = get_post( $new_post_id );

        $upload_dir = wp_upload_dir();
        
        $this->assertContains( 
            $upload_dir['url'] . '/solo-cabeza2.png',
            $post->post_content
        );

        $this->assertContains( 
            $upload_dir['url'] . '/IMG_2301-768x1024-150x150.jpg',
            $post->post_content
        );

        $this->assertContains( 
            $upload_dir['url'] . '/fondos-paisajes-1024-7-300x158.jpg',
            $post->post_content
        );


        $this->assertContains( 
            '<img src="http://www.google.es/images/srpr/logo9w.png" />', 
            $post->post_content
        );

        $post_thumbnail_id = get_post_thumbnail_id( $new_post_id );
        $thumbnail = get_post( $post_thumbnail_id );

        $this->assertContains( 'thumbnail', $thumbnail->guid );

        $this->assertTrue( $post->post_author == $orig_post->post_author );
        $this->assertTrue( $post->post_date == $orig_post->post_date );

        restore_current_blog();
    }

    function test_copy_terms() {
        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'post_type' => 'book',
            'copy_images' => true,
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false,
            'copy_terms' => true
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );

        $new_post_id = $copier->copy_item( $this->orig_post_id );

        $terms = wp_get_object_terms( $new_post_id, array( 'genre', 'writer' ), array( 'fields' => 'all' ) );

        $this->assertNotEmpty( $terms );
        foreach ( $terms as $term ) {
            if ( $term->taxonomy == 'genre' )
                $this->assertTrue( in_array( $term->name, array( 'A genre', 'Another genre' ) ) );
            if ( $term->taxonomy == 'writer' )
                $this->assertTrue( in_array( $term->name, array( 'A writer', 'Another writer' ) ) );
        }

        restore_current_blog();
    }

    function test_copy_meta() {
        switch_to_blog( $this->orig_blog_id );
        $meta_value_array = array(
            'this'  => 'is',
            'an'    => 'array'
        );
        update_post_meta( $this->orig_post_id, 'meta_array', $meta_value_array );

        $meta_value_string = 'this is an array';
        update_post_meta( $this->orig_post_id, 'meta_string', $meta_value_string );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'copy_images' => false,
            'post_type' => 'book',
            'keep_user' => false,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = Multisite_Content_Copier_Factory::get_copier( 'post', $this->orig_blog_id, array( $this->orig_blog_id ), $args );

        $new_post_id = $copier->copy_post( $this->orig_post_id );

        $meta = get_post_meta( $new_post_id, 'meta_array', true );
        $this->assertEquals( $meta_value_array, $meta );

        $meta = get_post_meta( $new_post_id, 'meta_string', true );
        $this->assertEquals( $meta_value_string, $meta );
        
        restore_current_blog();
    }

    function setup_initial_data() {

        $this->orig_blog_id = $this->factory->blog->create_object( $this->factory->blog->generate_args() );
        $this->dest_blog_id = $this->factory->blog->create_object( $this->factory->blog->generate_args() );
        $this->current_time = '2013/10';

        if ( $this->orig_blog_id == 1 )
            $this->orig_upload_subfolder = $this->current_time . '/';
        else
            $this->orig_upload_subfolder = 'sites/' . $this->orig_blog_id . '/' . $this->current_time . '/';

        switch_to_blog( $this->dest_blog_id );
        $upload_dir = wp_upload_dir();
        $this->dest_upload_subfolder = 'sites/' . $this->dest_blog_id . $upload_dir['subdir'] . '/';
        $this->dest_base_dir = $upload_dir['path'] . '/';
        restore_current_blog();

        switch_to_blog( $this->orig_blog_id );

        $labels = array(
            'name'               => 'Books',
            'singular_name'      => 'Book',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Book',
            'edit_item'          => 'Edit Book',
            'new_item'           => 'New Book',
            'all_items'          => 'All Books',
            'view_item'          => 'View Book',
            'search_items'       => 'Search Books',
            'not_found'          => 'No books found',
            'not_found_in_trash' => 'No books found in Trash',
            'parent_item_colon'  => '',
            'menu_name'          => 'Books'
          );

          $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'book' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
          );        
        register_post_type( 'book', $args );

        // Taxonomies
        $labels = array(
            'name'              => _x( 'Genres', 'taxonomy general name' ),
            'singular_name'     => _x( 'Genre', 'taxonomy singular name' ),
            'search_items'      => __( 'Search Genres' ),
            'all_items'         => __( 'All Genres' ),
            'parent_item'       => __( 'Parent Genre' ),
            'parent_item_colon' => __( 'Parent Genre:' ),
            'edit_item'         => __( 'Edit Genre' ),
            'update_item'       => __( 'Update Genre' ),
            'add_new_item'      => __( 'Add New Genre' ),
            'new_item_name'     => __( 'New Genre Name' ),
            'menu_name'         => __( 'Genre' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'genre' ),
        );

        register_taxonomy( 'genre', array( 'book' ), $args );

        $labels = array(
            'name'                       => _x( 'Writers', 'taxonomy general name' ),
            'singular_name'              => _x( 'Writer', 'taxonomy singular name' ),
            'search_items'               => __( 'Search Writers' ),
            'popular_items'              => __( 'Popular Writers' ),
            'all_items'                  => __( 'All Writers' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Writer' ),
            'update_item'                => __( 'Update Writer' ),
            'add_new_item'               => __( 'Add New Writer' ),
            'new_item_name'              => __( 'New Writer Name' ),
            'separate_items_with_commas' => __( 'Separate writers with commas' ),
            'add_or_remove_items'        => __( 'Add or remove writers' ),
            'choose_from_most_used'      => __( 'Choose from the most used writers' ),
            'not_found'                  => __( 'No writers found.' ),
            'menu_name'                  => __( 'Writers' ),
        );

        $args = array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'writer' ),
        );

        register_taxonomy( 'writer', 'book', $args );


        $post_content = 'a_content';

        $this->orig_parent_post_id = $this->factory->post->create_object( array(
            'post_content' => $post_content,
            'post_type' => 'book',
            'post_name' => 'book-parent',
            'post_date' => '2013-09-25 00:00:00'
        ) );

        

        $this->orig_post_id = $this->factory->post->create_object( array(
            'post_content' => 'content',
            'post_type' => 'book',
            'post_parent' => $this->orig_parent_post_id,
            'post_name' => 'book-child',
            'post_date' => '2013-09-25 00:00:00'
        ) );


        $term1 = wp_insert_term( 'A genre', 'category' );
        $term2 = wp_insert_term( 'Another genre', 'category' );
        $terms = array( $term1['term_id'], $term2['term_id'] );
        wp_set_object_terms( $this->orig_post_id, $terms, 'genre' );

        $tag1 = wp_insert_term( 'A writer', 'writer' );
        $tag2 = wp_insert_term( 'Another writer', 'writer' );
        $tags = array( $tag1['term_id'], $tag2['term_id'] );
        wp_set_object_terms( $this->orig_post_id, $tags, 'writer' );


        // Copying images to the first upload folder
        $this->images_array = array(
            array( 'filename' => 'fondos-paisajes-1024-7.jpg', 'post_mime_type' => 'image/jpg', 'post_parent' => $this->orig_post_id, 'thumbnail' => false ),
            array( 'filename' => 'IMG_2301-768x1024.jpg', 'post_mime_type' => 'image/jpg', 'post_parent' => 0, 'thumbnail' => false ),
            array( 'filename' => 'solo-cabeza2.png', 'post_mime_type' => 'image/png', 'post_parent' => $this->orig_post_id, 'thumbnail' => false ),
            array( 'filename' => 'thumbnail.jpg', 'post_mime_type' => 'image/jpg', 'post_parent' => false, 'thumbnail' => true )
        );

        $upload_dir = wp_upload_dir( $this->current_time );

        $current_dir = dirname( __FILE__ );
        $this->base_dir = $upload_dir['path'] . '/';

        foreach( $this->images_array as $image ) {
            copy( $current_dir . '/images/' . $image['filename'], $this->base_dir . $image['filename'] );
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $this->post_content_images = array();
        $post_content = '';
        foreach ( $this->images_array as $image ) {
            $attachment_id = $this->factory->attachment->create_object( $image['filename'], 0, array(
                'post_mime_type' => $image['post_mime_type'],
                'post_type' => 'attachment',
                'post_title' => $image['filename'],
                'post_parent' => $image['post_parent'],
                'guid' => $upload_dir['url'] . '/' . basename( $upload_dir['path'] . "/" . $image['filename'] )
            ) );

            $metadata = wp_generate_attachment_metadata( $attachment_id, $this->base_dir . $image['filename'] );
            wp_update_attachment_metadata( $attachment_id, $metadata );

            $attachment_file_meta = get_post_meta( $attachment_id, '_wp_attached_file' );
            $new_attachment_file_meta = ltrim( $upload_dir['subdir'], '/' ) . '/' . $image['filename'];
            update_post_meta( $attachment_id, '_wp_attached_file', $new_attachment_file_meta );

            if ( $image['thumbnail'] )
                set_post_thumbnail( $this->orig_post_id, $attachment_id );

            $content = '';
            switch ( $image['filename'] ) {
                case 'solo-cabeza2.png':  {
                    $content_image = wp_get_attachment_image_src( $attachment_id, 'full' );
                    $content =  '<a href="' . $content_image[0] . '"><img class="alignnone size-full wp-image-274" alt="solo-cabeza" src="' . $content_image[0] . '" width="1555" height="767" /></a>';
                    $this->post_content_images[] = $content_image;
                    break;
                }
                case 'fondos-paisajes-1024-7.jpg':  {
                    $content_image = wp_get_attachment_image_src( $attachment_id, 'medium' );
                    $_content_image = wp_get_attachment_image_src( $attachment_id, 'full' );
                    $content =  '<a href="' . $_content_image[0] . '"><img class="alignnone size-medium wp-image-271" alt="fondos-paisajes-1024 (7)" src="' . $content_image[0] .'" width="300" height="158" /></a>';
                    $this->post_content_images[] = $content_image;
                    break;
                }
                case 'IMG_2301-768x1024.jpg':  {
                    $content_image = wp_get_attachment_image_src( $attachment_id );
                    $_content_image = wp_get_attachment_image_src( $attachment_id, 'full' );
                    $content =  '<a href="' . $_content_image[0] . '"><img class="alignnone size-thumbnail wp-image-275" alt="IMG_2301-768x1024" src="' . $content_image[0] .'" width="150" height="150" /></a>';
                    $this->post_content_images[] = $content_image;
                    break;
                }
            }
            $post_content .= $content;
            
        }

        $post_content .= '<img src="http://www.google.es/images/srpr/logo9w.png" />';

        $this->factory->post->update_object( $this->orig_post_id, array( 'post_content' => $post_content ) );

        $time = current_time('mysql');

        // One comment for the parent
        $data = array(
            'comment_post_ID' => $this->orig_parent_post_id,
            'comment_author' => 'admin',
            'comment_author_email' => 'admin@admin.com',
            'comment_author_url' => 'http://',
            'comment_content' => 'parent post parent comment',
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => 1,
            'comment_author_IP' => '127.0.0.1',
            'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
            'comment_date' => $time,
            'comment_approved' => 1,
        );
        $comment_id = wp_insert_comment( $data );

        // Two for the other
        $data = array(
            'comment_post_ID' => $this->orig_post_id,
            'comment_author' => 'admin',
            'comment_author_email' => 'admin@admin.com',
            'comment_author_url' => 'http://',
            'comment_content' => 'parent comment',
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => 1,
            'comment_author_IP' => '127.0.0.1',
            'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
            'comment_date' => $time,
            'comment_approved' => 1,
        );
        $comment_id = wp_insert_comment( $data );

        $data = array(
            'comment_post_ID' => $this->orig_post_id,
            'comment_author' => 'admin',
            'comment_author_email' => 'admin@admin.com',
            'comment_author_url' => 'http://',
            'comment_content' => 'child comment',
            'comment_type' => '',
            'comment_parent' => $comment_id,
            'user_id' => 1,
            'comment_author_IP' => '127.0.0.1',
            'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
            'comment_date' => $time,
            'comment_approved' => 1,
        );
        $comment_id = wp_insert_comment( $data );

        //Comment meta
        update_comment_meta( $comment_id, 'a_meta_key', 'meta_value' );

        
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        // Adding a repeated category
        $term = wp_insert_term( 'Another category', 'category' );
        restore_current_blog();
    }

    

} // end class  
