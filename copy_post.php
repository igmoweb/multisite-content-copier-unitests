<?php

require_once( 'C:\wamp\www\wpmudev2\wp-content\plugins\multisite-content-copier/multisite-content-copier.php' ); 
  
class MCC_Copy_Post extends WP_UnitTestCase {  

    protected $plugin;  
    protected $orig_blog_id;  
    protected $dest_blog_id; 
    protected $copier;
    protected $images_array;
    protected $base_dir;
    protected $new_post_id;
  
    function setUp() {  
          
        parent::setUp(); 

        global $multisite_content_copier_plugin;
        $this->plugin = $multisite_content_copier_plugin; 

        $this->orig_blog_id = 1;
        $this->dest_blog_id = 2;
        $this->current_time = '2013/10';

        $this->setup_initial_data();

    } // end setup  
      
    function setup_initial_data() {

        switch_to_blog( $this->orig_blog_id );
        $post_content = 'a_content';

        $this->orig_parent_post_id = $this->factory->post->create_object( array(
            'post_content' => $post_content,
            'post_type' => 'post',
            'post_name' => 'post-parent',
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
            'post_type' => 'post',
            'post_parent' => $this->orig_parent_post_id,
            'post_name' => 'post-child',
            'post_date' => '2013-09-25 00:00:00'
        ) );

        $term1 = wp_insert_term( 'A category', 'category' );
        $term2 = wp_insert_term( 'Another category', 'category' );
        $terms = array( $term1['term_id'], $term2['term_id'] );
        wp_set_object_terms( $this->orig_post_id, $terms, 'category' );

        $tag1 = wp_insert_term( 'A tag', 'post_tag' );
        $tag2 = wp_insert_term( 'Another tag', 'post_tag' );
        $tags = array( $tag1['term_id'], $tag2['term_id'] );
        wp_set_object_terms( $this->orig_post_id, $tags, 'post_tag' );


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
        }

        
        restore_current_blog();

        switch_to_blog( 2 );
        $upload_dir = wp_upload_dir( $this->current_time );

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

//    function test_copy_post() {
//        switch_to_blog( $this->dest_blog_id );
//        $args = array(
//            'copy_images' => false,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => false,
//            'copy_parents' => false,
//            'copy_comments' => false
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//        restore_current_blog();
//
//        switch_to_blog( $this->dest_blog_id );
//        $results = $copier->copy( $this->orig_post_id );
//
//        $this->assertTrue( is_integer( $results['new_post_id'] ) && $results['new_post_id'] > 0 );
//        $this->assertFalse( $results['new_parent_post_id'] );
//
//        $new_post = get_post( $results['new_post_id'] );
//        $this->assertEquals( $new_post->post_name, 'post-child' );
//
//        restore_current_blog();
//    }
//
//    function test_copy_post_and_parent() {
//
//        switch_to_blog( $this->dest_blog_id );
//        $args = array(
//            'copy_images' => false,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => false,
//            'copy_parents' => true,
//            'copy_comments' => false
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//        restore_current_blog();
//
//        switch_to_blog( $this->dest_blog_id );
//        $results = $copier->copy( $this->orig_post_id );
//        
//        $new_parent_post_id = $results['new_parent_post_id'];
//        $this->assertTrue( is_integer( $new_parent_post_id ) && $new_parent_post_id > 0 );
//
//        $new_parent_post = get_post( $new_parent_post_id );
//        $this->assertEquals( $new_parent_post->post_name, 'post-parent' );
//
//        $new_post = get_post( $results['new_post_id'] );
//        $this->assertEquals( $new_post->post_parent, $new_parent_post_id );
//        restore_current_blog();
//        
//        
//    }
//
//    function test_copy_post_update_date() {
//
//        switch_to_blog( $this->dest_blog_id );
//        $args = array(
//            'copy_images' => false,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => true,
//            'copy_parents' => false,
//            'copy_comments' => false
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//
//        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );
//
//        $results = $copier->copy( $this->orig_post_id );
//
//        $new_post = get_post( $results['new_post_id'] );
//        $this->assertGreaterThan( $orig_post->post_date, $new_post->post_date );
//        restore_current_blog();
//        
//    }
//
//    function test_copy_post_and_comments() {
//
//        switch_to_blog( $this->orig_blog_id );
//        $orig_comments_no = count( get_comments( array( 'post_id' => $this->orig_post_id ) ) );
//        restore_current_blog();
//
//        switch_to_blog( $this->dest_blog_id );
//        $args = array(
//            'copy_images' => false,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => false,
//            'copy_parents' => false,
//            'copy_comments' => true
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//
//        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );
//
//        $results = $copier->copy( $this->orig_post_id );
//
//        $new_comments = get_comments( array( 'post_id' => $results['new_post_id'] ) );
//        $this->assertEquals( count( $new_comments ), $orig_comments_no );
//
//        foreach ( $new_comments as $comment ) {
//            if ( $comment->comment_content == 'child comment' ) {
//                $meta_value = get_comment_meta( $comment->comment_ID, 'a_meta_key', true );
//                $this->assertEquals( 'meta_value', $meta_value );
//            }
//        }
//        restore_current_blog();
//        
//    }
//
//    function test_get_all_media() {
//
//        switch_to_blog( $this->dest_blog_id );
//        $args = array(
//            'copy_images' => true,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => false,
//            'copy_parents' => false,
//            'copy_comments' => false
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//        $images = $copier->get_all_media_in_post( $this->orig_post_id );
//
//        $attachments = array();
//        foreach( $images['attachments'] as $attachment ) {
//            $attachments[] = $attachment->post_title;
//        }
//
//        $this->assertContains( 'solo-cabeza2.png', $attachments, 'solo_cabeza2.png was not found in attachments' );
//        $this->assertNotEmpty( $this->file_exists( 'solo-cabeza2.png' ), "solo-cabeza2.png file was not found in $this->base_dir" );
//
//        $this->assertNOTContains( 'uptown-laneway01.jpg', $attachments, 'uptown-laneway01.jpg was not found in attachments' );
//
//        $no_attachments = array();
//        foreach( $images['no_attachments'] as $no_attachment ) {
//            $no_attachments[] = $no_attachment['name'];
//        }
//
//        $this->assertContains( 'fondos-paisajes-1024-7', $no_attachments, 'fondos-paisajes-1024-7 was not found in no-attachments' );
//        $this->assertNotEmpty( $this->file_exists( 'fondos-paisajes-1024-7' ), "fondos-paisajes-1024-7 file was not found in $this->base_dir" );
//
//        $this->assertContains( 'IMG_2301-768x1024', $no_attachments, 'IMG_2301-768x1024 was not found in no-attachments' );
//        $this->assertNotEmpty( $this->file_exists( 'IMG_2301-768x1024' ), "IMG_2301-768x1024 file was not found in $this->base_dir" );
//
//        $this->assertNotContains( 'uptown-laneway01', $no_attachments, 'uptown-laneway01 was found in no-attachments' );
//        $this->assertEmpty( $this->file_exists( 'uptown-laneway01' ), "uptown-laneway01 file was found in $this->base_dir. WHY???" );
//
//        restore_current_blog();
//
//    }
//
//    function file_exists( $filename ) {
//        return glob( $this->base_dir . '/' . $filename . '*' );
//    }
//
//    function test_copy_post_and_media() {
//        switch_to_blog( $this->dest_blog_id );
//
//        $args = array(
//            'copy_images' => true,
//            'post_ids' => array( $this->orig_post_id ),
//            'keep_user' => true,
//            'update_date' => false,
//            'copy_parents' => false,
//            'copy_comments' => false
//        );
//
//        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );
//
//        $new_post_id = $copier->copy_post( $this->orig_post_id );
//
//        $post = get_post( $new_post_id );
//        // Post created ??
//        $this->assertTrue( ! empty( $post ) );
//        restore_current_blog();
//
//        switch_to_blog( $this->dest_blog_id );
//
//        $copier->copy_media( $this->orig_post_id, $new_post_id );
//
//        $orig_post = get_blog_post( $this->orig_blog_id, $this->orig_post_id );
//        $orig_author = $orig_post->post_author;
//        $orig_date = $orig_post->post_date;
//
//        $post = get_post( $new_post_id );
//
//        $this->assertContains( 
//            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/solo-cabeza2.png"><img class="alignnone size-full wp-image-274" alt="solo-//cabeza" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/solo-cabeza2.png" width="1555" height="767" />', 
//            $post->post_content
//        );
//
//        $this->assertContains( 
//            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/IMG_2301-768x1024.jpg"><img class="alignnone size-thumbnail wp-image-275" //alt="IMG_2301-768x1024" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/IMG_2301-768x1024-150x150.jpg" width="150" height="150" ///></a>', 
//            $post->post_content
//        );
//
//        $this->assertContains( 
//            '<a href="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/fondos-paisajes-1024-7.jpg"><img class="alignnone size-medium wp-image-271" //alt="fondos-paisajes-1024 (7)" src="http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/fondos-paisajes-1024-7-300x158.jpg" width="300" //height="158" /></a>', 
//            $post->post_content
//        );
//
//        $this->assertContains( 
//            '<img class="alignnone" alt="" src="http://localhost/wpmudev2/wp-content/uploads/2013/08/uptown-laneway01-150x150.jpg" width="150" height="150" />', 
//            $post->post_content
//        );
//
//        $post_thumbnail = get_the_post_thumbnail( $new_post_id );
//
//        $this->assertContains( 'http://localhost/phpunit-wp/wp-content/uploads/sites/2/2013/10/thumbnail', $post_thumbnail );
//
//        $this->assertTrue( $post->post_author == $orig_post->post_author );
//        $this->assertTrue( $post->post_date == $orig_post->post_date );
//        
//        restore_current_blog();
//    }

    function test_copy_terms() {
        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'copy_images' => true,
            'post_ids' => array( $this->orig_post_id ),
            'keep_user' => true,
            'update_date' => false,
            'copy_parents' => false,
            'copy_comments' => false,
            'copy_terms' => true
        );

        $copier = new Multisite_Content_Copier_Post_Copier( $this->orig_blog_id, $args );

        $new_post_id = $copier->copy( $this->orig_post_id );

        $terms = wp_get_object_terms( $new_post_id, array( 'category', 'post_tag' ), array( 'fields' => 'all' ) );

        foreach ( $terms as $term ) {
            if ( $term->taxonomy == 'category' )
                $this->assertTrue( in_array( $term->name, array( 'A category', 'Another category' ) ) );
            if ( $term->taxonomy == 'post_tag' )
                $this->assertTrue( in_array( $term->name, array( 'A tag', 'Another tag' ) ) );
        }

        restore_current_blog();
    }

    

    

} // end class  
