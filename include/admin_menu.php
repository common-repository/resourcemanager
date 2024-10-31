<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );


class Admin_Menu extends Configured {
	private $menu = [];
	
	private $settings = [
		'settings-group-a' => [
			'group' => '-settings-group-a',
			'settings' => [
				/*'test' => [
					'name' => '-test',
					'descripDate' => 'Test',
					'type' => 'text',
				],*/
				'admin-groups' => [
					'name' => '-admin-groups',
					'descrip' => '',
					'type' => 'select',
					'options' => [
						'' => '',
					],
					'attributes' => [
						'multiple' => true,
					],
				],
				/*'custom-fields' => [
					'name' => '-custom-fields',
					'descrip' => '',
					'type' => 'text',
					'attributes' => [
					],
				],*/
			],
		],
	];
	
	function __construct() {
        parent::__construct();
		
		$this->menu = [
			[
				'name' => __( 'Resource Calendar', 'resourcemanager' ),
				'slug' => '_views',
				'function_name' => 'views',
				'capability' => $this->config->plugin['capability_user'],
			],
			[
				'name' => __( 'Resources', 'resourcemanager' ),
				'slug' => '_resources',
				'function_name' => 'resources',
				'capability' => $this->config->plugin['capability_user'],
			],
			[
				'name' => __( 'Settings', 'resourcemanager' ),
				'slug' => '_settings',
				'function_name' => 'settings',
				'capability' => $this->config->plugin['capability'],
			],
			[
				'name' => __( 'About', 'resourcemanager' ),
				'slug' => '_about',
				'function_name' => 'about',
				'capability' => $this->config->plugin['capability_user'],
			],
		];
    }
	
	private function get_editable_roles() {
		global $wp_roles;

		$all_roles = $wp_roles->roles;
		$editable_roles = \apply_filters('editable_roles', $all_roles);

		return $editable_roles;
	}
	
	public function new_menu(){
		add_menu_page(
			esc_html__( 'Resourcemanager', 'resourcemanager' ),		//page_title
			esc_html__( 'Resourcemanager', 'resourcemanager' ),		//menu_title
			$this->config->plugin['capability'],				//capability
			$this->config->plugin['menu_slug'],					//menu_slug
			'',			//callback function
			'dashicons-calendar-alt',								//icon_url
			21													//position
		);
		
		foreach($this->menu as $link){
			add_submenu_page(
				$this->config->plugin['menu_slug'],
				esc_html__( 'Resourcemanager', 'resourcemanager' ),
				esc_html__( $link['name'], 'resourcemanager' ),
				$link['capability'],
				$this->config->plugin['menu_slug'].$link['slug'],
				[$this, $link['function_name']]
			);
		}
		
		remove_submenu_page($this->config->plugin['menu_slug'],$this->config->plugin['menu_slug']);
	}
	
	private function admin_tabs($active){
		?>
		<div id='bpmnm-nav' class='nav-tab-wrapper'>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Resourcemanager', 'resourcemanager' );?></h1>
			<?php foreach($this->menu as $link){ ?>
				<a class='nav-tab<?php echo esc_html(@$active[$link['slug']])?>' href='./admin.php?page=<?php echo esc_html(@$this->config->plugin['menu_slug'].$link['slug'])?>'><?php echo esc_html__( $link['name'], 'resourcemanager' )?></a>
			<?php } ?>
		</div>
		<hr class="wp-header-end" />
		<?php
	}
	
	/* Head-Tabs */
	public function about(){
		$this->admin_tabs(['_about' => ' nav-tab-active']);
		echo $this->template->load('about.html');
	}
	
	public function settings(){
		$this->admin_tabs(['_settings' => ' nav-tab-active']);
		
		/* load Options */
		$roles = $this->get_editable_roles();
		foreach($roles as $key => $role){
			$this->settings['settings-group-a']['settings']['admin-groups']['options'][$key] = $role['name'];
		}
		
		/* Translations */
		$this->settings['settings-group-a']['settings']['admin-groups'] ['descrip'] = __( 'admin groups', 'resourcemanager' );
		$this->settings['settings-group-a']['settings']['custom-fields']['descrip'] = __( 'custom fields', 'resourcemanager' );
		
		?>
		
		<div class="wrap" id="resourcemanager-settings">
			<form method="post" action="options.php">
				<?php foreach($this->settings as $group){ /* Einstellungs-Gruppen auflisten */ ?>
					<?php settings_fields( $this->config->plugin['name'].$group['group'] ); ?>
					<?php do_settings_sections( $this->config->plugin['name'].$group['group'] ); ?>
					<table class="form-table">
						<?php foreach($group['settings'] as $setting){ /* Einstellungen dieser Gruppe auflisten */ ?>
							<tr valign="top">
								<td scope="row"><strong><?php echo esc_html($setting['descrip'])?></strong></td>
								<?php if($setting['type'] == 'select'){ ?>
									<?php $saved_val = get_option( $this->config->plugin['name'].$setting['name'], [] ); ?>
									<td><select name="<?php echo esc_attr($this->config->plugin['name'].$setting['name'])?>[]"<?php echo ($setting['attributes']['multiple'])?' multiple':''?>>
										<?php foreach($setting['options'] as $option_key => $option_val){ /*Optinen auflisten, falls Einstellung ein Select-Typ ist*/ ?>
											<option value="<?php echo esc_attr( $option_key )?>"
												<?php echo (in_array($option_key,$saved_val))? ' selected="selected"' : ''?>>
													<?php echo esc_attr( $option_val )?>
											</option>
										<?php } ?>
									</select></td>
								<?php } else { ?>
									<?php $saved_val = get_option( $this->config->plugin['name'].$setting['name'], '' ); ?>
									<td><input type="<?php echo esc_attr($this->config->plugin['name'].$setting['type'])?>" name="<?php echo esc_attr($this->config->plugin['name'].$setting['name'])?>" value="<?php echo esc_attr( $saved_val )?>" /></td>
								<?php } ?>
							</tr>
						<?php } ?>
					</table>
				<?php } ?>
				<?php submit_button(); ?>
			</form>
		</div>
		
		<?php 
	}
	public function register_settings(){
		foreach($this->settings as $group){
			foreach($group['settings'] as $setting){
				register_setting( $this->config->plugin['name'].$group['group'], $this->config->plugin['name'].$setting['name'] );
			}
		}
	}
	
	public function current_user_is_resource_admin($res_id = []){
		$return = false;
		
		if(!is_array($res_id)){
			$res_id = [$res_id];
		}
		
		
		$user = wp_get_current_user();
		
		if ( in_array( 'administrator', (array) $user->roles ) ) {
			$return = true;
		}
		
		foreach($res_id as $id){
			$res_owner = Config::$instances['db']->get_resources($id)[0]['ownerRole'];
			if ( in_array( $res_owner, (array) $user->roles ) ) {
				$return = true;
			}
			if($res_owner == "" && current_user_can( 'manage_options' )){
				$return = true;
			}
		}
		
		$admin_groups = get_option( $this->config->plugin['name'].'-admin-groups' );
		foreach($admin_groups as $i => $admin_group_name){
			if ( in_array( $admin_group_name, (array) $user->roles ) ) {
				$return = true;
			}
		}
		
		return $return;
	}
	
	public function current_user_is_view_admin($view_id){
		$user = wp_get_current_user();
		
		$ownerRole = get_post_meta( $view_id, 'ownerRole', true );
		
		if($ownerRole == "" && current_user_can( 'manage_options' )){
			return true;
		}
		
		if ( in_array( 'administrator', (array) $user->roles ) ) {
			return true;
		}
		
		if ( in_array( $ownerRole, (array) $user->roles ) ) {
			return true;
		}
		
		$admin_groups = get_option( $this->config->plugin['name'].'-admin-groups' );
		foreach($admin_groups as $i => $admin_group_name){
			if ( in_array( $admin_group_name, (array) $user->roles ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	public function resources(){
		/* Head-Tabs */
		$this->admin_tabs(['_resources' => ' nav-tab-active']);
		
		?>
		<div id='poststuff'>
			<table class="wp-list-table widefat striped table-view-list" id="resourcemanager-resource-edit">
				<thead>
					<tr>
						<?php $this->print_resources_col_names();?>
					</tr>
				</thead>
				<tbody>
					
				</tbody>
				<tfoot>
					<?php $this->print_resources_col_names();?>
				</tfoot>
			</table>
		</div>
		<?php
		$this->set_confirm_modal();
	}
	
	private function print_resources_col_names(){
	?>
		<tr>
			<td><?php echo esc_html__( 'name', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'extended description', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'number', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'custom fields', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'owner', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'last author', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'last changed', 'resourcemanager' )?></td>
			<td><?php echo esc_html__( 'delete', 'resourcemanager' )?></td>
		</tr>
	<?php
	}
	
	public function views(){
		/* Head-Tabs */
		$this->admin_tabs(['_views' => ' nav-tab-active']);

		/* Template */
		
		?>
		
		<div id='poststuff'>
		
			<table class="wp-list-table widefat striped table-view-list" id="resourcemanager-views-edit">
				<thead>
					<tr>
						<td><?php echo esc_html__( 'designation', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'resources', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'owner', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'author', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'last changed', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'delete', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'shortcode', 'resourcemanager' )?></td>
					</tr>
				</thead>
				<tbody>
					
				</tbody>
				<tfoot>
					<tr>
						<td><?php echo esc_html__( 'designation', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'resources', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'owner', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'author', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'last changed', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'delete', 'resourcemanager' )?></td>
						<td><?php echo esc_html__( 'shortcode', 'resourcemanager' )?></td>
					</tr>
				</tfoot>
			</table>
			
			<p>
				<?php echo esc_html__( 'The following shortcode parameters are possible', 'resourcemanager' )?>:
				<ul>
					<li>cal_id=1234: <?php echo esc_html__( 'ID of this group', 'resourcemanager' )?> (<?php echo esc_html__( 'mandatory', 'resourcemanager' )?>)</li>
					<li>statistics=1 <?php echo esc_html__( 'switch to the statistics-page of this group', 'resourcemanager' )?></li>
				</ul>
			</p>
		</div>
		<?php
		$this->set_confirm_modal();
	}
	
	private function set_confirm_modal(){
		?>
		<div id="resourcemanager-dialog-confirm" title="<?php echo esc_html__( 'Confirm deletion', 'resourcemanager' )?>">
			<p><span class="ui-icon ui-icon-alert"></span><?php echo esc_html__( 'These items will be permanently deleted and cannot be recovered. Are you sure?', 'resourcemanager' )?></p>
		</div>
		<?php
	}
}
Config::$instances['admin_menu'] = new Admin_Menu();
add_action( 'admin_menu', [ Config::$instances['admin_menu'], 'new_menu'] );
add_action( 'admin_init', [ Config::$instances['admin_menu'], 'register_settings'] );

?>