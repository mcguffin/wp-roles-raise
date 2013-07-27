<?php
/**
* @package WP_roles_raise
* @version 0.1
*/

/*
Plugin Name: WP Roles Raise
Plugin URI: https://github.com/mcguffin/wp-roles-raise
Description: Just another WordPress Role Editor.
Author: Joern Lund
Version: 0.0.1
Author URI: https://github.com/mcguffin
*/

function is_rolesraise_active_for_network( ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	return is_plugin_active_for_network( basename(dirname(__FILE__)).'/'.basename(__FILE__) );
}

require_once( dirname(__FILE__). '/inc/class-RolesRaise_Core.php' );
require_once( dirname(__FILE__). '/inc/class-RolesRaise_RoleAPI.php' );
require_once( dirname(__FILE__). '/inc/class-RolesRaise_UI.php' );

RolesRaise_Core::init();

register_activation_hook( __FILE__ , array( 'RolesRaise_Core' , 'plugin_activation' ) );
register_uninstall_hook( __FILE__ , array( 'RolesRaise_Core' , 'plugin_uninstall' ) );


// instantinate RolesRaise_UI
if ( is_multisite() && is_network_admin() && is_rolesraise_active_for_network( ) )
	$rolesraise_ui = new RolesRaise_NetworkUI();
else 
	$rolesraise_ui = new RolesRaise_UI();



?>