<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

class Add_Scripts extends Configured {
	private function always() {
		wp_register_style(
			'resourcemanager_CSS',
			plugins_url( $this->config->plugin['name'].'/css/style.css' ),
			[],
			'0.9.0d'
		);
		wp_enqueue_style( 'resourcemanager_CSS' );
		
		wp_register_style( 'jQuery_Toast_CSS', plugins_url( $this->config->plugin['name'].'/css/jquery.toast.css' ) );
		wp_enqueue_style( 'jQuery_Toast_CSS' );
		
		wp_register_script(
			'jQuery_Toast_JS',
			plugins_url( $this->config->plugin['name'].'/js/jquery.toast.js' ),
			[ 'jquery' ]
		);
		wp_enqueue_script( 'jQuery_Toast_JS' );
		
		
		wp_register_script(
			'resourcemanager_JS',
			plugins_url( $this->config->plugin['name'].'/js/gui.js' ),
			[ 'wp-i18n', 'jquery' ],
			'0.9.2a'
		);
		wp_set_script_translations(
			'resourcemanager_JS',
			$this->config->plugin['name'],
			$this->config->plugin['path'].'/languages'
		);
		wp_localize_script(
			'resourcemanager_JS',
			'ajax',
            [
				'url' => admin_url( 'admin-ajax.php' ),
				'user_login' => wp_get_current_user()->user_login,
			]
		);
		wp_enqueue_script( 'resourcemanager_JS' );
		
		
		//Add the Select2 CSS file
		wp_enqueue_style( 'select2-css', plugins_url($this->config->plugin['name'] . '/css/select2.min.css'), [], '4.1.0-rc.0' );

		//Add the Select2 JavaScript file
		wp_enqueue_script( 'select2-js', plugins_url($this->config->plugin['name'] . '/js/select2.min.js'), 'jquery', '4.1.0-rc.0' );

		
		
		wp_enqueue_script( 'jquery-ui-core');
		wp_enqueue_script( 'jquery-ui-dialog' ); 
		wp_enqueue_script( 'jquery-ui-sortable' ); 
		
		wp_enqueue_style( 'jquery-ui', plugins_url($this->config->plugin['name'] . '/css/jquery-ui.min.css'), [], '1.13.1' );
	}
	
	function user(){
		$this->always();
		
	}

	function admin() {
		$this->always();
		
		wp_register_script(
			'resourcemanager_admin_JS',
			plugins_url( $this->config->plugin['name'].'/js/admin-gui.js' ),
			[ 'wp-i18n', 'jquery' ],
			'0.9'
		);
		wp_set_script_translations('resourcemanager_admin_JS', $this->config->plugin['name']);
		wp_enqueue_script( 'resourcemanager_admin_JS' );
		
		wp_enqueue_script( 'select2sortable-js', plugins_url($this->config->plugin['name'] . '/js/select2.sortable.min.js'), 'jQuery', '1.1' );
		
		wp_enqueue_editor();
		wp_enqueue_media();
	}
	
	function plugin_load_textdomain() {
		load_plugin_textdomain( $this->config->plugin['name'], false, $this->config->plugin['name'] . '/languages/' );
	}
}

Config::$instances['add_scripts'] = new Add_Scripts();
add_action('wp_enqueue_scripts', [ Config::$instances['add_scripts'], 'user']);
add_action('admin_enqueue_scripts', [ Config::$instances['add_scripts'], 'admin']);

add_action( 'init', [ Config::$instances['add_scripts'], 'plugin_load_textdomain'] );
?>