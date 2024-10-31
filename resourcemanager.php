<?php
/**
 * Resourcemanager Wordpress-Plugin
 * 
 * 
 * @author		Alexander Hacker <info@edv-hacker.de>
 * @license		GPLv3
 * @link		https://edv-hacker.de
 * @copyright	2020 Alexander Hacker
 * 
 * @wordpress-plugin
 * Plugin Name:		Resourcemanager
 * Description:		Visualizes and manages definable resources in an compact month view.
 * Version:			1.1.0
 * Author:			Alexander Hacker
 * Author URI:		https://edv-hacker.de
 * Text Domain:		resourcemanager
 * Domain Path:		/languages
 * License:			propritary
 * License URI:		https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

/* Config */
class Config {
	public $plugin;
	public static $instances = [];
	
	function __construct(){
		$this->plugin['name'] = "resourcemanager";
		$this->plugin['db_name'] = "ha_resourcemanager";
		$this->plugin['type'] = "resourcemanager";
		$this->plugin['post_type_view'] = 'resman_view';
		$this->plugin['capability'] = 'manage_options';
		$this->plugin['capability_user'] = 'read';
		#$this->plugin['replaceReplace'] = "[".$this->plugin['type']." id={{id}}]";

		$this->plugin['path'] = WP_PLUGIN_DIR."/".$this->plugin['name'];
		$this->plugin['menu_slug'] = $this->plugin['name'];
	}
}
class Configured {
	protected $config;
	protected $template;
	
	function __construct(){
		$this->config = new Config();
		$this->template = new \ha_Template($this->config);
	}
}

$path = (new Config())->plugin['path'];


/* Modules */
#require_once($path . '/ha_lib/html_datatypes.class.php');

require_once($path . '/ha_lib/template.class.php');

require_once($path . '/include/recurringDates.class.php');

require_once($path . '/include/ajax.php');
require_once($path . '/include/add_scripts.php');
require_once($path . '/include/db.php');
include_once($path . '/include/setup.php');

require_once($path . '/include/admin_menu.php');
require_once($path . '/include/shortcode_handler.php');



?>