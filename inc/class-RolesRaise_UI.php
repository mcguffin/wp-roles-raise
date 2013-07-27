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




if ( ! class_exists( 'RolesRaise_UI' ) ) :

class RolesRaise_UI {
	private $role_api;
	private $editor_title;
	
	function __construct() {
		$this->role_api = new Blog_Role_API();
		$this->editor_title = __('Manage Roles','roles-raise');
		add_action( 'init' , array( &$this , 'hookup' ) );
	}
	
	function hookup() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this , 'add_roles_menu' ));
		
			add_action( 'load-users_page_roles' ,array( &$this , 'hookup_admin_head'));
			add_action( 'load-users_page_roles' ,array( &$this , 'do_role_actions'));
	
			add_filter( 'update_role_data' , array( &$this , 'update_role_data' ) );
		
			add_action( 'role_actions_ui' ,   array( &$this , 'role_action_update_users' ) );
			add_filter( 'role_action_update-users' ,   array( &$this , 'update_users' ) , 10 ,2 );
		
			if ( ! is_multisite() )
				add_action( 'roles_table_head' ,   array( &$this , 'print_select_default_role' ) , 10 , 2 );
		}
	}
	// action
	public function hookup_admin_head() {
		add_action('admin_head',array( &$this , 'admin_head_role_editor'));
	}

	// filter
	public function update_role_data( $data ){
		unset($data['administrator']);
		return $data;
	}

	// editors
	public function add_roles_menu() {
		add_users_page(__('Manage Roles','roles-raise'), __('Roles','roles-raise'), 'promote_users', 'roles', array( &$this , 'manage_roles_screen'));
	}
	
	
	public function get_cap_groups( ) {
		global $wp_roles , $wpdb;
		$existing_caps = array();
		$resorted_existing_caps = array();
		

		$roles = $this->role_api->get_roles();

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
	
	public function do_role_actions() {
		global $wp_roles,$wpdb;

		if ( empty( $_POST ) )
			return;
		$redirect = $_SERVER['REQUEST_URI'];
		if ( ! current_user_can( 'promote_users' ) ) 
			wp_die( __('You are not allowed to do this.' ,'roles-raise') );

		if ( ! isset( $_REQUEST['action'] ) )
			wp_redirect( $redirect ) & die();

		$action = $_REQUEST['action'];
		
		if ( ! wp_verify_nonce( @$_REQUEST['_wpnonce'], $action ) )
			wp_die( __('You are not allowed to do this.' ,'roles-raise') );
		
		$role_api = $this->role_api;
		
		$redirect = apply_filters_ref_array( "role_action_$action" , array( $redirect , &$role_api ) );
		
		switch ( $action ) {
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
				
				$role_data = apply_filters( 'update_role_data' , $_REQUEST['caps'] );
				
				if (  ! is_multisite() || is_network_admin() )
					$role_api->update_default_role( $_REQUEST['default_role'] );
		

				
				$role_api->update_roles( $role_data );
				
				$redirect = add_query_arg( array('message' => '2' ) , $redirect ); // roles updated
				break;
		}
		wp_redirect( $redirect ) & die();
	}
	
	public function update_users( $redirect , $role_api ) {
		$role_api->after_update_roles( );
		return add_query_arg( array('message' => '5' ) , $redirect ); // users updated
	}
	
	public function manage_roles_screen( $a ) {
		global $wp_roles;

		$role_api = $this->role_api;
		$resorted_existing_caps = $this->get_cap_groups();
		$roles = $role_api->get_roles();
		$default_role = $role_api->get_default_role();
		
		?><div id="edit-roles" class="wrap"><?php
		?><div id="icon-users" class="icon32"><br></div><?php
		?><h2><?php
			echo $this->editor_title;
		?></h2><?php
		
		$this->_put_message( );
	


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
				
				do_action( 'role_actions_ui' );
				
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
			do_action_ref_array( 'roles_table_head' , array( &$role_api , $roles ) );
			// set default role

			?><tbody><?php
		
			foreach ($resorted_existing_caps as $groupname => $caps ) {
				?><tr><?php
					?><th class="groupname" colspan="<?php echo count($wp_roles->roles)+2 ?>"><?php
						echo $groupname;
					?></th><?php
				?></tr><?php
				$group_slug = sanitize_title( $groupname );
				$this->print_roles_head( $group_slug );

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
	public function role_action_update_users( ) {
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
	public function print_roles_head( $group_slug = '' ) {
		$roles = $this->role_api->get_roles();
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
	public function print_select_default_role( &$role_api , $roles ){
		$default_role = $role_api->get_default_role();
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
	

	public function admin_head_role_editor( ) {
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

	private function _put_message( ) {
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

endif;



if ( ! class_exists( 'RolesRaise_NetworkUI' ) ) :
	class RolesRaise_NetworkUI extends RolesRaise_UI {
		
		public function __construct() {
			parent::__construct();
			$this->role_api = new Network_Role_API();
			$this->editor_title = __('Manage Default Roles','roles-raise');
		}
		
		public function hookup() {
			if ( is_network_admin() ) {
				add_action( 'network_admin_menu', array( &$this , 'add_default_roles_menu' ));
				add_action( 'load-users_page_default_roles' ,array( &$this , 'do_role_actions'));
			
				// style and script
				add_action( 'load-users_page_default_roles' ,array( &$this , 'hookup_admin_head'));

				add_action( 'roles_table_head' ,   array( &$this , 'print_select_default_role' ) , 10 , 2 );
			}
		}

		public function add_default_roles_menu(){
			add_users_page(__('Manage Default Roles','roles-raise'), __('Default Roles','roles-raise'), 'promote_users', 'default_roles', array( &$this , 'manage_roles_screen'));
		}

		
	}
endif;


?>