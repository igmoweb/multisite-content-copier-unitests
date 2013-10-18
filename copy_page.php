<?php

require_once( 'C:\wamp\www\wpmudev2\wp-content\plugins\multisite-content-copier/multisite-content-copier.php' ); 
  
class MCC_Copy_Page extends WP_UnitTestCase {  

    private $plugin;  
    private $orig_blog_id;  
    private $dest_blog_id; 
    private $copier;
    private $images_array;
    private $base_dir;
    private $new_page_id;
  
    function setUp() {  
          
        parent::setUp(); 

        global $multisite_content_copier_plugin;
        $this->plugin = $multisite_content_copier_plugin; 

        $this->orig_blog_id = 1;
        $this->dest_blog_id = 2;

        $this->setup_initial_data();

    } // end setup  
      
    function setup_initial_data() {

        switch_to_blog( $this->orig_blog_id );
        $post_content = 'a_content';

        $this->orig_parent_post_id = $this->factory->post->create_object( array(
            'post_content' => $post_content,
            'post_type' => 'page',
            'post_name' => 'page-parent',
            'post_date' => '2013-09-25 00:00:00'
        ) );

        $post_content = '<a href="http://localhost/phpunit-wp/wp-content/uploads/2013/10/solo-cabeza2.png"><img class="alignnone size-full wp-image-274" alt="solo-cabeza" src="http://localhost/phpunit-wp/wp-content/uploads/2013/10/solo-cabeza2.png" width="1555" height="767" /><a href="http://localhost/phpunit-wp/wp-content/uploads/2013/10/IMG_2301-768x1024.jpg"><img class="alignnone size-thumbnail wp-image-275" alt="IMG_2301-768x1024" src="http://localhost/phpunit-wp/wp-content/uploads/2013/10/IMG_2301-768x1024-150x150.jpg" width="150" height="150" /></a></a>
<a href="http://localhost/phpunit-wp/wp-content/uploads/2013/10/fondos-paisajes-1024-7.jpg"><img class="alignnone size-medium wp-image-271" alt="fondos-paisajes-1024 (7)" src="http://localhost/phpunit-wp/wp-content/uploads/2013/10/fondos-paisajes-1024-7-300x158.jpg" width="300" height="158" /></a>
&nbsp;
&nbsp;
<img class="alignnone" alt="" src="http://localhost/wpmudev2/wp-content/uploads/2013/08/uptown-laneway01-150x150.jpg" width="150" height="150" />
&nbsp;
&nbsp;';

        $this->orig_post_id = $this->factory->post->create_object( array(
            'post_content' => $post_content,
            'post_type' => 'page',
            'post_parent' => $this->orig_parent_post_id,
            'post_name' => 'page-child',
            'post_date' => '2013-09-25 00:00:00'
        ) );

        // Copying images to the first upload folder
        $this->images_array = array(
            array( 'filename' => 'fondos-paisajes-1024-7.jpg', 'post_mime_type' => 'image/jpg', 'post_parent' => $this->orig_post_id ),
            array( 'filename' => 'IMG_2301-768x1024.jpg', 'post_mime_type' => 'image/jpg', 'post_parent' => 0 ),
            array( 'filename' => 'solo-cabeza2.png', 'post_mime_type' => 'image/png', 'post_parent' => $this->orig_post_id )
        );

        $upload_dir = wp_upload_dir();

        $current_dir = dirname( __FILE__ );
        $this->base_dir = $upload_dir['path'] . '/';

        foreach( $this->images_array as $image ) {
            copy( $current_dir . '/images/' . $image['filename'], $this->base_dir . $image['filename'] );
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
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
        }

        
        restore_current_blog();

        switch_to_blog( 2 );
        $upload_dir = wp_upload_dir();

        $current_dir = dirname( __FILE__ );
        $this->dest_base_dir = $upload_dir['path'] . '/';
        restore_current_blog();


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
    }

    function tearDown() {
        $files = glob( $this->base_dir . '/*');
        foreach ( $files as $image ) {
            unlink( $image );
        }

        $files = glob( $this->dest_base_dir . '/*');
        foreach ( $files as $image ) {
            unlink( $image );
        }
    }

    function test_copy_page() {
        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $results = $copier->copy( $this->orig_post_id );
        
        $this->assertTrue( is_integer( $results['new_page_id'] ) && $results['new_page_id'] > 0 );
        $this->assertFalse( $results['new_parent_page_id'] );

        $new_page = get_post( $results['new_page_id'] );
        $this->assertEquals( $new_page->post_name, 'page-child' );

        restore_current_blog();
    }

    function test_copy_page_and_parent() {

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => true,
            'copy_comments' => false
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $results = $copier->copy( $this->orig_post_id );
        
        $new_parent_page_id = $results['new_parent_page_id'];
        $this->assertTrue( is_integer( $new_parent_page_id ) && $new_parent_page_id > 0 );

        $new_parent_page = get_post( $new_parent_page_id );
        $this->assertEquals( $new_parent_page->post_name, 'page-parent' );

        $new_page = get_post( $results['new_page_id'] );
        $this->assertEquals( $new_page->post_parent, $new_parent_page_id );
        restore_current_blog();
        
        
    }

    function test_copy_page_update_date() {

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => true,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );

        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );

        $results = $copier->copy( $this->orig_post_id );

        $new_page = get_post( $results['new_page_id'] );
        $this->assertGreaterThan( $orig_post->post_date, $new_page->post_date );
        restore_current_blog();
        
    }

    function test_copy_page_and_comments() {

        switch_to_blog( $this->orig_blog_id );
        $orig_comments_no = count( get_comments( array( 'post_id' => $this->orig_post_id ) ) );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'copy_images' => false,
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => true
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );
        
        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );

        $results = $copier->copy( $this->orig_post_id );

        $new_comments = get_comments( array( 'post_id' => $results['new_page_id'] ) );
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
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );
        $images = $copier->get_all_media_in_post( $this->orig_post_id );

        $attachments = array();
        foreach( $images['attachments'] as $attachment ) {
            $attachments[] = $attachment->post_title;
        }

        $this->assertContains( 'solo-cabeza2.png', $attachments, 'solo_cabeza2.png was not found in attachments' );
        $this->assertNotEmpty( $this->file_exists( 'solo-cabeza2.png' ), "solo-cabeza2.png file was not found in $this->base_dir" );

        $this->assertNOTContains( 'uptown-laneway01.jpg', $attachments, 'uptown-laneway01.jpg was not found in attachments' );

        $no_attachments = array();
        foreach( $images['no_attachments'] as $no_attachment ) {
            $no_attachments[] = $no_attachment['name'];
        }

        $this->assertContains( 'fondos-paisajes-1024-7', $no_attachments, 'fondos-paisajes-1024-7 was not found in no-attachments' );
        $this->assertNotEmpty( $this->file_exists( 'fondos-paisajes-1024-7' ), "fondos-paisajes-1024-7 file was not found in $this->base_dir" );

        $this->assertContains( 'IMG_2301-768x1024', $no_attachments, 'IMG_2301-768x1024 was not found in no-attachments' );
        $this->assertNotEmpty( $this->file_exists( 'IMG_2301-768x1024' ), "IMG_2301-768x1024 file was not found in $this->base_dir" );

        $this->assertNotContains( 'uptown-laneway01', $no_attachments, 'uptown-laneway01 was found in no-attachments' );
        $this->assertEmpty( $this->file_exists( 'uptown-laneway01' ), "uptown-laneway01 file was found in $this->base_dir. WHY???" );

        restore_current_blog();

    }

    function file_exists( $filename ) {
        return glob( $this->base_dir . '/' . $filename . '*' );
    }

    function test_copy_page_and_media() {
        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'copy_images' => true,
            'pages_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false
        );

        $copier = new Multisite_Content_Copier_Page_Copier( $this->orig_blog_id, $args );

        $new_page_id = $copier->copy_post( $this->orig_post_id );

        $page = get_post( $new_page_id );
        // Page created ??
        $this->assertTrue( ! empty( $page ) );
        restore_current_blog();

        switch_to_blog( $this->dest_blog_id );

        $copier->copy_media( $this->orig_post_id, $new_page_id );

        $orig_page = get_blog_post( $this->orig_blog_id, $this->orig_post_id );
        $orig_author = $orig_page->post_author;
        $orig_date = $orig_page->post_date;

        $page = get_post( $new_page_id );

        $this->assertContains( 
            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/solo-cabeza2.png"><img class="alignnone size-full wp-image-274" alt="solo-cabeza" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/solo-cabeza2.png" width="1555" height="767" />', 
            $page->post_content
        );

        $this->assertContains( 
            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/IMG_2301-768x1024.jpg"><img class="alignnone size-thumbnail wp-image-275" alt="IMG_2301-768x1024" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/IMG_2301-768x1024-150x150.jpg" width="150" height="150" /></a>', 
            $page->post_content
        );

        $this->assertContains( 
            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/fondos-paisajes-1024-7.jpg"><img class="alignnone size-medium wp-image-271" alt="fondos-paisajes-1024 (7)" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/fondos-paisajes-1024-7-300x158.jpg" width="300" height="158" /></a>', 
            $page->post_content
        );

        $this->assertContains( 
            '<img class="alignnone" alt="" src="http://localhost/wpmudev2/wp-content/uploads/2013/08/uptown-laneway01-150x150.jpg" width="150" height="150" />', 
            $page->post_content
        );

        $this->assertTrue( $page->post_author == $orig_page->post_author );
        $this->assertTrue( $page->post_date == $orig_page->post_date );
        
        restore_current_blog();
    }

    

    

} // end class  
