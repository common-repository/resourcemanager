<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

class DB extends Configured {
	private $db_version = '2.5.3';
	
	private $post_type_view = 'resman_view';
		
	public function create_db(){
		global $wpdb;
		$saved_version = get_option( $this->config->plugin['db_name'] . 'db_version', '0.0' );
		
		if ( version_compare( $saved_version, $this->db_version ) == -1) {

			$charset_collate = $wpdb->get_charset_collate();
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			
			//Tabelle der Ressourcen
			$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
			$sql = "CREATE TABLE $table_name (
			  `id` MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
			  `name` tinytext NOT NULL,
			  `descripRes` TEXT NOT NULL,
			  `customFields` TEXT NOT NULL,
			  `countable` MEDIUMINT(9) DEFAULT '1' NOT NULL,
			  `ownerRole` VARCHAR(50) NOT NULL,
			  `lastChanged` TIMESTAMP on update CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
			  `lastChangedUser` BIGINT(20) NOT NULL,
			  PRIMARY KEY  (`id`)
			) $charset_collate;";
			dbDelta( $sql );
			
			//Tabelle der Ressourcen-Belegungen
			$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_bookings';
			$sql = "CREATE TABLE $table_name (
			  `resource_id` MEDIUMINT(9) NOT NULL,
			  `type` VARCHAR(8) DEFAULT 'INSERT' NOT NULL,
			  `from` DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  `to` DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  `bookingUser` VARCHAR(50) NOT NULL,
			  `userType` VARCHAR(8) NOT NULL DEFAULT 'user',
			  `customFieldsData` TEXT NOT NULL,
			  `reserved_number` MEDIUMINT(9) DEFAULT '1' NOT NULL,
			  `descripDate` TEXT NOT NULL,
			  `recurringPattern` TEXT NOT NULL,
			  `persons` MEDIUMINT(9) NOT NULL,
			  `lastChanged` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
			  `lastChangedUser` BIGINT(20) NOT NULL,
			  PRIMARY KEY  (`resource_id`, `from`, `lastChanged`)
			) $charset_collate;";
			dbDelta( $sql );
			
			update_option( $this->config->plugin['db_name'] . 'db_version', $this->db_version );
		}
	}
	
	public function read_resources_from_db($cal_id = -1){
		if($cal_id == -1){ return []; }
		
		$ress_full = $this->read_resources_from_db_per_view($cal_id);
		
		$ress = [];
		foreach($ress_full as $row){
			$ress[$row->id] = $row->name;
		}
		
		return $ress;
	}
	
	public function read_resource_from_db($res_id){
		global $wpdb;
		
		$table_resources = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		$res = $wpdb->get_row("
			SELECT      *
			FROM        $table_resources
			WHERE		id = '$res_id'
		");
		
		return $res;
	}
	
	private function read_resources_from_db_per_view($cal_id){
		global $wpdb;
		
		$table_resources = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		$view = $this->read_view($cal_id);
		
		$resources = [];
		foreach($view['resources_arr'] as $ress_id){
			$resources[] = $table_resources.".id=".$ress_id;
		}
		$where = (sizeof($resources) > 0) ? implode(" OR ",$resources) : "TRUE";
		
		
		$res_results = $wpdb->get_results("
			SELECT      *
			FROM        $table_resources
			WHERE		$where
		");
		
		
		$ress = [];
		foreach($res_results as $row){
			$ress[$row->id] = $row;
		}
		
		return $ress;
	}
	
	public function read_resources_from_db_full(){
		global $wpdb;
		
		$table_resources	= $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		$ress_results = $wpdb->get_results( 
			"
				SELECT      *
				FROM        $table_resources
				ORDER BY    id
			"
		);
		
		$ress = [];
		foreach($ress_results as $row){
			$ress[$row->id] = $row;
		}
		
		return $ress;
	}
	
	public function read_booking_from_db($res_id,$from,$include_deleted = false){
		global $wpdb;
		
		$table_bookings		= $wpdb->prefix . $this->config->plugin['db_name'] . '_bookings';
		$table_resources	= $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		$qry = "SELECT *
				FROM `$table_bookings` AS n
				LEFT JOIN `$table_resources` ON n.resource_id = `$table_resources`.id
				WHERE
					(
						`from` = %s
					)
					AND (n.lastChanged = (
						SELECT MAX(lastChanged)
						FROM `$table_bookings`
						WHERE resource_id = n.resource_id AND `from` = n.`from`
					))
					AND ( n.resource_id = $res_id )"
				.($include_deleted ? "" : " HAVING n.type = 'INSERT' OR n.type = 'ASKFOR'");
				
		$qry_prep = $wpdb->prepare(
					$qry,
					$from,
				);
				
		$booking_results = $wpdb->get_row( $qry_prep );
		
		if($booking_results){
			return $booking_results;
		}
		return (object) [
			'type' => 'NULL'
		];
	}
	
	public function read_bookings_from_db($resources,$from,$to,$include_all_ask_for = 0){
		global $wpdb;
		
		$table_bookings		= $wpdb->prefix . $this->config->plugin['db_name'] . '_bookings';
		$table_resources	= $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		$requested_resources = [];
		foreach($resources as $i => $ress_name){
			$requested_resources[] = "n.resource_id = ".$i;
		}
		$requested_resources_where = implode(" OR ",$requested_resources);
		
		$from .= (strlen($from) > 10) ? '' : ' 00:00:00';
		$to   .= (strlen($to)   > 10) ? '' : ' 23:59:59';
		
		$qry_prep = $wpdb->prepare(
			"SELECT *
			FROM `$table_bookings` AS n
			LEFT JOIN `$table_resources` ON n.resource_id = `$table_resources`.id
			WHERE
				(
					(  /* in given time frame */
						(`from` >= %s AND `from` <=  %s)
						 OR (`to` >= %s AND `to` <= %s)
						 OR (`from` >= %s AND `recurringPattern` <> '')
					)
					".($include_all_ask_for ? "OR n.type = 'ASKFOR'" : "")."
					OR n.recurringPattern <> ''
				)
				AND (n.lastChanged = (
					SELECT MAX(lastChanged)
					FROM `$table_bookings`
					WHERE resource_id = n.resource_id AND `from` = n.`from`
				))
				AND ( $requested_resources_where )
			HAVING /* HAVING needed to filter from the filtered (just the newest of one key) datasets */
				n.type = 'INSERT' OR
				n.type = 'ASKFOR' ;",
			$from,
			$to,
			$from,
			$to,
			$from
		);
		
		$booking_results = $wpdb->get_results( $qry_prep );
				
		return $booking_results;
		
	}
	
	public function write_booking($args){
		global $wpdb;
		
		$resources = [intval($args['resource_id']) => ''];
		$from = (new \DateTime($args['from']))->format('Y-m-d H:i:s');
		$to   = (new \DateTime($args['to']))->format('Y-m-d H:i:s');
		
		
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($args['resource_id']);
		
		if(
			   ( $current_user_is_resource_admin )
			|| ( $args['type'] == 'ASKFOR' && \is_user_logged_in() )
		){
			$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_bookings';
			
			$data = [
				'resource_id' => intval($args['resource_id']),
				'type' => $args['type'],
				'from' => $from,
				'to' => $to,
				'recurringPattern' => $args['recurringPattern'],
				'bookingUser' => $args['bookingUser'],
				'userType' => $args['userType'],
				'lastChangedUser' => get_current_user_id(),
				'customFieldsData' => $args['customFieldsData'],
				'reserved_number' => $args['reserved_number'],
			];
			$format = [
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			];
			
			#print_r($data);
			
			$wpdb->insert($table_name,$data,$format);
		}
	}
	
	public function get_resources($i = -1){
		global $wpdb;
		$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		if($i == -1){
			$qry = "SELECT * FROM ".$table_name;
		}
		else{
			$qry = "SELECT * FROM ".$table_name." WHERE id = ".intval($i);
		}
		
		$results = $wpdb->get_results( $qry, ARRAY_A );
		
		return $results;
	}

	private function current_user_is_resource_admin($id){
		return Config::$instances['admin_menu']->current_user_is_resource_admin($id) ? true : false ;
	}
	
	public function write_resource($data){
		global $wpdb;
		$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		if($this->current_user_is_resource_admin($data['id'])){
			$format = [];
			foreach($data as $key => $val){
				if(
					$key == 'id' ||
					$key == 'lastChangedUser'
				){
					$data[$key] = intval($data[$key]);
					$format[] = '%d';
				}
				else{
					$data[$key] = stripslashes(trim($data[$key]));
					$format[] = '%s';
				}
				
				if($key == 'customFields'){
					$data[$key] = str_replace(['\r','\n'],['',''],$data[$key]);
				}
			}
			
			$data['lastChangedUser'] = get_current_user_id();
			$format[] = '%d';
			
			
			if(isset($data['id'])){
				if($data['id'] != 0){
					$wpdb->replace($table_name,$data,$format);
				}
				else{ $wpdb->insert($table_name,$data,$format); }
			}
			else{
				$wpdb->insert($table_name,$data,$format);
			}
			
			return [$data,$format];
		}
	}
	
	public function delete_resource($id){
		global $wpdb;
		
		$table_name = $wpdb->prefix . $this->config->plugin['db_name'] . '_resources';
		
		if($this->current_user_is_resource_admin($id)){
			$data = [
				'id' => intval($id),
			];
			$format = [
				'%d',
			];
			
			$wpdb->delete($table_name,$data,$format);
		}
	}
	
	public function get_views(){
		$return = [];
		
		$args = [
			'post_type' => $this->post_type_view,
			'post_status' => 'publish',
			#'post_count' => -1,
			'orderby' => 'ID',
			'order' => 'ASC'
		];
		
		$query = new \WP_Query($args);
		
		while ($query->have_posts()) {
			$query->the_post();
			$post_id = get_the_ID();
			$post = get_post($post_id);
			
			$return[$post_id] = [
				'content' => json_decode($post->post_content),
				'author' => $post->post_author,
				'lastChanged' => date_i18n(get_option( 'date_format' ), strtotime($post->post_modified)),
				'title' => $post->post_title,
				'ownerRole' => get_post_meta( $post_id, 'ownerRole', true ),
			];
		}
		
		wp_reset_query();
		
		return $return;
	}
	
	public function read_view($id){
		$post = get_post($id);
		if($post->post_type == $this->post_type_view){
			return [
				'title' => $post->post_title,
				'resources_arr' => json_decode($post->post_content),
			];
		}
		
		if($id == 0){
			return [
				'title' => '',
				'resources_arr' => (object) [],
			];
		}
		
		return [];
	}
	
	public function write_view($title,$content_arr,$id = 0){
		/*
			$content_arr = [
				resources => [
					resource1,
					resource2,
					...
				],
			];
		*/
		$content = json_encode($content_arr);
		if($content == "{}"){ $content = "[]"; }
		
		$postarr = [
			'ID' => $id,
			'post_content' => $content,
			'post_status' => 'publish',
			'post_type' => $this->config->plugin['post_type_view'],
			'post_title' => $title,
			
			//'post_author' => default,
			//'post_date' => default,
			//'post_date_gmt' => default,
			//'post_content_filtered' => default,
			//'post_excerpt' => default,
			//'comment_status' => default,
			//'ping_status' => default,
			//'post_password' => default,
			//'post_name' => default,
			//'to_ping' => default,
			//'pinged' => default,
			//'post_modified' => default,
			//'post_modified_gmt' => default,
			//'menu_order' => default,
			//'post_mime_type' => default,
			//'guid' => default,
			//'import_id' => default,
			//'post_category' => default,
			//'tags_input' => default,
			//'tax_input' => default,
			//'meta_input' => default,
		];
		
		
		if($postarr['ID'] == 0){
			wp_insert_post( $postarr, 1 );
		}
		else{
			$post = get_post($id);
			if($post->post_type == $this->config->plugin['post_type_view']){
				wp_update_post( $postarr );
			}
		}
	}
	
	public function delete_view($id){
		$post = get_post($id);
		if($post->post_type == $this->config->plugin['post_type_view']){
			wp_delete_post( $id, true );
		}
	}
}

Config::$instances['db'] = new DB();
add_action('init', [ Config::$instances['db'], 'create_db']);
?>
