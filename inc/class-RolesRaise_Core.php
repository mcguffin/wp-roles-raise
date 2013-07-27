<?php
/**
* @package WP_roles_raise
* @version 0.1
*/




if ( ! class_exists( 'RolesRaise_Core' ) ) :

class RolesRaise_Core {
	
	static function init() {
		add_action('wpmu_new_blog' , array( __CLASS__ , 'set_network_roles_for_blog' ) , 10 ,6 );
		load_plugin_textdomain( 'roles-raise' , false, dirname(dirname(  plugin_basename( __FILE__ ) )) . '/languages');
	}

	static function plugin_activation() {
		if ( is_multisite() ) { // && network activation!?!?!?
			$role_api = new Network_Role_API();
		} else {
			$role_api = new Blog_Role_API();
		}
		$role_api->backup_default_roles();
		
		
		// we need to know WP's default capablities
		// no other way to get them than scanning the code where they are set? Boo!
		$matches = array();
		$wp_schema_file = file_get_contents( ABSPATH . '/wp-admin/includes/schema.php' );
		preg_match_all( '/add_cap\(\s?(\'|")([a-z0-9_]+)\1\s?\)/' , $wp_schema_file , $matches );
		
		if ( is_multisite() )
			update_site_option( 'wp_native_caps' , array_unique($matches[2]) );
		else
			update_option( 'wp_native_caps' , array_unique($matches[2]) );
		
		
		
		// write capability names for translation
		$langfile = dirname(dirname( __FILE__ )).'/capnames.php';
		if ( ! file_exists( $langfile ) ) {
			touch( $langfile );
			$handle = fopen($langfile,'w');
			fwrite($handle,"<?php\r\r");
			fwrite($handle,"// Auto genereated capability names prepared for l18n.\r\r");
			$caps = array_keys($role_api->get_all_caps());
			foreach ( $caps as $cap ) {
				if ( $role_api->is_wp_native_cap( $cap ) ) {
					$readable_cap = $role_api->raw_readable_cap( $cap );
					fwrite( $handle , "_x( '$readable_cap' , 'Capability name' ,'roles-raise');\r" );
				}
			}
			fwrite( $handle , "\r\r?>" );
			fclose( $handle );
		}
		
	}

	static function plugin_uninstall() {
		if ( is_multisite() ) {
			delete_site_option( 'wp_native_caps' );
			$role_api = new Network_Role_API();
		} else {
			delete_option( 'wp_native_caps' );
			$role_api = new Blog_Role_API();
		}
		$role_api->restore_roles();
		
		
	}

	
	static function set_network_roles_for_blog( $blog_id ) {
		$old_blog_id = get_current_blog_id();
		switch_to_blog( $blog_id );
		$role_api = new Blog_Role_API();
		$role_api->restore_roles();
		switch_to_blog( $old_blog_id );
	}
	
}


endif;


?>