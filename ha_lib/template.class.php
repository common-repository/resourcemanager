<?php
if(!class_exists('ha_Template')){
	class ha_Template{
		private $config;
		
		function __construct($config){
			$this->config = $config;
		}
		
		public function load($template,$replace = []){
			$path = $this->config->plugin['path'].'/templates/'.$template;
			return file_get_contents($path);
		}
	}
}
?>