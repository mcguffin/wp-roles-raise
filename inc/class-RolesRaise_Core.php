<?php
/**
* @package WP_roles_raise
* @version 0.1
*/




if ( ! class_exists( 'RolesRaise_Core' ) ) :

class RolesRaise_Core {
	
	static function init() {

		register_activation_hook( __FILE__ , array( __CLASS__ , 'plugin_activation' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__ , 'plugin_uninstall' ) );
		
		add_action('wpmu_new_blog' , array( __CLASS__ , 'set_network_roles_for_blog' ) , 10 ,6 );
		load_plugin_textdomain( 'roles-raise' , false, dirname(dirname( plugin_basename( __FILE__ ))) . '/languages');
	}

	static function plugin_activation() {
		if ( is_multisite() ) {
			$role_api = new Network_Role_API();
		} else {
			$role_api = new Blog_Role_API();
		}
		$role_api->backup_default_roles();
	}

	static function plugin_uninstall() {
		if ( is_multisite() ) {
			$role_api = new Network_Role_API();
		} else {
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