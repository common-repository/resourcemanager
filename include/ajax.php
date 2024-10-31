<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

class Ajax extends Configured{
	
	private $json_users = [];
	private $json_groups = [];
	private $res_id_list = [];
	
	private $error = [];
	
	private function get_res_ordered(){
		$cal_id = $this->sanitize_int($_POST['cal_id']);
		$res = Config::$instances['db']->read_view($cal_id);
		return $res['resources_arr'];
	}
	
	private function current_user_is_resource_admin($res_id){
		return Config::$instances['admin_menu']->current_user_is_resource_admin($res_id);
	}
	
	private function resolve_recurring_pattern($bookings,$from,$to){
		//resolve
		foreach($bookings as $i => $row){
			if($row->recurringPattern){
				$recurringDates = (new recurringDate($row->from,$row->recurringPattern,$from,$to))->get_list();
				
				foreach($recurringDates as $j => $new_date){
					$new_row = clone $row;
					$row_time_difference = strtotime($row->to) - strtotime($row->from);
					
					$new_row->recurringOriginalFrom = $new_row->from;
					$new_row->recurringOriginalTo = $new_row->to;
					$new_row->recurringOriginalRecurringPattern = $new_row->recurringPattern;
					
					$new_row->from = $new_date.' '.date('H:i:s', strtotime($row->from));
					$new_row->to = date('Y-m-d H:i:s', strtotime($new_row->from) + $row_time_difference);
					$new_row->recurringPattern = '';
					$bookings[] = $new_row;
				}
				unset($bookings[$i]);
			}
		}
		
		//cut, what is not needed
		$day = 60*60*24;
		foreach($bookings as $i => $row){
			if(
				(
					strtotime($row->from) > strtotime($to) + $day
					|| strtotime($row->to) < strtotime($from)
				)
				&& $row->type != "ASKFOR"
			){
				unset($bookings[$i]);
			}
		}
		
		return array_values($bookings);
	}
	
	private function read_bookings_from_db($res,$from,$to,$include_all_ask_for = 0){
		$bookings = Config::$instances['db']->read_bookings_from_db($res,$from,$to,$include_all_ask_for);
		$resolved_bookings =  $this->resolve_recurring_pattern($bookings,$from,$to);
		return $resolved_bookings;
	}
	
	private function get_time_data(){
		$from = sanitize_key($_POST['from']);
		$to = sanitize_key($_POST['to']);
		$cal_id = $this->sanitize_int($_POST['cal_id']);
		
		$data = [];
		
		$res = Config::$instances['db']->read_resources_from_db($cal_id);
				
		foreach($res as $id => $res_name){
			$resource_from_db = Config::$instances['db']->read_resource_from_db($id);
			
			$data[$id] = [
				'meta' => [
					'id' => $id,
					'name' => $res_name,
					'adminView' => $this->current_user_is_resource_admin($id),
					'customFields' => json_decode($resource_from_db->customFields),
					'countable' => $resource_from_db->countable,
					'descripRes' => $resource_from_db->descripRes,
				],
			];
			$this->res_id_list[] = $id;
		}
		
		$bookings = $this->read_bookings_from_db($res,$from,$to,1);
		
		foreach($bookings as $i => $row){
			$ts = strtotime($row->from);
			$date = date("Y-m-d",$ts);
			
			$res_id = $row->resource_id;
			
			$isForCurrentUser = $this->is_for_current_user($row);
			
			//Wenn Admin oder Inhaber genau dieser Buchung: descripDate, bookingUser, persons
			$customFieldsData = false;
			if($this->current_user_is_resource_admin($res_id) || $isForCurrentUser){
				$bookingUser = $row->bookingUser;
				$userType = $row->userType;
				
				$customFieldsData = json_decode($row->customFieldsData);
			}
			else{
				$bookingUser = "";
				$userType = "";
				
				$customFieldsData = (object)[];
			}
			
			$data[$res_id][$date][] = (object) [
				'from' => $row->from,
				'to' => $row->to,
				'bookingUser' => $bookingUser,
				'userType' => $userType,
				'isForCurrentUser' => $isForCurrentUser,
				'type' => $row->type,
				'customFieldsData' => $customFieldsData,
				'reserved_number' => $row->reserved_number,
				'original' => (@$row->recurringOriginalFrom) ? (object) [
					'from' => $row->recurringOriginalFrom,
					'to' => $row->recurringOriginalTo,
					'pattern' => json_decode($row->recurringOriginalRecurringPattern),
				] : (object) [],
			];
		}
		
		return $data;
	}
	
	private function get_resource_owners($res_id){
		$res_owner_role = Config::$instances['db']->get_resources($res_id)[0]['ownerRole'];
		if($res_owner_role == ''){
			return [];
		}
		
		$args = array(
			'role'    => $res_owner_role,
			'orderby' => 'user_nicename',
			'order'   => 'ASC'
		);
		$users = \get_users( $args );
		
		return $users;
	}
	
	private function user_has_mail_activated($user_id){
		if($user_id != 847){
			//Administrator. TODO: Einstellbar, welche Benutzer ausgenommen werden
			return true;
		}
		return false;
	}
	
	private function send_mail_to_client_of_booking($res_id,$from,$subject,$mail){
		$include_deleted = true;
		$booking_dataset = Config::$instances['db']->read_booking_from_db($res_id,$from,$include_deleted);
		
		$client_user_name = $booking_dataset->bookingUser;
		$user = get_user_by('login',$client_user_name);
		if($user){
			if($this->user_has_mail_activated($user->ID)){
				\wp_mail(
					$user->user_email,
					$subject,
					$mail,
				);
			}
		}
	}
	
	private function send_mail_to_owners_of_ressource($res_id,$subject,$mail){
		$users = $this->get_resource_owners($res_id);
		foreach($users as $user){
			if($this->user_has_mail_activated($user->ID)){
				/* TODO: Benutzergruppen sind noch zu groß
				\wp_mail(
					$user->user_email,
					$subject,
					$mail,
				);*/
			}
		}
	}
	
	private function is_current_user($user_login){
		$current_user = wp_get_current_user();
		return $user_login == $current_user->user_login;
	}
	
	private function is_for_current_user($row){
		$current_user = wp_get_current_user();
		
		if(
			$row->userType == 'user' &&
			$row->bookingUser == $current_user->user_login &&
			$row->bookingUser != ""
		){ return true; }
		if(
			$row->userType == 'group' &&
			in_array($row->bookingUser, $current_user->roles)
		){ return true; }
		
		return false;
	}
	
	public function load_time() {
		echo json_encode([
			'data' => $this->get_time_data(),
			'users' => $this->get_users(),
			'groups' => $this->get_groups(),
			'res_ordered' => $this->get_res_ordered(),
		]);
		
		wp_die();
	}
	
	private function sanitize_time($t){
		if($t){
			$ts = strtotime($t);
			return date("Y-m-d H:i:s", $ts);
		}
		return false;
	}
	
	private function sanitize_type($t){
		$t = strtoupper($t);
		if($t == "INSERT" || $t == "DELETE" || $t == "ASKFOR"){ return $t; }
		return false;
	}
	
	private function sanitize_user_type($t){
		$t = strtolower($t);
		if($t == "user" || $t == "group"){ return $t; }
		return false;
	}
	
	private function sanitize_int($i){
		return intval($i);
	}
	
	private function sanitize_unit($u){
		if($u == "day") return "day";
		if($u == "week") return "week";
		if($u == "month") return "month";
		if($u == "year") return "year";
		return false;
	}
	
	private function get_previous_second($dt){
		$timestamp = strtotime($dt);
		$timestamp--;
		return date('Y-m-d H:i:s',$timestamp);
	}
	
	private function does_booking_collide($args){
		if($args['object_before']->type == "NULL"){ return 0; }
		
		$bookings = $this->read_bookings_from_db(
			[$args['current_booking_data']['resource_id'] => ''],
			$args['current_booking_data']['from'],
			$args['current_booking_data']['to']
		);
		
		#print_r($args['current_booking_data']['from']." - ".$args['current_booking_data']['to']);
		#print_r($bookings);
		
		if($bookings){
			$max_number = $bookings[0]->countable;
			
			foreach($bookings as $i => $booking){
				if(
						$booking->resource_id == $args['object_before']->resource_id
					&&	$booking->from == $args['object_before']->from
				){
					unset($bookings[$i]);
				}
			}
			$bookings = array_values($bookings);
			
			//wenns nur eine ressource gibt: einfacher behandeln
			if( $max_number == 1 && sizeof($bookings) > 0){
				return 1;
			}
			
			//sonst: schau mal, wie viele ressourcen in dem zeitraum jeweils maximal belegt sind
			$max_current_number = 0;
			foreach($bookings as $i => $booking){
				$max_current_number = max($max_current_number, $this->count_overlapping_numbers($i,$bookings));
				if($max_current_number + $args['current_booking_data']['reserved_number'] > $max_number ){
					return 2;
				}
			}
		}
		
		return 0;
	}
	
	private function count_overlapping_numbers($i,$bookings){
				
		$current_number = $bookings[$i]->reserved_number;
		foreach($bookings as $j => $row){
			if(
				$i != $j
				&& strtotime($bookings[$j]->from) > strtotime($row->from)
				&& strtotime($bookings[$j]->from) < strtotime($row->to)
			){
				$current_number += $row->reserved_number;
			}
		}
		
		return $current_number;
	}
	
	public function save_time() {
		if(!isset($_POST['userType'])){ $_POST['userType'] = 'user'; }
		
		$resource_id	= $this->sanitize_int( $_POST['resource_id'] );
		$res_id_before	= $this->sanitize_int( $_POST['res_id_before'] );
		$type			= $this->sanitize_type( $_POST['type'] );
		$from			= $this->sanitize_time( $_POST['from_d']." ".$_POST['from_t'] );
		$from_before	= $this->sanitize_time( $_POST['from_d_hidden']." ".$_POST['from_t_hidden'] );
		$to				= $this->sanitize_time( $_POST['to_d']." ".$_POST['to_t'] );
		$bookingUser	= sanitize_user( $_POST['bookingUser'] );
		$userType		= $this->sanitize_user_type( $_POST['userType'] );
		
		$recurring		= $this->sanitize_int($_POST['recurring']);
		$every_i		= $this->sanitize_int($_POST['every_i']);
		$every_u		= $this->sanitize_unit($_POST['every_u']);
		$till_d			= $this->sanitize_time($_POST['till_d']);
		
		$reserved_number		= isset($_POST['reserved_number']) ? max(1,$this->sanitize_int($_POST['reserved_number'])) : 1;
		
		$recurringPattern = ($recurring && $every_i>0 && $every_u && $till_d) ? json_encode([$every_i,$every_u,[],$till_d]) : false;
		
		$object_before	= Config::$instances['db']->read_booking_from_db($resource_id,$from_before);
		$type_before	= $object_before->type;
		
		$customFields	= json_decode(Config::$instances['db']->read_resource_from_db($resource_id)->customFields);
		if(!$customFields){ $customFields = (object) []; }
		
		$customFieldsData = [];
		foreach($customFields as $key => $definition){
			if($definition == 'text'){ $customFieldsData[$key] = sanitize_text_field($_POST[$key]); }
			else{
				$customFieldsData[$key] = sanitize_text_field($_POST[$key]);
			}
		}
		
		$customFieldsData = json_encode((object) $customFieldsData);
		
		
		
		$resources = [$resource_id => ''];
		$from = (new \DateTime($from))->format('Y-m-d H:i:s');
		$to   = (new \DateTime($to))->format('Y-m-d H:i:s');
		
		
		$current_booking_data = [
			'resource_id'		=> $resource_id,
			'type' 				=> $type,
			'from'				=> $from,
			'to' 				=> $to,
			'recurringPattern'	=> $recurringPattern,
			'bookingUser' 		=> $bookingUser,
			'userType' 			=> $userType,
			'customFieldsData' 	=> $customFieldsData,
			'reserved_number'	=> $reserved_number,
		];
		
		$booking_collides = $this->does_booking_collide([
			'object_before' => $object_before,
			'current_booking_data' => $current_booking_data,
		]);
		
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($resource_id);
		
		$current_user_is_booking_user = $this->is_current_user($bookingUser);
		
		if(
			   ( $type == 'DELETE' &&  $current_user_is_resource_admin )
			|| ( $type == 'DELETE' &&  $current_user_is_booking_user )
			|| ( $booking_collides == 0 && $current_user_is_resource_admin )
			|| ( $booking_collides == 0 && $type == 'ASKFOR' && \is_user_logged_in() )
		){
		
			if(
				($type == 'INSERT' || $type == 'ASKFOR')
				&& (
					$from != $from_before
					|| $resource_id != $res_id_before
				)
				&& $object_before->type != "NULL"
			){
				$data = [
					'resource_id'		=> $res_id_before,
					'type' 				=> 'DELETE',
					'from'				=> $from_before,
					'to' 				=> $to,
					'recurringPattern'	=> $recurringPattern,
					'bookingUser' 		=> $bookingUser,
					'userType' 			=> $userType,
					'customFieldsData' 	=> $customFieldsData,
					'reserved_number'	=> $reserved_number,
				];
				Config::$instances['db']->write_booking($data);
			}
			
			Config::$instances['db']->write_booking($current_booking_data);
			
			
			$this->send_mails_if_necessary($type_before,$type,$resource_id,$from);
		}
		else{
			if( $booking_collides == 1 ){ $this->error[] = esc_html__( 'booking overlaps another booking', 'resourcemanager' ); }
			if( $booking_collides == 2 ){ $this->error[] = esc_html__( 'too many items were tried to reserve', 'resourcemanager' ); }
		}
		
		if($this->error){
			wp_die(esc_html($this->error[0]));
		}
		
		wp_die();
	}
	
	private function send_mails_if_necessary($type_before,$type,$res_id,$from){
		$res_name = Config::$instances['db']->read_resource_from_db($res_id)->name;
		
		$booking = Config::$instances['db']->read_booking_from_db($res_id,$from,true);
				
		if($type_before == "ASKFOR" && $type == "INSERT"){
			//Client benachrichtigen, dass seine Buchung genehmigt wurde. Oder verändert.
			$this->send_mail_to_client_of_booking(
				$res_id,
				$from,
				esc_html__( 'Resourcemanager', 'resourcemanager' )
					.': '.esc_html__( 'Resource request accepted', 'resourcemanager' ),
				esc_html__( 'Your request for', 'resourcemanager' )
					.' '.esc_html(str_replace("<br />","",$res_name))
					.' ('.esc_html($booking->from).' - '.esc_html($booking->to).')'
					.' '.esc_html__( 'was accepted.', 'resourcemanager' ),
			);
		}
		if($type_before == "ASKFOR" && $type == "DELETE"){
			//Client benachrichtigen, dass seine Buchung abgelehnt wurde.
			$this->send_mail_to_client_of_booking(
				$res_id,
				$from,
				esc_html__( 'Resourcemanager', 'resourcemanager' )
					.': '.esc_html__( 'Resource request rejected', 'resourcemanager' ),
				esc_html__( 'Your request for', 'resourcemanager' )
					.' '.esc_html(str_replace("<br />","",$res_name))
					.' ('.esc_html($booking->from).' - '.esc_html($booking->to).')'
					.' '.esc_html__( 'was rejected.', 'resourcemanager' ),
			);
		}
		if($type_before == "NULL" && $type == "ASKFOR"){
			//Owner benachrichtigen, dass eine Buchung eingegangen ist.
			$this->send_mail_to_owners_of_ressource(
				$res_id,
				esc_html__( 'Resourcemanager', 'resourcemanager' )
					.': '.esc_html__( 'Resource requested', 'resourcemanager' ),
				esc_html__( 'Someone requested', 'resourcemanager' )
					.' '.esc_html(str_replace("<br />","",$res_name))
					.' ('.esc_html($booking->from).' - '.esc_html($booking->to).')'
					.'. '.esc_html__( 'Please review the request and accept or reject it.', 'resourcemanager' ),
			);
		}
		if($type_before == "NULL" && $type == "INSERT"){
			$this->send_mail_to_client_of_booking(
				$res_id,
				$from,
				esc_html__( 'Resourcemanager', 'resourcemanager' )
					.': '.esc_html__( 'Resource request accepted', 'resourcemanager' ),
				esc_html__( 'Your request for', 'resourcemanager' )
					.' '.esc_html(str_replace("<br />","",$res_name))
					.' ('.esc_html($booking->from).' - '.esc_html($booking->to).')'
					.' '.esc_html__( 'was registered for you.', 'resourcemanager' ),
			);
		}
		if($type_before == "INSERT" && $type == "DELETE"){
			$this->send_mail_to_client_of_booking(
				$res_id,
				$from,
				esc_html__( 'Resourcemanager', 'resourcemanager' )
					.': '.esc_html__( 'Resource reservation deleted', 'resourcemanager' ),
				esc_html__( 'Your request for', 'resourcemanager' )
					.' '.esc_html(str_replace("<br />","",$res_name))
					.' ('.esc_html($booking->from).' - '.esc_html($booking->to).')'
					.' '.esc_html__( 'was deleted.', 'resourcemanager' ),
			);
		}
	}
	
	private function convert_display_name($name){
		if(stristr($name,',') !== false){ return $name; }
		
		$name_ar = explode(" ",$name);
		if(sizeof($name_ar) != 2){ return $name; }
		
		if(is_numeric($name_ar[1])){ return $name; }
		if(is_numeric($name_ar[0])){ return $name; }
		
		return trim($name_ar[1]).", ".trim($name_ar[0]);
	}
	
	private function get_groups(){
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($this->res_id_list) ? true : false ;
		
		//WP Groups
		$avail_roles = wp_roles()->get_names();
		$current_user = wp_get_current_user();
		foreach($avail_roles as $role_slug => $role_name){
			if( in_array($role_slug, (array) $current_user->roles) || $current_user_is_resource_admin) {
				$this->json_groups[$role_slug] = esc_html($role_name);
			}
		}
		
		asort($this->json_groups);
		
		return $this->json_groups;
	}
	
	private function get_users(){
		global $wp_roles;
		
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($this->res_id_list) ? true : false ;
		
		//leerer User
		$this->json_users[''] = [''];
		
		//WP User
		$wp_users = get_users();
		foreach($wp_users as $user){
			if(
				(
					$current_user_is_resource_admin
					|| ($user->ID == get_current_user_id() && $user->ID > 0)
				)
			){
				$this->json_users[$user->user_login] = esc_html($this->convert_display_name($user->display_name));
			}
		}
		
		asort($this->json_users);
		
		return $this->json_users;
	}
	
	private function get_roles(){
		if(\is_admin()){
			global $wp_roles;

			if ( ! isset( $wp_roles ) ){
				$wp_roles = new WP_Roles();
			}
			
			$json_roles = $wp_roles->get_names();

			return $wp_roles->get_names();
		}
		return [];
	}
	
	private function get_resources(){
		$res_full = Config::$instances['db']->read_resources_from_db_full();
		
		foreach($res_full as $id => $row){
			$res_full[$id]->admin = $this->current_user_is_resource_admin($id);
			
			$lastChangedUserData = get_userdata( $row->lastChangedUser );
			
			$res_full[$id]->editUserLink = '<a href="'. get_edit_user_link( $row->lastChangedUser ) .'">'. esc_attr( $lastChangedUserData->user_nicename ) .'</a>';
			
			$res_full[$id]->lastChangedI18n = date_i18n(get_option( 'date_format' ), strtotime($row->lastChanged));
			
		}
		
		return $res_full;
	}
	
	public function load_admin_data(){
		echo json_encode([
			'resources' => $this->get_resources(),
			'roles' => $this->get_roles(),
		]);
		
		wp_die();
	}
	
	private function sanitize_field_content($field_name,$field_content){
		if($field_name == 'name'){ return sanitize_text_field($field_content); }
		if($field_name == 'ownerRole'){ return sanitize_text_field($field_content); }
		return $field_content;
	}
	
	public function save_resource_attr(){
		$id = $this->sanitize_int($_POST['id']);
		$field_name = sanitize_text_field($_POST['field_name']);
		$field_content = $this->sanitize_field_content($field_name,$_POST['field_content']);
		
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($id) ? true : false ;
		
		if($current_user_is_resource_admin){
		
			$data = Config::$instances['db']->get_resources($id)[0];
			unset($data['lastChanged']);
			unset($data['lastChangedUser']);
			$data[$field_name] = $field_content;
			
			Config::$instances['db']->write_resource($data);
		}
		
		$this->load_admin_data();
		
		wp_die();
	}
	
	public function delete_resource(){
		$id = $this->sanitize_int($_POST['id']);
		$current_user_is_resource_admin = $this->current_user_is_resource_admin($id) ? true : false ;
		
		if($current_user_is_resource_admin){
			Config::$instances['db']->delete_resource($id);
		}
		
		$this->load_admin_data();
		
		wp_die();
	}
	
	public function load_views(){
		echo json_encode([
			'views' => $this->get_views(),
			'resources' => $this->get_resources(),
			'roles' => $this->get_roles(),
		]);
		
		wp_die();
	}
	
	private function get_views(){
		$views = Config::$instances['db']->get_views();
		
		foreach($views as $id => $data){
			#$current_user_is_resource_admin = Config::$instances['admin_menu']->current_user_is_resource_admin($id) ? true : false ;
			
			#$res_full[$id]->admin = $current_user_is_resource_admin;
			
			$lastChangedUserData = get_userdata( $data['author'] );
			
			
			$views[$id]['editUserLink'] = '<a href="'. get_edit_user_link( @$data->author ) .'">'. esc_attr( $lastChangedUserData->user_nicename ) .'</a>';
			
			$views[$id]['ownerRole'] = @$data['ownerRole'];
			
			$views[$id]['admin'] = Config::$instances['admin_menu']->current_user_is_view_admin($id);
			
			
			#$data[$id]->lastChangedI18n = date_i18n(get_option( 'date_format' ), strtotime($row->lastChanged));
			
		}
		
		return $views;
	}
	
	public function save_view_attr(){
		$id = $this->sanitize_int($_POST['id']);
		$field_name = sanitize_text_field($_POST['field_name']);
		$field_content = $this->sanitize_field_content($field_name,$_POST['field_content']);
		
		$data = Config::$instances['db']->read_view($id);
		
		if($field_name == 'resources_arr'){
			Config::$instances['db']->write_view(
				$data['title'],
				$field_content,
				$id
			);
		}
		
		if($field_name == 'title'){
			Config::$instances['db']->write_view(
				$field_content,
				[]
			);
		}
		
		if($field_name == 'ownerRole'){
			update_post_meta(
				$id,
				$field_name,
				$field_content
			);
		}
		
		$this->load_views();
	}
	
	public function delete_view(){
		$view_id = $this->sanitize_int($_POST['id']);
		
		Config::$instances['db']->delete_view($view_id);
		
		$this->load_views();
		
		wp_die();
	}
}

Config::$instances['ajax'] = new Ajax();

add_action( 'wp_ajax_resman_load_time', [ Config::$instances['ajax'], 'load_time'] );
add_action( 'wp_ajax_nopriv_resman_load_time', [ Config::$instances['ajax'], 'load_time'] );

add_action( 'wp_ajax_resman_save_time', [ Config::$instances['ajax'], 'save_time'] );
add_action( 'wp_ajax_nopriv_resman_save_time', [ Config::$instances['ajax'], 'save_time'] );

add_action( 'wp_ajax_resman_load_admin_data', [ Config::$instances['ajax'], 'load_admin_data'] );

add_action( 'wp_ajax_resman_load_views', [ Config::$instances['ajax'], 'load_views'] );

add_action( 'wp_ajax_resman_save_view_attr', [ Config::$instances['ajax'], 'save_view_attr'] );

add_action( 'wp_ajax_resman_save_resource_attr', [ Config::$instances['ajax'], 'save_resource_attr'] );

add_action( 'wp_ajax_resman_delete_resource', [ Config::$instances['ajax'], 'delete_resource'] );

add_action( 'wp_ajax_resman_delete_view', [ Config::$instances['ajax'], 'delete_view'] );


?>