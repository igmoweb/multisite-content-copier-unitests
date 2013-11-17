<?php

require_once( 'C:\wamp\www\wpmudev2\wp-content\plugins\multisite-content-copier/multisite-content-copier.php' ); 
 

class MCC_Copy_Post extends WP_UnitTestCase {  
	function setUp() {  
	}

	function set_nonces_parameters() {
		$_GET['page'] = 'mcc_settings_page';
		$_REQUEST['_wp_http_referer'] = 'whatever';
		$_REQUEST['mcc_settings_nonce'] = wp_create_nonce( 'submit_mcc_settings' );
	}

	function test_activate_nbt_integration_nbt_no_active() {
		global $multisite_content_copier_plugin;
		$multisite_content_copier_plugin->init_plugin();
		
		$settings_menu = $multisite_content_copier_plugin::$network_settings_menu_page;

		// Need to pass the IFs and nonces verifications
		$_POST['submit_mcc_settings']['blog_templates_integration'] = 'on';
		$this->set_nonces_parameters();

		add_filter( 'mcc_update_settings_screen_redirect_url', array( &$this, 'set_redirect_to' ) );
		$settings_menu->sanitize_settings();
		remove_filter( 'mcc_update_settings_screen_redirect_url', array( &$this, 'set_redirect_to' ) );

		$settings = mcc_get_settings();
		$this->assertFalse( $settings['blog_templates_integration'] );
	}

	function set_redirect_to( $link ) {
		return false;
	}

	function test_activate_nbt_integration_nbt_active_active() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$result = activate_plugin( 'blogtemplates/blogtemplates.php', '', true, false );
		echo "HHHHHHH";
		var_dump( is_plugin_active_for_network( 'blogtemplates/blogtemplates.php' ) );
	}
}