<?php




if ( ! class_exists( 'RolesRaise_Editor' ) ) :

class RolesRaise_Editor {
	
	static function init() {
		add_action( 'admin_menu', array( __CLASS__ , 'add_roles_menu' ));
		add_action( 'network_admin_menu', array( __CLASS__ , 'add_default_roles_menu' ));
		
		add_action( 'load-users_page_roles' ,array( __CLASS__ , 'hookup_admin_head'));
		add_action( 'load-users_page_default_roles' ,array( __CLASS__ , 'hookup_admin_head'));

		add_action( 'load-users_page_roles' ,array( __CLASS__ , 'do_role_actions'));
		add_action( 'load-users_page_default_roles' ,array( __CLASS__ , 'do_role_actions'));

		register_activation_hook( __FILE__ , array( __CLASS__ , 'plugin_activation' ) );
		register_uninstall_hook( __FILE__ , array( __CLASS__ , 'plugin_uninstall' ) );
		
		add_action('wpmu_new_blog' , array( __CLASS__ , 'set_network_roles_for_blog' ) , 10 ,6 );
//		add_filter('user_has_cap',array(__CLASS__,'dump'),10,3);
		load_plugin_textdomain( 'roles-raise' , false, dirname(dirname( plugin_basename( __FILE__ ))) . '/lang');
	}
	static function dump( ) {
		$a = func_get_args();
		var_dump($a);
		return $a[0];
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


	// editors
	static function add_roles_menu() {
		add_users_page(__('Manage Roles','roles-raise'), __('Roles','roles-raise'), 'promote_users', 'roles', array( __CLASS__ , 'manage_roles_screen'));
	}
	static function add_default_roles_menu(){
		add_users_page(__('Manage Default Roles','roles-raise'), __('Default Roles','roles-raise'), 'promote_users', 'default_roles', array( __CLASS__ , 'manage_roles_screen'));
	}
	
	
	
	static function get_current_role_api( ) {
		if ( is_multisite() && is_network_admin() )
			return new Network_Role_API();
		else
			return new Blog_Role_API();
	}
	static function get_current_roles( ) {
		return self::get_current_role_api( )->get_roles();
	}
	
	static function set_network_roles_for_blog( $blog_id ) {
		$old_blog_id = get_current_blog_id();
		switch_to_blog( $blog_id );
		$role_api = new Blog_Role_API();
		$role_api->restore_roles();
		switch_to_blog( $old_blog_id );
	}
	
	static function get_cap_groups( ) {
		global $wp_roles , $wpdb;
		$existing_caps = array();
		$resorted_existing_caps = array();
		

		$roles = self::get_current_role_api( )->get_roles();

		foreach ($roles as $role => $role_array ) {
			foreach ( $role_array['capabilities'] as $cap => $permit ) {
				if ( ! isset( $existing_caps[$cap] ) )
					$existing_caps[$cap] = array();
			
				$existing_caps[$cap][$role] = isset( $role_array['capabilities'][$cap] ) && $permit;
			}
		}
		$cap_groups = array(
			'/pages?$/' => __('Pages'),
			'/posts?$/' => __('Posts'),
			'/links?$/' => __('Links'),
			'/themes?/' => __('Themes'),
			'/plugins?$/' => __('Plugins'),
			'/(files?|uploads?)$/' => __('Files'),
			'/users?$/' => __('Users'),
			'(.*)' => __('Miscellaneous','roles-raise'),
		);
		foreach ( $cap_groups as $regex => $groupname ) {
			if ( ! isset($resorted_existing_caps[ $groupname ] ) )
				$resorted_existing_caps[ $groupname ] = array();
			foreach ( $existing_caps as $cap => $rolenames ) {
				if ( preg_match( $regex , $cap ) ) {
					$resorted_existing_caps[ $groupname ][ $cap ] = $rolenames;
					unset($existing_caps[$cap]);
				}
			}
		}
		return $resorted_existing_caps;
	}
	
	static function do_role_actions() {
		global $wp_roles,$wpdb;

		if ( empty( $_POST ) )
			return;
		$redirect = $_SERVER['REQUEST_URI'];
		if ( ! current_user_can( 'promote_users' ) ) 
			wp_die( __('You are not allowed to do this.' ,'roles-raise') );

		if ( ! isset( $_REQUEST['action'] ) )
			wp_redirect( $redirect ) & die();
		
		if ( ! wp_verify_nonce( @$_REQUEST['_wpnonce'], $_REQUEST['action'] ) )
			wp_die( __('You are not allowed to do this.' ,'roles-raise') );
		

		$role_api = self::get_current_role_api( );
	
		switch ( $_REQUEST['action'] ) {
			case 'restore-roles':
				$role_api->restore_roles( );
				$redirect = add_query_arg( array('message' => '4') , $redirect );
				break;
			case 'create-role':
				// no rolename entered!
				if ( ! isset($_REQUEST['rolename']) || empty($_REQUEST['rolename'] ) ) {
					$redirect = add_query_arg( array('message' => '10') , $redirect );
					break;
				}
				$rolename = $_REQUEST['rolename'];
				// role exists!
				// all good, do it!
				
				if ( $role_api->has_role( $rolename ) ) {
					$redirect = add_query_arg( array('message' => '11') , $redirect );
					break;
				}
				$role_api->add_role( sanitize_title( $rolename ) , sanitize_text_field($rolename) );
				
				$redirect = add_query_arg( array('message' => '1' ) , $redirect ); // role created
				break;
			case 'delete-role':
				// no role spacified!
				if ( ! isset($_REQUEST['rolename']) || empty($_REQUEST['rolename'] ) ) {
					$redirect = add_query_arg( array('message' => '12') , $redirect );
					break;
				}
				$rolename = $_REQUEST['rolename'];
			
				// never delete an admin, mon!
				if (  $rolename == 'administrator' ) {
					$redirect = add_query_arg( array('message' => '13') , $redirect );
					break;
				}

				// no such role
				if ( ! $role_api->has_role( $rolename ) ) {
					$redirect = add_query_arg( array('message' => '14') , $redirect );
					break;
				}
				$role_api->remove_role( $rolename );
				$redirect = add_query_arg( array('message' => '3' ) , $redirect ); // role deleted
				break;
			case 'update-roles':
				if ( ! isset($_REQUEST['caps']) || empty($_REQUEST['caps'] ) ) {
					$redirect = add_query_arg( array('message' => '15') , $redirect );
					break;
				}
				if ( ! is_multisite() || ! is_network_admin() )
					unset($_REQUEST['caps']['administrator']);
				
				$role_api->update_roles( $_REQUEST['caps'] );
				
				//
				if (  ! is_multisite() || is_network_admin() )
					$role_api->update_default_role( $_REQUEST['default_role'] );
				
				

				$redirect = add_query_arg( array('message' => '2' ) , $redirect ); // roles updated
				break;
			case 'update-users':
				// update all user levels
				$role_api->after_update_roles( );
				$redirect = add_query_arg( array('message' => '5' ) , $redirect ); // roles updated
				break;
		}
		wp_redirect( $redirect ) & die();
	}
	

	
	static function manage_roles_screen( $a ) {
		global $wp_roles;

		$role_api = self::get_current_role_api( );
		$resorted_existing_caps = self::get_cap_groups();
		$roles = $role_api->get_roles();
		$default_role = $role_api->get_default_role();
		
		?><div id="edit-roles" class="wrap"><?php
		?><div id="icon-users" class="icon32"><br></div><?php
		?><h2><?php
			if ( is_network_admin() )
				_e('Manage Default Roles','roles-raise');
			else 
				_e('Manage Roles','roles-raise');
		?></h2><?php
		self::_put_message( );
	


		// delete role.
		// add role.
		?><div class="metabox-holder"><?php
		?><div class="postbox"><?php
		?><h3 class="hndle"><span><?php
			_e('Tools');
		?></span></h3><?php
		?><table class="widefat form-table"><?php
			?><tr><?php
				?><th><?php
					_e( 'Create Role' ,'roles-raise')
				?></th><?php
				?><th><?php
					_e( 'Delete Role' ,'roles-raise')
				?></th><?php
				if ( ! is_multisite() || ! is_network_admin() ) { // user accounts are affected only on blog
					?><th><?php
						_e( 'Update User accounts' ,'roles-raise')
					?></th><?php
				}
				?><th class="danger"><?php
					_e( 'Restore Default Roles' ,'roles-raise')
				?></th><?php
			?></tr><?php
			?><tr><?php
				?><td><?php
					?><form method="post"><?php
						$action = 'create-role';
						wp_nonce_field( $action , '_wpnonce' , true , true );
						?><input type="hidden" name="action" value="<?php echo $action ?>"  /><?php
						
						?><label for="input-role"><?php
						_e( 'Rolename:' ,'roles-raise');
						?></label><?php
						?><br /><?php
						
						?><input id="input-role" type="text" name="rolename" class="regular-text"  /> <?php

						submit_button( __('Create Role','roles-raise'), 'secondary', 'create-role' , true );
					?></form><?php
				?></td><?php
				
				
				?><td><?php
					?><form method="post"><?php
						$action = 'delete-role';
						wp_nonce_field( $action , '_wpnonce' , true , true );
						?><input type="hidden" name="action" value="<?php echo $action ?>"  /><?php
						
						?><label for="select-role"><?php
						_e( 'Rolename:' ,'roles-raise');
						?></label><?php
						?><br /><?php
						?><select name="rolename" id="select-role"><?php
							?><option><?php _e('– Select Role –','roles-raise') ?></option><?php
							foreach ( $roles as $rolename => $role ) {
								if ( $rolename == 'administrator' || $rolename == $default_role )
									continue;
								?><option value="<?php echo $rolename ?>"><?php echo translate_user_role( $role["name"] ); ?></option><?php
							}
						?></select><?php

						submit_button( __('Delete Role','roles-raise'), 'delete', 'delete-role' , true );
					?></form><?php
				?></td><?php
				
				if ( ! is_multisite() || ! is_network_admin() ) {
					?><td><?php
						?><form method="post"><?php
							$action = 'update-users';
							wp_nonce_field( $action , '_wpnonce' , true , true );
							?><input type="hidden" name="action" value="<?php echo $action ?>"  /><?php
							
							$is_due = isset( $_REQUEST['message'] ) && ( $_REQUEST['message'] == 2 ||  $_REQUEST['message'] == 4); // restored || updated
							
							if ( $is_due ) {
								?><div class="message"><?php
							}
							?><p class="description"><?php
								_e('After changing a role, You should update all user accounts on your blog.','roles-raise') ;
							?></p><?php 
							if ( $is_due ) {
									?><p><strong><?php 
										_e('The right time is now.','roles-raise') ;
									?></strong></p><?php 
								?></div><?php
							}
							submit_button( __('Update User accounts','roles-raise'), 'primary', 'delete-role' , true );
						?></form><?php
					?></td><?php
				}
				?><td class="danger"><?php
					?><form method="post"><?php
						$action = 'restore-roles';
						wp_nonce_field( $action , '_wpnonce' , true , true );
						?><div class="warning"><?php
							?><p><?php 
								_e('<strong>Danger Zone:</strong> <br />All your changes will be lost! This is our last warning.','roles-raise') ;
							?></p><?php 
						
							?><p><?php 
								?><input id="confirm-restore" type="checkbox" name="action" value="<?php echo $action ?>"  /> <?php
								?><label for="confirm-restore"><?php
								_e( 'I know what I am doing.' ,'roles-raise');
								?></label><?php
							?></p><?php 
						
						?></div><?php
						submit_button( __('Restore Roles','roles-raise'), 'delete', 'restore-roles' , true );
					?></form><?php
				?></td><?php
				
			?></tr><?php
		?></table><?php
		?></div><?php
		?></div><?php
	


		?><h3><?php
			_e('Roles and Capabilities','roles-raise');
		?></h3><?php


		?><form method="post"><?php

		$action = 'update-roles';
		wp_nonce_field( $action , '_wpnonce' , true , true );
		?><input type="hidden" name="action" value="<?php echo $action ?>"  /><?php
		
		$odd = true;
		// capgroups: themes?, pages?, posts?, users?, uploads?, files?, links?
		foreach ($roles as $role => $role_array ) {
			?><input type="hidden" name="caps[<?php echo $role ?>][name]" value="<?php echo $role_array['name'] ?>" /><?php
		}
		?><table class="form-table roles"><?php
		
			// set default role
			if ( ! is_multisite() || is_network_admin() ) {
				$default_role = self::get_current_role_api( )->get_default_role();
				?><tr class="roles-head"><?php
					?><th class="title"><?php
						_e('New User Default Role');
					?></th><?php
					foreach ($roles as $role => $role_array ) {
						?><th class="rolename"><?php
							if ( $role != 'administrator' ) {
								?><input id="default-role-<?php echo $role ?>" type="radio" name="default_role"  value="<?php echo $role ?>" <?php checked($default_role,$role,true) ?> /><?php
								?><label for="default-role-<?php echo $role ?>"><?php
									echo translate_user_role( $role_array["name"] );
								?></label><?php
								?><br /><?php
							} else {
								//echo translate_user_role( $role_array["name"] );
							}
						?></th><?php
					}
				?></tr><?php
			}

			?><tbody><?php
		
			foreach ($resorted_existing_caps as $groupname => $caps ) {
				?><tr><?php
					?><th class="groupname" colspan="<?php echo count($wp_roles->roles)+2 ?>"><?php
						echo $groupname;
					?></th><?php
				?></tr><?php
				$group_slug = sanitize_title( $groupname );
				self::print_roles_head( $group_slug );

				foreach ($caps as $cap => $cap_role_array ) {
					if ( $odd ) {
						?><tr><?php
					} else {
						?><tr class="alternate"><?php
					}
						?><td class="title cap-select"><?php
							?><input id="<?php echo $cap ?>" class="<?php echo $cap ?>" type="checkbox" name="_dummy" value="" /> <?php
							?><label for="<?php echo $cap ?>"><?php
							$readable_cap = ucfirst( str_replace('_', ' ', $cap));

							_ex( $readable_cap , 'capability' ,'roles-raise');
							?></label><?php
						?></td><?php
					foreach ($roles as $role => $role_array ) {
						?><td class="cap-edit"><?php
							$enabled = isset($cap_role_array[$role]) && $cap_role_array[$role];
							$readonly = ! is_network_admin() && $role == 'administrator' ? ' disabled="disabled" ' : '';
							?><input type="hidden" name="caps[<?php echo $role ?>][capabilities][<?php echo $cap ?>]" value="0" /><?php
							?><input class="<?php echo $group_slug.' '.$role.' '.$cap ?>" type="checkbox" name="caps[<?php echo $role ?>][capabilities][<?php echo $cap ?>]" value="1" <?php echo checked($enabled,true).$readonly ?> /><?php
					
						?></td><?php
					}
					?></tr><?php
					$odd = !$odd;
				}
			}
			?></tbody><?php
		
		?></table><?php
		
		submit_button( __('Save Role Settings','roles-raise'), 'primary', 'save-caps' );
		?></form><?php
		
	}

	static function print_roles_head( $group_slug = '' ) {
		$roles = self::get_current_roles();
		?><tr class="roles-head"><?php
			?><th class="title"><?php
				// _e('Capability / Role','roles-raise');
			?></th><?php
			foreach ($roles as $role => $role_array ) {
				?><th class="rolename"><?php
					if ( is_network_admin() || $role != 'administrator' ) {
						?><label for="<?php echo $group_slug.'-'.$role ?>"><?php
							echo translate_user_role( $role_array["name"] );
						?></label><?php
						?><br /><?php
						?><input id="<?php echo $group_slug.'-'.$role ?>" class="<?php echo $group_slug.' '.$role ?>" type="checkbox" name="_dummy" value="" /><?php
					} else {
						echo translate_user_role( $role_array["name"] );
					}
				?></th><?php
			}
		?></tr><?php
	}

	static function hookup_admin_head() {
		add_action('admin_head',array( __CLASS__ , 'admin_head_role_editor'));
	}

	static function admin_head_role_editor( ) {
		?><style type="text/css">
		#edit-roles {
			border-collapse:collapse;
		}
		#edit-roles .groupname {
			padding:0.25em;
			background:#21759b;
			color:#ffffff;
			font-weight:bold;
			text-align:center;
			font-size:1.2em;
			text-shadow:0 -1px 0 #333;
			border-top:2px solid #fff;
		}
		#edit-roles .cap-edit {
			text-align:center;
		}
		#edit-roles .rolename {
			font-weight:bold;
			text-align:center;
			font-size:1.2em;
			background:#777;
			color:#fff;
			text-shadow:0 -1px 0 #333;
			border-right:1px solid #ccc;
		}
		#edit-roles .title {
			font-weight:bold;
			font-size:1.2em;
			background:#aaa;
			color:#fff;
			text-shadow:0 -1px 0 #333;
		}
		#edit-roles .roles-head .title,
		#edit-roles .roles-head .rolename {
			border-top:1px solid #ccc;
		}
		
		#edit-roles .warning {
			border:2px solid #333;
			background:#ffdd00;
			padding:0.5em;
			text-shadow:0 -1px 0 #ccc;
		}
		#edit-roles .danger {
			background:#777;
			color:#fff;
			text-shadow:0 -1px 0 #333;
		}
		#edit-roles .message {
			background-color: #ffffe0;
			border-color: #e6db55;
			padding: 0 .6em;
			-webkit-border-radius: 3px;
			border-radius: 3px;
			border-width: 1px;
			border-style: solid;
			color: #333;
		}
		
		</style><?php
		?><script type="text/javascript">
		(function($){
		
			$(document).ready(function($){
				$('tr.roles-head input[type="checkbox"]').click(function(event){
					var $this = $(this);
					var selector = 'input[type="checkbox"].'+$this.attr('class').split(' ').join('.');
					if ( $this.attr('checked') )
						$(selector).attr('checked', 'checked' );
					else
						$(selector).removeAttr('checked');
				});
				$('td.cap-select input[type="checkbox"]').click(function(event){
					var $this = $(this);
					var selector = 'input[type="checkbox"]:not([disabled]).'+$this.attr('class');
					console.log(selector);
					if ( $this.attr('checked') )
						$(selector).attr('checked', 'checked' );
					else
						$(selector).removeAttr('checked');
				});
			});

		})(jQuery);
		</script><?php
	}

	private static function _put_message( ) {
		if ( ! isset( $_REQUEST['message'] ) )
			return;
			
		$message_wrap = '<div id="message" class="updated"><p>%s</p></div>';
		switch( $_REQUEST['message'] ) {
			case 1: // created
				$message = __('Role created.','roles-raise');
				break;
			case 2: // updated
				$message = __('Roles updated. Please remember to Update all user accounts as well.','roles-raise');
				break;
			case 3: // deleted
				$message = __('Role deleted.','roles-raise');
				break;
			case 4: // restored
				$message = __('All Roles have been restored to default values.','roles-raise');
				break;
			case 5: // user accounts updated
				$message = __('User accounts have been updated.','roles-raise');
				break;
				
			case 10: // not name specified
				$message = __('No Rolename specified.','roles-raise');
				break;
			case 11: // exists
				$message = __('This Role already exists.','roles-raise');
				break;
			case 12: // no data submitted
				$message = __('Missing data.','roles-raise');
				break;
			case 13: // Not allowd to delete the adminisrator role
				$message = __('You cannot delete the Adminstrator role.','roles-raise');
				break;
			case 14: // not exists
				$message = __('This Role does not exist.','roles-raise');
				break;
			default:
				$message = '';
				break;
		}
		if ( $message )
			printf( $message_wrap , $message );
	}
	
}
RolesRaise_Editor::init();

endif;



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
	function after_update_roles() {
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