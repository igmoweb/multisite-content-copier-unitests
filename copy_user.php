<?php

require_once( 'C:\wamp\www\wpmudev2\wp-content\plugins\multisite-content-copier/multisite-content-copier.php' ); 
  
class MCC_Copy_Users extends WP_UnitTestCase {  
	function setUp() {  
          
        parent::setUp(); 

        global $multisite_content_copier_plugin;
        $this->plugin = $multisite_content_copier_plugin; 

        $this->orig_blog_id = 1;
        $this->dest_blog_id = 2;

        $this->users = array();
        $this->users_ids = array();

        $this->setup_initial_data();



    } // end setup  

    function setup_initial_data() {
    	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    	switch_to_blog( $this->orig_blog_id );
        $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );

        $user_id = wp_create_user( 'user1', $random_password, 'user1@gmail.com' );
        add_user_to_blog( get_current_blog_id(), $user_id, 'administrator' );
        $this->users[ $user_id ] = 'administrator';
        $this->users_ids[] = $user_id;

        $user_id = wp_create_user( 'user2', $random_password, 'user2@gmail.com' );
        add_user_to_blog( get_current_blog_id(), $user_id, 'author' );
        $this->users[ $user_id ] = 'author';
        $this->users_ids[] = $user_id;

        $user_id = wp_create_user( 'user3', $random_password, 'user3@gmail.com' );
        add_user_to_blog( get_current_blog_id(), $user_id, 'author' );
        $this->users[ $user_id ] = 'author';
        $this->users_ids[] = $user_id;

    	restore_current_blog();
    }

    function tearDown() {
    	include_once( ABSPATH . 'wp-admin/includes/user.php' );

        foreach ( $this->users as $user_id => $role ) {
            wp_delete_user( $user_id );  
        }        

    }

    /**
     * Copy 2 users
     * @return type
     */
    function test_copy_few_users() {
        $users_to_copy = array( $this->users_ids[0], $this->users_ids[1] );

        switch_to_blog( $this->dest_blog_id );
        $args = array(
            'users' => $users_to_copy
        );
        $copier = new Multisite_Content_Copier_User_Copier( $this->orig_blog_id, $args );
        $copier->execute();

        $current_users = get_users();
        foreach ( $current_users as $user ) {
            $current_users[ $user->data->ID ] = $user->roles[0];
        }

        foreach ( $users_to_copy as $user_id ) {
            $role = $this->users[ $user_id ];
            $this->assertTrue( array_key_exists( $user_id, $current_users ) );
            $this->assertTrue( $current_users[ $user_id ] == $role );
        }

        // Let's check if the user that we didn't want to copy hasn't been added
        $this->assertFalse( array_key_exists( $this->users_ids[2], $current_users ) );

        restore_current_blog();
    }

    function test_copy_already_existent_user_with_different_role() {
        switch_to_blog( $this->dest_blog_id );
        // First we'll add an already existent user with a different role than has in the orig blog
        // Instead of administrator we'll add it as a subscriber
        $already_existent_user = add_user_to_blog( get_current_blog_id(), $this->users_ids[0], 'subscriber' );

        $args = array(
            'users' => $this->users_ids[0]
        );
        $copier = new Multisite_Content_Copier_User_Copier( $this->orig_blog_id, $args );
        $copier->execute();

        // The user must exists but he should have subscriber role
        $user = get_userdata( $this->users_ids[0] );
        
        $this->assertEquals( $user->roles[0], 'subscriber' );

        restore_current_blog();
    }

    function test_copy_all_users() {
        switch_to_blog( $this->dest_blog_id );

        $args = array(
            'users' => 'all'
        );
        $copier = new Multisite_Content_Copier_User_Copier( $this->orig_blog_id, $args );
        $copier->execute();

        $current_users = get_users();
        $users_roles = array();
        foreach ( $current_users as $user ) {
            $users_roles[ $user->data->ID ] = $user->roles[0];
        }

        foreach ( $this->users_ids as $user_id ) {
            $role = $this->users[ $user_id ];
            $this->assertTrue( array_key_exists( $user_id, $users_roles ), "Failed: user_id $user_id does not exist as a key in user_roles" );
            $this->assertTrue( $users_roles[ $user_id ] == $role, "Failed: user_id $user_id ( " . get_userdata( $user_id )->data->user_email . " ) has not the role $role" );
        }

        restore_current_blog();
    }
}