"use strict";

const { __, _x, _n, _nx } = wp.i18n;
ajax.url = location.protocol + '//' + location.host + '/wp-admin/admin-ajax.php';

jQuery(document).ready(function( $ ) {
	// $ Works! You can test it with next line if you like
	// console.log($);
	RESOURCEMANAGER.functions.root_init();
});


var RESOURCEMANAGER = RESOURCEMANAGER || {};

RESOURCEMANAGER.config = {
	/* configuration */
	day_start : '07:00:00',
	day_end   : '18:00:00',
	line_sectors : [0,10,90,100],
	
	plugin_name : 'resourcemanager',
	
	current_view : {
		view : 'monthly_vision',
		args : {
			/* Start values */
			cal_id : 0,
			from : new Date((new Date()).getFullYear(), (new Date()).getMonth(), 1),
			to : new Date((new Date()).getFullYear(), (new Date()).getMonth() + 1, 0)
		},
		
		get_month: function(){
			return this.args.from.getMonth()+1;
		},
		
		set_month: function(val){
			this.args.from = new Date(this.args.from.getFullYear(), val-1, 1);
			this.args.to = new Date(this.args.to.getFullYear(), val, 0);
		},
		
		get_year: function(){
			return this.args.from.getFullYear();
		},
		
		set_year: function(val){
			this.args.from = new Date(val, this.args.from.getMonth(), 1);
			this.args.to = new Date(val, this.args.to.getMonth()+1, 0);
		},
		
		increment_month: function(){
			if(this.get_month() == 12){
				this.set_month( 1 );
				this.set_year( this.get_year() +1 );
			}
			else{
				this.set_month( this.get_month() +1 );
			}
		},
		
		decrement_month: function(){
			if(this.get_month() == 1){
				this.set_month( 12 );
				this.set_year( this.get_year() -1 );
			}
			else{
				this.set_month( this.get_month() -1 );
			}
		},
		
		get_from_string: function(){
			let d = this.args.from;
		
			let year = d.getFullYear();
			let month = d.getMonth()+1;
			let day = d.getDate();
			
			return year + '-' + month + '-' + day;
		},
		
		get_to_string: function(){
			let d = this.args.to;
		
			let year = d.getFullYear();
			let month = d.getMonth()+1;
			let day = d.getDate();
			
			return year + '-' + month + '-' + day;
		},
	}
}

RESOURCEMANAGER.functions = {
	root_init: function(){
		RESOURCEMANAGER.datepicker_statistics.init();
		RESOURCEMANAGER.datepicker_monthly.init();
		
		RESOURCEMANAGER.progressbar.init();
		
		if(jQuery( ".resourcemanager-vision[data-cal_id]" ).length){
			this.data_init();
		}
	},
	
	data_init: function(){
		RESOURCEMANAGER.config.current_view.args.cal_id = parseInt(jQuery('div[data-cal_id]').attr('data-cal_id'));
		RESOURCEMANAGER.data.init();
	},
	
	timestamp_from_hour: function(time_str){
		var time = time_str.split(" ");
		if(time.length == 1){
			time = time_str.split(":");
			return (parseInt(time[0])*60*60 + parseInt(time[1])*60 + parseInt(time[2]));
		}
	},
	
	hour_from_timestamp: function(timestamp){
		var h = Math.floor(timestamp/60/60);
		var m = Math.floor((timestamp % (60*60)) / 60);
		var s = timestamp % 60;
		return h.toString().padStart(2, '0') + ":" + m.toString().padStart(2, '0') + ":" + s.toString().padStart(2, '0');
	},
	
	hour_from_Date: function(date){
		const hours = date.getHours().toString().padStart(2, '0');
		const minutes = date.getMinutes().toString().padStart(2, '0');
		const seconds = date.getSeconds().toString().padStart(2, '0');

		return `${hours}:${minutes}:${seconds}`;
	},
	
	timestamp_from_date_string: function(date_str){
		date_str = date_str.split("-");
		var newDate = new Date(date_str);
		return newDate.getTime();
	},
	
	date_string_from_timestamp: function(timestamp){
		let d = new Date( timestamp );
		
		let year = d.getFullYear();
		let month = d.getMonth()+1;
		let day = d.getDate();
		
		return year.toString().padStart(4, '0') + '-' + month.toString().padStart(2, '0') + '-' + day.toString().padStart(2, '0');
	}
}

RESOURCEMANAGER.progressbar = {
	init: function(){
		jQuery('#resourcemanager-progressbar-dialog').dialog({
			autoOpen: false,
			modal: true,
			closeOnEscape: false,
			open: function(event, ui) {
				jQuery('div[aria-describedby="resourcemanager-progressbar-dialog"] .ui-dialog-titlebar-close', ui.dialog || ui).hide();
			}
		});
	},
	
	open: function(){
		jQuery('#resourcemanager-progressbar-dialog').dialog('open');
	},
	
	close: function(){
		jQuery('#resourcemanager-progressbar-dialog').dialog('close');
	}
}

RESOURCEMANAGER.data = {
	data : {},
	users : {},
	groups: {},
	res_ordered: {},
	
	init: async function(){
		await this.fetch_data();
	},
	
	fetch_data: async function(){
		//via Ajax Daten vom Server holen
		var data = {
			'action': 'resman_load_time',
			'from': RESOURCEMANAGER.config.current_view.get_from_string(),
			'to': RESOURCEMANAGER.config.current_view.get_to_string(),
			'cal_id': RESOURCEMANAGER.config.current_view.args.cal_id
		};
		//console.log(data);
		
		RESOURCEMANAGER.progressbar.open();

		let response = await jQuery.post(ajax.url, data).promise();
		
		
		
		// since 2.8 ajax.url is always defined in the admin header and points to admin-ajax.php
		//jQuery.post(ajax.url, data, function(response) {
			//console.log('Got this from the server: ' + response);
			
			let decoded_response = JSON.parse(response);
			
			RESOURCEMANAGER.data.data = decoded_response['data'];
			RESOURCEMANAGER.data.users = decoded_response['users'];
			RESOURCEMANAGER.data.groups = decoded_response['groups'];
			RESOURCEMANAGER.data.res_ordered = decoded_response['res_ordered'];
			
			/* monthly vision */
			if(jQuery( "#monthly_vision" ).length){
				jQuery('#monthly_vision').html(
					RESOURCEMANAGER.view.monthly_vision(
						RESOURCEMANAGER.data.data
					)
				);
				
				RESOURCEMANAGER.view.set_event_triggers();
				
				RESOURCEMANAGER.detail_view.init();
			}
			
			/* statistics vision */
			if(jQuery( "#statistics_vision" ).length){
				jQuery('#statistics_vision').html(
					RESOURCEMANAGER.view.statistics_vision(
						RESOURCEMANAGER.data.data
					)
				);
			}
			
			/* askFor vision */
			RESOURCEMANAGER.askFor_view.init();
		
			RESOURCEMANAGER.progressbar.close();
			
		//});
	}
}

RESOURCEMANAGER.datepicker_statistics = {
	init: function(){
		if(jQuery( "#resourcemanager-datepicker-statistics-from" ).length){
			let from_field = jQuery( "#resourcemanager-datepicker-statistics-from" );
			from_field.datepicker({
				dateFormat: "yy-mm-dd"
			});
			from_field.val(RESOURCEMANAGER.config.current_view.get_from_string());
			
			from_field.on('change',function(){
				RESOURCEMANAGER.config.current_view.args.from = new Date(jQuery(this).val());
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
			
			let to_field = jQuery( "#resourcemanager-datepicker-statistics-to" );
			to_field.datepicker({
				dateFormat: "yy-mm-dd"
			});
			to_field.val(RESOURCEMANAGER.config.current_view.get_to_string());
			to_field.on('change',function(){
				RESOURCEMANAGER.config.current_view.args.to = new Date(jQuery(this).val());
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
		}
	}
}

RESOURCEMANAGER.datepicker_monthly = {
	init: function(){
		if(jQuery( "#resourcemanager-datepicker-year" ).length){
			this.set_current_date_to_picker();
			
			jQuery( "#resourcemanager-datepicker-year" ).on('change',function(){
				RESOURCEMANAGER.config.current_view.set_year( jQuery(this).val() );
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
			
			jQuery( "#resourcemanager-datepicker-month" ).on('change',function(){
				RESOURCEMANAGER.config.current_view.set_month( jQuery(this).val() );
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
			
			jQuery( "#resourcemanager-datepicker-left" ).on('click',function(){
				RESOURCEMANAGER.config.current_view.decrement_month();
				RESOURCEMANAGER.datepicker_monthly.set_current_date_to_picker();
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
			
			jQuery( "#resourcemanager-datepicker-right" ).on('click',function(){
				RESOURCEMANAGER.config.current_view.increment_month();
				RESOURCEMANAGER.datepicker_monthly.set_current_date_to_picker();
				RESOURCEMANAGER.functions.data_init();
				return false;
			});
		}
	},
	
	set_current_date_to_picker: function(){
		jQuery( "#resourcemanager-datepicker-year" ).val(RESOURCEMANAGER.config.current_view.get_year());
		jQuery( "#resourcemanager-datepicker-month" ).val(RESOURCEMANAGER.config.current_view.get_month());
	}
}

RESOURCEMANAGER.view = {
	/* configuration */
	day_start : RESOURCEMANAGER.config.day_start,
	day_end   : RESOURCEMANAGER.config.day_end,
	line_sectors : RESOURCEMANAGER.config.line_sectors,
	
	/* calculated but often used */
	secondsPerDay : 60*60*24,
	day_start_ts : 0,
	day_end_ts : 0,
	secondsPerWorkDay : 0,
	line_sectors_multiplicator : [0.0,0.0,0.0,0.0],
	data : {},
	monthly_data : {},
	statistics_data : {},

	init: function(){
		this.day_start_ts = this.timestamp_from_hour(this.day_start);
		this.day_end_ts = this.timestamp_from_hour(this.day_end);
		this.secondsPerWorkDay = this.day_end_ts - this.day_start_ts;
		
		for(let i=0; i < this.line_sectors.length-1; i++){
			this.line_sectors_multiplicator[i] = (this.line_sectors[i+1]-this.line_sectors[i]) * 0.01;
		}
	},
	
	set_event_triggers : function(){
		jQuery('#monthly_vision table tr td a').hover(RESOURCEMANAGER.view.on_mouse_enter, RESOURCEMANAGER.view.on_mouse_leave);
		jQuery('#monthly_vision table tr td a').focusin(RESOURCEMANAGER.view.on_focus_enter);
		jQuery('#monthly_vision table tr td a').focusout(RESOURCEMANAGER.view.on_focus_leave);
		
		jQuery('#monthly_vision table tr td a.overlapping').click(RESOURCEMANAGER.view.overlapping_on_click);
	},
	
	overlapping_on_click : function(){
		jQuery(this).toggleClass('open');
		return false;
	},
	
	on_mouse_enter : function(){
		let data_resource = jQuery(this)[0].getAttribute("data-resource");
		let data_from_d = jQuery(this)[0].getAttribute("data-from_d");
		let data_from_t = jQuery(this)[0].getAttribute("data-from_t");
		
		jQuery('#monthly_vision table tr td a[data-resource="' + data_resource + '"][data-from_d="' + data_from_d + '"][data-from_t="' + data_from_t + '"]').addClass('hoverInclude');
		
		
		data_from_d = jQuery(this)[0].getAttribute("data-original-from_d");
		data_from_t = jQuery(this)[0].getAttribute("data-original-from_t");
		if(data_from_d && data_from_t){
			jQuery('#monthly_vision table tr td a[data-resource="' + data_resource + '"][data-original-from_d="' + data_from_d + '"][data-original-from_t="' + data_from_t + '"]').addClass('hoverInclude');
		}
	},
	
	on_mouse_leave : function(){
		jQuery('#monthly_vision table tr td a').removeClass('hoverInclude');
	},
	
	on_focus_enter : function(){
		let data_resource = jQuery(this)[0].getAttribute("data-resource");
		let data_from_d = jQuery(this)[0].getAttribute("data-from_d");
		let data_from_t = jQuery(this)[0].getAttribute("data-from_t");
		
		jQuery('#monthly_vision table tr td a[data-resource="' + data_resource + '"][data-from_d="' + data_from_d + '"][data-from_t="' + data_from_t + '"]').addClass('focusInclude');
	},
	
	on_focus_leave : function(){
		jQuery('#monthly_vision table tr td a').removeClass('focusInclude');
	},
	
	prepare_data_for_monthly_vision(){
		//Durchlaufe alle Eintraege und splitte mehrtägige Termine auf die jeweiligen Tage auf
		this.monthly_data = this.data;
		for(let res_id in this.monthly_data){
			for(let i = 0; i < Object.keys(this.monthly_data[res_id]).length; i++){
				let date_keys = Object.keys(this.monthly_data[res_id]).sort();
				let date = date_keys[i];
			
				for(let time_slot_id in this.monthly_data[res_id][date]){
					let time_slot = this.monthly_data[res_id][date][time_slot_id]; //nicht zum zurückschreiben der Werte verwenden!
					try {
						this.monthly_data[res_id][date][time_slot_id]['from_d'] = time_slot['from'].split(' ')[0];
						this.monthly_data[res_id][date][time_slot_id]['from_t'] = time_slot['from'].split(' ')[1];
						this.monthly_data[res_id][date][time_slot_id]['to_d']   = time_slot['to'].split(' ')[0];
						this.monthly_data[res_id][date][time_slot_id]['to_t']   = time_slot['to'].split(' ')[1];
						
						let to_d_ts = this.timestamp_from_date_string(this.monthly_data[res_id][date][time_slot_id]['to_d']);
						let date_ts = this.timestamp_from_date_string(date);
						let next_date_ts = date_ts + (60*60*24)*1000;
						let next_date = this.date_string_from_timestamp(next_date_ts);
						
						if(to_d_ts > date_ts){
							//Kopiere ohne Veränderungen einfach einen Tag weiter
							if (this.monthly_data[res_id][next_date] === undefined) {
								this.monthly_data[res_id][next_date] = [];
							}
							this.monthly_data[res_id][next_date].push(time_slot);
						}
						
					} catch(e) {
						//console.log(e);
					}
				}
			}
		}
	},
	
	statistics_vision: function(data){
		this.init();
		
		this.data = data;
		this.prepare_data_for_monthly_vision();
		this.statistics_data = this.monthly_data;
		
		let available_time = {}; //in s
		let reserved_time = {};	// in ms
		
		let from = RESOURCEMANAGER.config.current_view.args.from;
		let to = RESOURCEMANAGER.config.current_view.args.to;
		
		for(let res_id in this.statistics_data){
			available_time[res_id] = 0;
			reserved_time[res_id] = 0;
			
			for (let d = new Date(from.getTime()); d <= to; d.setDate(d.getDate() + 1)) {
				if(d.getDay() != 0 && d.getDay() != 7){
					available_time[res_id] += this.secondsPerWorkDay;
					
					try{
						let date_str = d.getFullYear().toString().padStart(4, '0') + '-' + (d.getMonth() +1).toString().padStart(2, '0') + '-' + d.getDate().toString().padStart(2, '0');
						let reservations = this.statistics_data[res_id][date_str];
						for(let reservation_id in reservations){
							let time_frame = new Date(reservations[reservation_id].to).getTime() - new Date(reservations[reservation_id].from).getTime();
							reserved_time[res_id] += time_frame;
						}
					} catch(e) {}
				}
			}
		}
		
		
		var html = "<table class='resourcemanager statisctics'>";
		for(let res_id in available_time){
			let utilisation = Math.round(reserved_time[res_id]/available_time[res_id])/10; // in %
			html += "<tr><td>" + this.data[res_id]['meta']['name'] + "</td><td>" + utilisation + "%</td></tr>";
		}
		html += "</table>";
		
		return html;
	},
	
	monthly_vision: function(data){
		this.init();
		
		this.data = data;
		
		this.prepare_data_for_monthly_vision();
		
		let year = RESOURCEMANAGER.config.current_view.get_year();
		let month = RESOURCEMANAGER.config.current_view.get_month();
		
		var days_in_month = new Date(year, month, 0).getDate();
		//console.log(days_in_month);
		//console.log("Hallo Welt");
		
		var html = "<table class='resourcemanager month'>";
		
		//console.log(data);
		
		/* Resource Title line */
		html += "<tr><td></td>";
		for(let i in RESOURCEMANAGER.data.res_ordered){
			let res = RESOURCEMANAGER.data.res_ordered[i];
			let countable = this.monthly_data[res]['meta']['countable'];
			
			html += "<td>";
			html += (this.monthly_data[res]['meta']['descripRes'] != '') ? this.monthly_data[res]['meta']['descripRes'] : this.monthly_data[res]['meta']['name'];
			html += (countable != undefined && countable > 1) ? " (" + countable + ")" : "";
			html += "</td>";
			console.log(this.monthly_data[res]);
		}
		html += "</tr>";
		
		/* Days of month per line */
		let today = RESOURCEMANAGER.functions.date_string_from_timestamp(Date.now());
		let year_str = year.toString().padStart(4, '0');
		let month_str = month.toString().padStart(2, '0');
		for(let i=1; i <= days_in_month; i++){
			// line of a single day
			let date = year_str + "-" + month_str + "-" + i.toString().padStart(2, '0');
			let date_text = i.toString().padStart(2, '0') + '.&nbsp;' + this.weekday((new Date(date)).getDay());
			let weekday_class = "weekday_" + (new Date(date)).getDay().toString();
			let tody_class = (date == today) ? ' today' : '';
			
			html += "<tr class='" + weekday_class + tody_class + "'><td>" + date_text + "</td>";
			for(let i in RESOURCEMANAGER.data.res_ordered){
				let res = RESOURCEMANAGER.data.res_ordered[i];
				// all entrys of a spexific resource for a single day
				html += this.get_day_line_for_resource(this.monthly_data[res][date], date, this.monthly_data[res]['meta']);
			}
			html += "</tr>";
		}
		html += "</table>";
		
		return html;
	},
	
	weekday: function(i){
		var weekday = new Array(7);
		weekday[0] = __( 'Su', RESOURCEMANAGER.config.plugin_name );
		weekday[1] = __( 'Mo', RESOURCEMANAGER.config.plugin_name );
		weekday[2] = __( 'Tu', RESOURCEMANAGER.config.plugin_name );
		weekday[3] = __( 'We', RESOURCEMANAGER.config.plugin_name );
		weekday[4] = __( 'Th', RESOURCEMANAGER.config.plugin_name );
		weekday[5] = __( 'Fr', RESOURCEMANAGER.config.plugin_name );
		weekday[6] = __( 'Sa', RESOURCEMANAGER.config.plugin_name );
		
		return weekday[i];
	},
	
	timestamp_from_hour: function(time_str){
		return RESOURCEMANAGER.functions.timestamp_from_hour(time_str);
	},
	
	hour_from_timestamp: function(timestamp){
		return RESOURCEMANAGER.functions.hour_from_timestamp(timestamp);
	},
	
	timestamp_from_date_string: function(date_str){
		return RESOURCEMANAGER.functions.timestamp_from_date_string(date_str);
	},
	
	date_string_from_timestamp: function(timestamp){
		return RESOURCEMANAGER.functions.date_string_from_timestamp(timestamp);
	},
	
	hour_from_Date: function(date){
		return RESOURCEMANAGER.functions.hour_from_Date(date);
	},
	
	
	get_data_for_line: function(args){
		if(args){
			let data = "";
			
			data += " data-resource='" + args['res_meta_id'] + "'";
			data += " data-from_d='" + args['from_d'] + "'";
			data += " data-from_t='" + args['from_t'] + "'";
			data += " data-adminView='" + args['res_meta_adminView'] + "'";
			data += " data-to_d='" + args['to_d'] + "'";
			data += " data-to_t='" + args['to_t'] + "'";
			data += " data-type='" + args['typeClass'] + "'";
			
			return data;
		}
		return '';
	},
	
	get_day_line_for_resource: function(reservations,date,res_meta){
		
		var date_ts = Math.round( new Date(date).getTime() / 1000 );
		
		var line_html = "";
		var overlapping_table = "";
		var timetable = {
			[this.timestamp_from_hour('00:00:00')] : {
				'to' : this.timestamp_from_hour('23:59:59'),
				'status' : 'free'
			},
		};
		
		
		if(reservations != null){
			timetable = this.merge_timetable_with_reservations(timetable,reservations,date_ts);
		}
		var sortedKeys = Object.keys(timetable).sort();
		
		var div_start_pos;
		var div_end_pos;
		var width;
		
		var overlapping_bookings = 0;
		var max_forCurrentUserClass = '';
		
		for(const key in sortedKeys){
			//von:    timetable[key]
			//bis:    timetable[key]['to']
			//status: timetable[key]['status']
			
			// before day start
			if(sortedKeys[key] < this.timestamp_from_hour('00:00:00')){
				div_start_pos = 0;
			}
			// 0 - day_start_ts
			else if(sortedKeys[key] < this.day_start_ts){
				div_start_pos = this.seconds_to_percent(sortedKeys[key],0,this.day_start_ts) * this.line_sectors_multiplicator[0] + this.line_sectors[0];
			}
			// day_start_ts - day_end_ts
			else if(sortedKeys[key] < this.day_end_ts){
				div_start_pos = this.seconds_to_percent(sortedKeys[key],this.day_start_ts,this.day_end_ts) * this.line_sectors_multiplicator[1] + this.line_sectors[1];
			}
			// day_end_ts - secondsPerDay
			else if(sortedKeys[key] < this.secondsPerDay){
				div_start_pos = this.seconds_to_percent(sortedKeys[key],this.day_end_ts,this.secondsPerDay) * this.line_sectors_multiplicator[2] + this.line_sectors[2];
			}
			
			
			if(timetable[sortedKeys[key]]['to'] < this.day_start_ts){
				div_end_pos = this.seconds_to_percent(timetable[sortedKeys[key]]['to'],0,this.day_start_ts) * this.line_sectors_multiplicator[0] + this.line_sectors[0];
			}
			else if(timetable[sortedKeys[key]]['to'] < this.day_end_ts){
				div_end_pos = this.seconds_to_percent(timetable[sortedKeys[key]]['to'],this.day_start_ts,this.day_end_ts) * this.line_sectors_multiplicator[1] + this.line_sectors[1];
			}
			else if(timetable[sortedKeys[key]]['to'] < this.secondsPerDay){
				div_end_pos = this.seconds_to_percent(timetable[sortedKeys[key]]['to'],this.day_end_ts,this.secondsPerDay) * this.line_sectors_multiplicator[2] + this.line_sectors[2];
			}
			else if(timetable[sortedKeys[key]]['to'] > this.timestamp_from_hour('23:59:59')){
				div_end_pos = 100;
			}
			
			
			width = div_end_pos - div_start_pos;
			
			let from_d = date;
			let from_t = this.hour_from_timestamp(sortedKeys[key]);
			let to_d = date;
			let to_t = this.hour_from_timestamp(timetable[sortedKeys[key]]['to']);
			let bookingUser = '';
			let forCurrentUserClass = '';
			let typeClass = '';
			let reserved_number = 1;
			
			var date_data = {};
			
			try{
				let time_slot_id = timetable[sortedKeys[key]]['time_slot_id'];
				date_data = this.monthly_data[res_meta['id']][date][time_slot_id];
				from_d = date_data['from_d'];
				from_t = date_data['from_t'];
				to_d = date_data['to_d'];
				to_t = date_data['to_t'];
				let userType = date_data['userType'];
				if(userType == 'user'){
					bookingUser = RESOURCEMANAGER.data.users[date_data['bookingUser']];
					//if(bookingUser == ''){
					//	bookingUser = date_data['descripDate'];
					//}
				}
				if(userType == 'group'){
					bookingUser = RESOURCEMANAGER.data.groups[date_data['bookingUser']];
				}
				forCurrentUserClass = (date_data['isForCurrentUser']) ? 'forCurrentUser' : '';
				typeClass = date_data['type'];
				
				reserved_number = date_data['reserved_number'];
				if(reserved_number == undefined || reserved_number == ''){ reserved_number = 1; }
				
			} catch(e) {
				//no reservation found, using standard values
				//console.log(timetable[sortedKeys[key]]);
			}
			
			let data = this.get_data_for_line({
				'res_meta_id' : res_meta['id'],
				'from_d' : from_d,
				'from_t' : from_t,
				'res_meta_adminView' : res_meta['adminView'],
				'to_d' : to_d,
				'to_t' : to_t,
				'typeClass' : typeClass,
			});
			
			/* data for recurring Dates */
			try{
				if(date_data['original']['from']){
					data += " data-original-from_d='" + this.date_string_from_timestamp((new Date( date_data['original']['from'] )).getTime()) + "'";
					data += " data-original-from_t='" + this.hour_from_Date(new Date( date_data['original']['from'] )) + "'";
					data += " data-original-to_d='" + this.date_string_from_timestamp((new Date( date_data['original']['to'] )).getTime()) + "'";
					data += " data-original-to_t='" + this.hour_from_Date(new Date( date_data['original']['to'] )) + "'";
					data += " data-original-pattern='" + JSON.stringify(date_data['original']['pattern']) + "'";
					//console.log(date_data['original']);
				}
				
			} catch (error) {
				//console.error(error);
				//console.log(date_data);
			}
			
			let resourceCountable = this.monthly_data[res_meta['id']]['meta']['countable'];
			if(resourceCountable > 1 && reserved_number >= 1){
				overlapping_bookings++;
			}
		
			if(
				timetable[sortedKeys[key]]['status'] != 'free'
				&& resourceCountable > 1
				&& reserved_number >= 1
			){
				if(max_forCurrentUserClass == '' && forCurrentUserClass != ''){max_forCurrentUserClass = forCurrentUserClass;}
			
				let free_data = this.get_data_for_line({
					'res_meta_id' : res_meta['id'],
					'from_d' : from_d,
					'from_t' : '00:00:00',
					'res_meta_adminView' : res_meta['adminView'],
					'to_d' : to_d,
					'to_t' : '23:59:59',
					'typeClass' : typeClass,
				});
				
				line_html = "<a href='#'"
								+ " class='timeSpace free partially_reserved " + max_forCurrentUserClass + "'"
								+ free_data
								+ " style='left:0%; width:100%'"
								/*+ " title='(" + from_t.substr(0, 5) + "-" + to_t.substr(0, 5) + ")"*/
											+ "'>"
							+ "</a>";
				overlapping_table += "<div>.<a href='#'"
								+ " class='timeSpace " + timetable[sortedKeys[key]]['status'] + " " + forCurrentUserClass + "'"
								+ data
								+ " style='left:" + div_start_pos + "%; width:" + width + "%'"
								+ " title='" + bookingUser + " (" + from_t.substr(0, 5) + "-" + to_t.substr(0, 5) + ")"
									+ ( (reserved_number > 1) ? " [" + reserved_number + "]" : "")
									+ "'>"
								+ bookingUser
							+ "</a></div>";
			}
			else if(overlapping_table == ""){
				line_html += "<a href='#'"
								+ " class='timeSpace " + timetable[sortedKeys[key]]['status'] + " " + forCurrentUserClass + "'"
								+ data
								+ " style='left:" + div_start_pos + "%; width:" + width + "%'"
								+ " title='" + bookingUser + " (" + from_t.substr(0, 5) + "-" + to_t.substr(0, 5) + ")"
											+ ( (reserved_number > 1) ? " [" + reserved_number + "]" : "")
											+ "'>"
								+ bookingUser
							+ "</a>";
			}
			
			//this.secondsPerWorkDay; // = 70%
			//timetable[key];
		}
		
		line_html = "<td>" + line_html + (overlapping_bookings > 1 ? "<a class='overlapping' href='#'></a><div>.</div>" + overlapping_table : "") + "</td>";
		
		return line_html;
	},
	
	seconds_to_percent: function(s,sector_from,sector_to){ //alle Einheiten in Sekunden
		return ((s - sector_from)/(sector_to - sector_from))*100;
	},
	
	get_time_from_String: function(s){
		return Math.round( new Date(s + "+00:00").getTime() / 1000 );
	},
	
	merge_timetable_with_reservations: function(timetable,reservations,date_ts){
		for(let time_slot_id in reservations){
			let reservation = reservations[time_slot_id];
			
			let reservation_from = this.get_time_from_String(reservation['from']) - date_ts;
			if(reservation_from < 0){reservation_from = 0;}
			
			let reservation_to = this.get_time_from_String(reservation['to']) - date_ts;
			if(reservation_to >= this.seconds_per_day){reservation_to = this.seconds_per_day - 1;}
			
			//intersect free time spaces in all entitys in timetable
			for(let timetable_from in timetable){
				let timetable_ = timetable[timetable_from];
				
				if(timetable_['status'] == 'free'){
					// [//[   ]//]
					if( reservation_from <= timetable_from && reservation_to >= timetable_['to']){
						//delete the free space
						delete timetable[timetable_from];
					}
					
					// [///[/]   ]
					else if(
						reservation_from <= timetable_from
						&& reservation_to >= timetable_from && reservation_to <= timetable_['to']
					){
						//shorten the free space in front
						timetable[(reservation_to +1)] = timetable[timetable_from];
						delete timetable[timetable_from];
					}
					
					// [   [/]///]
					else if(
						reservation_from >= timetable_from && reservation_from <= timetable_['to']
						&& reservation_to >= timetable_['to']
					){
						//shorten the free space at the end
						timetable[timetable_from]['to'] = reservation_from -1;
					}
					
					// [  [///]  ]
					else if( reservation_from >= timetable_from && reservation_to <= timetable_['to']){
						//split the free space
						let timetable_to_temp = timetable[timetable_from]['to'];
						timetable[timetable_from]['to'] = reservation_from -1;
						timetable[(reservation_to +1)] = { 'to': timetable_to_temp, 'status' : 'free' };
					}
				}
			}
			
			timetable[reservation_from] = {
				'to' : reservation_to,
				'status' : 'reserved',
				'time_slot_id' : time_slot_id
			};
		}
			
		return timetable;
	},
}

RESOURCEMANAGER.askFor_view = {
	init: function(){
		this.get_askFor_data();
	},
	
	get_askFor_data: function(){
		jQuery( "#ask_for > div" ).remove();
		
		//console.log(RESOURCEMANAGER.data.data);
		
		for(let res_id in RESOURCEMANAGER.data.data){
			for(let date in RESOURCEMANAGER.data.data[res_id]){
				if(date != 'meta'){
					for(let i in RESOURCEMANAGER.data.data[res_id][date]){
						if( RESOURCEMANAGER.data.data[res_id][date][i]['type'] == 'ASKFOR' ){
							this.print(res_id,date,i);
						}
					}
				}
			}
		}
	},
	
	print: function(res_id,date,i){
		let isAdmin = RESOURCEMANAGER.data.data[res_id]['meta']['adminView'];
		
		if(isAdmin){
			let dataset = RESOURCEMANAGER.data.data[res_id][date][i];
			let res_name = RESOURCEMANAGER.data.data[res_id]['meta']['name'];
			let html = "<div>"
						+ __( 'Pending request', RESOURCEMANAGER.config.plugin_name ) + ': '
						+ dataset['from'] + ', ' + res_name.replace( /<br\s*[\/]?>/gi , ' ')
						+ "</div>";
		
			jQuery( "#ask_for" ).append(html);
		}
	}
}

RESOURCEMANAGER.detail_view = {
	dialog : null,
	form : null,
	data : null,

	init: function(){
		jQuery( ".timeSpace" ).on( "click", this.timespaceOnClick);
		this.setResOptions();
		this.setUserOptions();
		
		jQuery( "#resourcemanager-dialog-confirm" ).dialog({
			autoOpen: false,
			resizable: false,
			height: "auto",
			width: 400,
			modal: true,
			buttons: {
				"Löschen": function() {
					jQuery( this ).dialog( "close" );
					RESOURCEMANAGER.detail_view.edit('DELETE');
				},
				'Abbrechen': function() {
					jQuery( this ).dialog( "close" );
				}
			}
		});
		
		this.dialog = jQuery( "#resourcemanager-dialog-form" ).dialog({
			autoOpen: false
		});
		
		jQuery("#resourcemanager-dialog-form #bookingUser").select2();
		
		jQuery("#resourcemanager-dialog-form #bookingUser").on('change', function(e){
			let userType = jQuery(this).find(':selected').data('usertype');
			jQuery("#resourcemanager-dialog-form #userType").val(userType);
		});
		
		var details = document.querySelector('#resourcemanager-dialog-form details');
		details.addEventListener('toggle', function(e){
			let checkbox = jQuery('#resourcemanager-dialog-form details #recurring');
			(details.open) ? checkbox.val(1) :  checkbox.val(0);
		});
	},
	
	save: function(){
		RESOURCEMANAGER.detail_view.edit('INSERT');
	},
	
	ask_for: function(){
		RESOURCEMANAGER.detail_view.edit('ASKFOR');
	},
	
	remove: function(){
		jQuery( "#resourcemanager-dialog-confirm" ).dialog('open');
	},
	
    edit: async function(type) {
		var form_data = {};
		jQuery("#resourcemanager-dialog-form .fdata").each(function () {
			let name = jQuery(this).attr('name');
			let val = jQuery(this).val();
			form_data[name] = val;
		});
		form_data['action'] = 'resman_save_time';
		form_data['type'] = type;
		
		// since 2.8 ajax.url is always defined in the admin header and points to admin-ajax.php
		let response = await jQuery.post(ajax.url, form_data).promise();
		
		console.log('Got this from the server: ' + response);
		
		if(response != ''){
			jQuery.toast({
			  heading: 'Error',
			  text : __( response, RESOURCEMANAGER.config.plugin_name ),
			  icon: 'error',
			  hideAfter: 8000,   // in milli seconds
			  position: 'bottom-center',
			});
		}
		
		RESOURCEMANAGER.functions.data_init();
		
        RESOURCEMANAGER.detail_view.cancel();
    },
	
	cancel: function() {
		RESOURCEMANAGER.detail_view.dialog.dialog( "close" );
	},
	
	getData: function(e) {
		var resource = parseInt(e.getAttribute("data-resource"));
		var from_d = e.getAttribute("data-original-from_d") ?? e.getAttribute("data-from_d");
		var from_t = e.getAttribute("data-original-from_t") ?? e.getAttribute("data-from_t");
		var to_d = e.getAttribute("data-original-to_d") ?? e.getAttribute("data-to_d");
		var to_t = e.getAttribute("data-original-to_t") ?? e.getAttribute("data-to_t");
		var pattern = JSON.parse(e.getAttribute("data-original-pattern"));
		
		var bookingUser = ajax.user_login;
		
		//console.log(pattern);
		//console.log(e.getAttribute("data-original-from_d"));
		
		var sql_ts = from_d + " " + from_t;
		this.data = RESOURCEMANAGER.data.data;
		
		var is_free_field = true;
		
		//immer zu setzende Daten (fuer leere Felder)
		let persons = 1;
		
		this.setCustomFields(resource);
		this.setNumberField(resource);
		
		jQuery( "#resourcemanager-dialog-form #resource_id_txt" ).html(resource);
		jQuery( "#resourcemanager-dialog-form #resource_id" ).val(resource);
		jQuery( "#resourcemanager-dialog-form #res_id_before" ).val(resource);
		//jQuery( "#resourcemanager-dialog-form #persons" ).val(persons);
		jQuery( "#resourcemanager-dialog-form #from_d" ).val(from_d);
		jQuery( "#resourcemanager-dialog-form #from_t" ).val(from_t);
		jQuery( "#resourcemanager-dialog-form #to_d" ).val(to_d);
		jQuery( "#resourcemanager-dialog-form #to_t" ).val(to_t);
		jQuery( "#resourcemanager-dialog-form #bookingUser" ).val(bookingUser);
		
		jQuery( "#resourcemanager-dialog-form #from_d ~ .userView" ).text(from_d + " " + from_t);
		jQuery( "#resourcemanager-dialog-form #to_d ~ .userView" ).text(to_d + " " + to_t);
		//jQuery( "#resourcemanager-dialog-form #persons ~ .userView" ).text(persons);
		
		
		//in bereits abgeholtem Datenpaket suchen
		if(typeof this.data[resource][from_d] !== 'undefined'){
			for(const time_frames in this.data[resource][from_d]){
				let time_frame = this.data[resource][from_d][time_frames];
				
				if(time_frame['from'] == sql_ts){
					//let persons = time_frame['persons'];
					let userType = time_frame['userType'];
										
					/* Deprecated */
					//let descripDate = time_frame['descripDate'];
					
					bookingUser = time_frame['bookingUser'];
					
					let customFieldsData = time_frame['customFieldsData'];

					//console.log(time_frame);
					for(let field in customFieldsData){
						let value = customFieldsData[field];
						jQuery( "#resourcemanager-dialog-form [name=\"" + field + "\"]" ).val(value);
						jQuery( "#resourcemanager-dialog-form [name=\"" + field + "\"] ~ .userView" ).text(value);
					}
					
					if(this.data[resource]['meta']['countable'] > 1){
						let value = time_frame['reserved_number'];
						jQuery( "#resourcemanager-dialog-form [name=\"reserved_number\"]" ).val(value);
						jQuery( "#resourcemanager-dialog-form [name=\"reserved_number\"] ~ .userView" ).text(value);
					}
					
					
					jQuery( "#resourcemanager-dialog-form #bookingUser" ).val(bookingUser);
					jQuery( "#resourcemanager-dialog-form #userType" ).val(userType);
					//jQuery( "#resourcemanager-dialog-form #descripDate" ).val(descripDate);
					//jQuery( "#resourcemanager-dialog-form #persons" ).val(persons);
					
					jQuery( "#resourcemanager-dialog-form #bookingUser ~ .userView" ).text(bookingUser);
					//jQuery( "#resourcemanager-dialog-form #descripDate ~ .userView" ).text(descripDate);
					//jQuery( "#resourcemanager-dialog-form #persons ~ .userView" ).text(persons);
					
					is_free_field = false;
					
					if(pattern){
						//console.log(pattern);
						document.querySelector('#resourcemanager-dialog-form details').open = true;
						let every_i = pattern[0];
						let every_u = pattern[1];
						let till_d = pattern[3].split(' ')[0];
						
						jQuery( "#resourcemanager-dialog-form #recurring" ).val(1);
						jQuery( "#resourcemanager-dialog-form #every_i" ).val(every_i);
						jQuery( "#resourcemanager-dialog-form #every_u" ).val(every_u);
						jQuery( "#resourcemanager-dialog-form #till_d" ).val(till_d);
						
						let userView_format = this.formatRecurringUserView(every_i, every_u, till_d);
						jQuery( "#resourcemanager-dialog-form .recurring + .userView" ).text(userView_format);
					}
					else{
						document.querySelector('#resourcemanager-dialog-form details').open = false;
						jQuery( "#resourcemanager-dialog-form #recurring" ).val(0);
					}
				}
			}
		}
		
		jQuery( "#resourcemanager-dialog-form #bookingUser" ).trigger('change');
		
		if(is_free_field){
			if(
				(from_t == '00:00:00' || from_t == '00:00:01')
				&& RESOURCEMANAGER.functions.timestamp_from_hour(to_t) > RESOURCEMANAGER.functions.timestamp_from_hour(RESOURCEMANAGER.config.day_start)
			){
				jQuery( "#resourcemanager-dialog-form #from_t" ).val(RESOURCEMANAGER.config.day_start);
			}
			
			if(to_t == '23:59:59' && RESOURCEMANAGER.functions.timestamp_from_hour(from_t) < RESOURCEMANAGER.functions.timestamp_from_hour(RESOURCEMANAGER.config.day_end)){
				jQuery( "#resourcemanager-dialog-form #to_t" ).val(RESOURCEMANAGER.config.day_end);
			}
		}
		
	},
	
	formatRecurringUserView: function(every_i, every_u, till_d){
		return __( 'every', RESOURCEMANAGER.config.plugin_name )
			+ ' ' + every_i
			+ ' ' + __( every_u, RESOURCEMANAGER.config.plugin_name )
			+ ' ' + __( 'till', RESOURCEMANAGER.config.plugin_name )
			+ ' ' + (new Date(till_d)).toLocaleDateString();
	},
	
	setNumberField: function(resource_id){
		let resourceCountable = this.data[resource_id]['meta']['countable'];
		let divBox = jQuery("#resourcemanager-dialog-form #number");
		divBox.empty();
		
		if(resourceCountable > 1){
			let html =    "<p>"
							+ "<label for=\"reserved_number\">" + __( 'number', RESOURCEMANAGER.config.plugin_name ) + "</label>"
							+ "<input type=\"number\" name=\"reserved_number\" value=\"1\" step=\"1\" min=\"1\" required"
								+ " max=\"" + resourceCountable + "\""
							+ "class=\"adminView text ui-widget-content ui-corner-all fdata\" />"
							+ "<span class=\"userView\"></span>"
						+ "</p>"
				
			divBox.append(html);
		}
	},
	
	setCustomFields: function(resource_id){
		let customFields = this.data[resource_id]['meta']['customFields'];
		let divBox = jQuery("#resourcemanager-dialog-form #customFields");
		
		divBox.empty();
		
		for(let key in customFields){
			let label = customFields[key]['label'];
			let type = customFields[key]['type'];
			
			let html =    "<p>"
							+ "<label for=\"" + key + "\">" + label + "</label>"
							+ "<input type=\"" + type + "\" name=\"" + key + "\""
								+ ((customFields[key]['step'] != undefined) ? " step=\"" + customFields[key]['step'] + "\"" : "")
								+ ((customFields[key]['min']  != undefined) ? " min=\""  + customFields[key]['min']  + "\"" : "")
								+ ((customFields[key]['max']  != undefined) ? " max=\""  + customFields[key]['max']  + "\"" : "")
								+ ((customFields[key]['required']  != undefined) ? " required" : "")
							+ "class=\"adminView text ui-widget-content ui-corner-all fdata\" />"
							+ "<span class=\"userView\"></span>"
						+ "</p>"
			
				//html = "<input type='hidden' name='" + key + "' />";
				
			divBox.append(html);
		}
	},
	
	setResOptions: function(){
		jQuery("#resourcemanager-dialog-form #resource_id").val(null).trigger('change');
		jQuery("#resourcemanager-dialog-form #resource_id").children().remove().end();
		jQuery("#resourcemanager-dialog-form #resource_id").trigger('change');
		
		let res = RESOURCEMANAGER.data.res_ordered;		
		for(const i in res){
			let res_id = res[i];
			let res_name = RESOURCEMANAGER.data.data[res_id]['meta']['name']
			let _option = new Option(res_name, res_id);
			jQuery("#resourcemanager-dialog-form #resource_id").append(_option);
		}
	},
	
	setUserOptions: function(){
		jQuery("#resourcemanager-dialog-form #bookingUser").val(null).trigger('change');
		jQuery("#resourcemanager-dialog-form #bookingUser").children().remove().end();
		jQuery("#resourcemanager-dialog-form #bookingUser").trigger('change');
		
		let users = RESOURCEMANAGER.data.users;		
		for(const id in users){
			let _option = new Option(users[id], id);
			_option.setAttribute('data-userType','user');
			jQuery("#resourcemanager-dialog-form #bookingUser").append(_option);
		}
		
		let groups = RESOURCEMANAGER.data.groups;
		for(const id in groups){
			let _option = (new Option(groups[id], id));
			_option.setAttribute('data-userType','group');
			jQuery("#resourcemanager-dialog-form #bookingUser").append(_option);
		}
	},

	timespaceOnClick: function(){
		var e = this;
		
		var adminView 		= (e.getAttribute('data-adminView') == 'true') 	? true : false;
		var askFor 			= (e.getAttribute('data-type') == 'ASKFOR') 	? true : false;
		var free 			= e.classList.contains('free') 					? true : false;
		var forCurrentUser 	= e.classList.contains('forCurrentUser') 		? true : false;
		
		
		if(adminView){
			RESOURCEMANAGER.detail_view.adminifyDialog(askFor);
		}
		else if(free){
			RESOURCEMANAGER.detail_view.askForDialog();
		}
		else{
			RESOURCEMANAGER.detail_view.userifyDialog({'forCurrentUser': forCurrentUser});
		}
		
		RESOURCEMANAGER.detail_view.dialog.dialog( "open" );
		
		//Attribute aus data-... uebernehmen		
		let data_vals = jQuery(e).data();
		for(const property in data_vals){
			var val = data_vals[property];
			if(val !== null && property != 'adminView'){
				jQuery( "#resourcemanager-dialog-form #" + property ).val(val);
			}
		}
		jQuery( "#resourcemanager-dialog-form #from_d_hidden" ).val(e.getAttribute('data-from_d'));
		jQuery( "#resourcemanager-dialog-form #from_t_hidden" ).val(e.getAttribute('data-from_t'));
		
		//weitere Attribute aus data.data verwenden
		RESOURCEMANAGER.detail_view.getData(e);
		
		return false;
	},
	
	userifyDialog: function(params){
		var buttons = {};
		if(params.forCurrentUser){
			buttons = {
				[__( 'Delete', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.remove,
				[__( 'Cancel', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.cancel,
			}
		}
		
		this.dialog = jQuery( "#resourcemanager-dialog-form" ).dialog({
		  //autoOpen: false,
		  height: 300,
		  width: 400,
		  modal: true,
		  buttons: { },
		  close: function() {
			RESOURCEMANAGER.detail_view.form[ 0 ].reset();
		  },
		  'buttons': buttons,
		});
			 
		this.form = this.dialog.find( "form" ).on( "submit", function( event ) {
		  //event.preventDefault();
		  //RESOURCEMANAGER.detail_view.ask_for();
		});
		
		jQuery( "#resourcemanager-dialog-form" ).addClass("userView");
		jQuery( "#resourcemanager-dialog-form" ).removeClass("adminView");
	},
	
	askForDialog: function(free){
		this.dialog = jQuery( "#resourcemanager-dialog-form" ).dialog({
		  //autoOpen: false,
		  height: 300,
		  width: 400,
		  modal: true,
		  buttons: {
			[__( 'Ask for', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.ask_for, //Übersetzung: Anfragen
			[__( 'Cancel', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.cancel
		  },
		  close: function() {
			RESOURCEMANAGER.detail_view.form[ 0 ].reset();
		  }
		});
			 
		this.form = this.dialog.find( "form" ).on( "submit", function( event ) {
		  //event.preventDefault();
		  //RESOURCEMANAGER.detail_view.ask_for();
		});
		
		jQuery( "#resourcemanager-dialog-form" ).addClass("adminView");
		jQuery( "#resourcemanager-dialog-form" ).removeClass("userView");
	},
	
	adminifyDialog: function(askFor){
		let saveText = __( 'Save', RESOURCEMANAGER.config.plugin_name );
		if(askFor){
			saveText = __( 'Confirm', RESOURCEMANAGER.config.plugin_name );
		}
		
		this.dialog = jQuery( "#resourcemanager-dialog-form" ).dialog({
		  //autoOpen: false,
		  height: 600,
		  width: 400,
		  //modal: true,
		  buttons: {
			[__( 'Delete', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.remove,
			[saveText]: RESOURCEMANAGER.detail_view.save,
			[__( 'Cancel', RESOURCEMANAGER.config.plugin_name )]: RESOURCEMANAGER.detail_view.cancel
		  },
		  close: function() {
			RESOURCEMANAGER.detail_view.form[0].reset();
		  }
		});
			 
		this.form = this.dialog.find( "form" ).on( "submit", function( event ) {
		  event.preventDefault();
		  RESOURCEMANAGER.detail_view.save();
		});
		
		jQuery( "#resourcemanager-dialog-form" ).addClass("adminView");
		jQuery( "#resourcemanager-dialog-form" ).removeClass("userView");
	},
}
