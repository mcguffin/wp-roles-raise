<?php
/**
* @package WP_roles_raise
* @version 0.1
*/



if ( ! interface_exists( 'Role_API' ) ) :

interface Role_API {

	function add_role( $slug , $name );
	function remove_role( $slug );
	function has_role( $slug );

	function update_roles( $roles_array );
	function after_update_roles( );

	function get_default_role();
	function update_default_role( $slug );

	function backup_default_roles( );
	function restore_roles( );

}
endif;


if ( ! class_exists( 'Network_Role_API' ) ) :

class Network_Role_API implements Role_API {
	private $_roles;
	private $_role_key;
	
	function __construct( ) {
		global $wpdb;
		$this->_role_key = $wpdb->prefix . 'default_roles';
		$this->_roles = get_site_option( $this->_role_key );
	}
	
	function has_role( $slug ) {
		return array_key_exists( $slug , $this->_roles );
	}
	function add_role( $slug , $name ) {
		$this->_roles[$slug] = array(
			'name' => $name,
			'capabilities' => array(),
		);
		$this->_store();
	}
	function remove_role( $slug ) {
		unset($this->_roles[$slug]);
		$this->_store();
	}
	function update_roles( $roles_array ) {
		$this->_roles = $roles_array;
		$this->_store();
	}
	function after_update_roles() { // dummy method
	}

	function restore_roles() {
		// get wp defaults, set.
		$this->_roles = get_site_option( 'wp_native_roles' );
		$this->_store();
	}
	function get_roles() {
		return $this->_roles;
	}
	function backup_default_roles() {
		// store native wp
		global $wp_roles;
		if ( ! get_site_option( 'wp_native_roles' ) )
			update_site_option( 'wp_native_roles' , $wp_roles->roles );
	}
	function get_default_role() {
		global $wpdb;
		return get_site_option( $wpdb->prefix . 'default_role' );
	}
	function update_default_role( $slug ) {
		global $wpdb;
		return update_site_option( $wpdb->prefix . 'default_role' , $slug );
	}
	
	
	private function _store( ) {
		update_site_option( $this->_role_key , $this->_roles );
	}
	
}
endif;


if ( ! class_exists( 'Blog_Role_API' ) ) :

class Blog_Role_API implements Role_API {
	
	function has_role( $slug ) {
		return ! is_null( get_role( $slug ) );
	}
	function add_role( $slug , $name ) {
		add_role( $slug , $name );
	}
	function remove_role( $slug ) {
		remove_role( $slug );
	}
	function update_roles( $roles_array ) {
		global $wp_roles;
		foreach ( $roles_array as $rolename => $role ) {
			if ( ! $this->has_role( $rolename ) )
				continue;
			$current_role = get_role( $rolename );
			
			foreach ( $role['capabilities'] as $cap => $grant ) {
				if ( (bool) $grant || $role == 'administrator' )
					$current_role->add_cap( $cap , (bool) $grant );
				else if ( $current_role->has_cap( $cap ) )
					$current_role->remove_cap( $cap );
			}
		}
	}
	function after_update_roles() {
		global $wpdb;
		$count_users = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
		
		$users = get_users();
		foreach ( $users as $user ) {
			$user->update_user_level_from_caps();
		}
	}
	
	function get_roles() {
		global $wp_roles;
		return $wp_roles->roles;
	}
	
	function get_default_role() {
		return get_option( 'default_role' );
	}
	function update_default_role( $slug ) {
		update_option( 'default_role' , $slug );
	}

	
	
	
	function restore_roles() {
		// get wp defaults, set.
		if ( is_multisite() ) { // network context
			$roles_api = new Network_Role_API();
			$restore_roles = $roles_api->get_roles();
			$restore_default_role = $roles_api->get_default_role();
		} else {
			$restore_roles = get_option( 'wp_native_roles' );
			$restore_default_role =  get_option('wp_native_default_role');
		}
		foreach ($this->get_roles() as $slug => $role )
			$this->remove_role( $slug );
		
		foreach ( $restore_roles as $slug => $role )
			$this->add_role( $slug , $role['name'] );
		
		$this->update_roles($restore_roles);
		
		update_option( 'default_role' , $restore_default_role );
	}
	function backup_default_roles() {
		// store native wp
		if ( ! is_multisite() ) {
			if ( ! get_option( 'wp_native_roles' ) )
				update_option( 'wp_native_roles' , $wp_roles->roles );
			if ( ! get_option( 'wp_native_default_role' ) )
				update_option( 'wp_native_default_role' , get_option('default_role') );
		}
	}
	

}
endif;




?>