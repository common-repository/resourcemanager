<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

class Setup extends Configured {
	function meta_links( $links, $file ) {
		if ( $this->config->plugin['name'].'/'.$this->config->plugin['name'].'.php' === $file ) {
			$links[] = '<a href="https://www.paypal.com/paypalme/edvhacker/6" target="_blank" title="' . __( 'Donate', 'resourcemanager' ) . '"><strong>' . __( 'Donate', 'resourcemanager' ) . '</strong> <span class="dashicons dashicons-coffee"></span></a>';
		}
		return $links;
	}
}

Config::$instances['setup'] = new Setup();
add_action( 'plugin_row_meta', [ Config::$instances['setup'], 'meta_links'], 10, 2 );