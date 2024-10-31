<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );


class recurringDate {
	private int $repeatEveryI = 1; // 1 - ?
	private string $unit = 'week'; //day, week, month, year
	
	private array $parameters = []; //week  - repeat on: 0,1,2,3,4,5,6 => Su,Mo,Tu,We,Th,Fr,Sa
									//month - false  wenn absolut, bezogen au das referenceDate
									//      - sonst: [ TODO: wird noch nicht unterstützt!
									//				0, //if day == 0 { weeknumber in month 1-5 }
									//				0, //if day == 0 { weekday 0-6 }
									//			];
	
	private string $endDate = '1970-01-01'; // end
	
	private string $referenceDate = '1970-01-01'; // start
	
	private $interval = null; //https://www.php.net/manual/de/class.dateinterval.php
	private $period = null; //https://www.php.net/manual/de/class.dateperiod.php
	
	private $list = [];
	
	/* =>
		[1, "week", [], "1970-01-01"]
	*/
	
	function __construct($reference_date, $pattern, $from, $to) {
		$this->referenceDate = $reference_date;
		
		//$pattern : json (wie durch eigene Funktion generiert) hier als Attribute übernehmen
		$this->set_data_by_json($pattern);
		
		$this->{'list'} = $this->get_list_of_dates_between($from,$to);
	}
	
	public function get_list(){
		return $this->{'list'};
	}
	
	//generiere Liste von Datumsangaben, die auf das Pattern innerhalb des Zeitraums passen
	private function get_list_of_dates_between($from,$to){
		//TODO: Relative Datumsangaben verwenden
		$recurringDates = [];
		foreach ($this->period as $date) {
			#$dayOfWeek = $date->format('w');
			#if (in_array($dayOfWeek, $recurrence[2])) {
				$recurringDays[] = $date->format('Y-m-d');
			#}
		}
		
		#\error_log(print_r($recurringDays,1));
		
		return $recurringDays;
	}
	
	private function sanitize_endDate($date){
		$ts = strtotime($date);
		return date('Y-m-d',$ts);
	}
	
	private function get_endDate(){
		return $this->sanitize_endDate($this->endDate);
	}
	
	private function sanitize_repeatEveryI($i){
		$i = intval($i);
		if($i < 1){ return 1; }
		return $i;
	}
	
	private function get_repeatEveryI(){
		return $this->sanitize_repeatEveryI($this->repeatEveryI);
	}
	
	private function sanitize_weekParameters($params){
		$sanitized_weekParameters = [];
		foreach($params as $i => $param){
			$param = intval($param);
			if($param >= 0 && $param < 7){
				$sanitized_weekParameters[] = $param;
			}
		}
		if(sizeof($sanitized_weekParameters) == 0){ return false; }
		
		$sanitized_weekParameters = array_unique($sanitized_weekParameters);
		asort($sanitized_weekParameters);
		
		return $sanitized_weekParameters;
	}
	
	private function get_weekParameters(){
		return $this->sanitize_weekParameters($this->weekParameters);
	}
	
	private function sanitize_relativeMonthParameters($params){
		if(sizeof($params != 2)){ return false;}
		
		$weeknumber = intval($params[0]);
		if($weeknumber < 1 || $weeknumber > 5){ return false; }
		
		$weekday = intval($params[1]);
		if($weekday < 0 || $weeknumber > 6){ return false; }
		
		return [
			$weeknumber,
			$weekday,
		];
	}
	
	private function get_relativeMonthParameters(){
		return $this->sanitize_relativeMonthParameters($this->relativeMonthParameters);
	}
	
	private function sanitize_unit($unit){
		if($unit == 'day'){ return 'day'; }
		if($unit == 'week'){ return 'week'; }
		if($unit == 'month'){ return 'month'; }
		if($unit == 'year'){ return 'year'; }
		
		return false;
	}
	
	private function get_unit(){
		return $this->sanitize_unit($this->unit);
	}

	public function get_array(){
		$unit = $this->get_unit();
		if($unit === false){ return []; }
		
		if($unit == 'week'){
			$parameters = $this->get_weekParameters();
			if($parameters === false){ return []; }
		}
		elseif($unit == 'month'){
			$parameters = $this->get_relativeMonthParameters();
		}
		else{
			$parameters = [];
		}

		return [
			$this->get_repeatEveryI(),
			$unit,
			$parameters,
			$this->get_endDate(),
		];
	}
	
	public function get_json_array(){
		json_encode($this->get_array());
	}
	
	private function set_data_by_json($json){
		$pattern = json_decode($json);
		
		$this->repeatEveryI = intval($pattern[0]);
		$this->unit = $this->sanitize_unit($pattern[1]);
		$this->parameters = $pattern[2];
		$this->endDate = $this->sanitize_endDate($pattern[3]);
		
		$duration = 'P' . $this->repeatEveryI . substr(strtoupper($this->unit),0,1);
		$this->interval = new \DateInterval($duration);
		$this->period = new \DatePeriod(
			(new \DateTime($this->referenceDate)),
			$this->interval,
			(new \DateTime($this->endDate))->modify('+1 day'), #Include End Date
			0 #Exclude Start date?
		);
	}
}