<?php
namespace ha\resourcemanager;
defined( 'ABSPATH' ) or die( 'Nope.' );

class Shortcode extends Configured {
	private $seconds_per_day = 60*60*24;
	
	public function init(){
		if(!is_admin()){
			add_shortcode( $this->config->plugin['type'], [$this, 'handler_function'] );
		}
	}
	
	public function handler_function($atts) {
        ob_start(); // Start output buffering
        $this->echo_handler_function($atts);
        $output = ob_get_clean(); // Get the output and clean the buffer
        return $output; // Return the output
    }
	
	public function echo_handler_function($atts){
		// normalize attribute keys, lowercase
		$atts = array_change_key_case( (array) $atts, CASE_LOWER );
			//$atts['cal_id'];
			//$atts['statistics'];
			
		if(isset($atts['statistics']) || isset($_GET['statistics'])){
			$this->handler_function_statistics_view($atts);
		}
		else{
			$this->handler_function_cal_view($atts);
		}
	}
	
	private function handler_function_statistics_view($atts){
		?>
		<div>
			<a href="./">&lt;--<?php echo esc_html__( 'Back', 'resourcemanager' )?></a>
		</div>
		
		<p>
			<input id='resourcemanager-datepicker-statistics-from' />
			<input id='resourcemanager-datepicker-statistics-to' />
		</p>
		
		
		<div id='statistics_vision' class='resourcemanager-vision' data-cal_id="<?php echo esc_attr($atts['cal_id'])?>"></div>
		<?php
	}
	
	private function handler_function_cal_view($atts){
		?>
		<div id='ask_for'></div>
		<p>
			<input type='button' id='resourcemanager-datepicker-left' value='&lt;' />
			<select id='resourcemanager-datepicker-year'>
				<?php
					for($y=2020; $y <= (date('Y')+1); $y++){
						echo "<option>".intval($y)."</option>";
					}
				?>
			</select>
			<select id='resourcemanager-datepicker-month'>
				<?php
					for($m=1; $m <= 12; $m++){
						echo "<option value='".intval($m)."'>".intval($m)."</option>";
					}
				?>
			</select>
			<input type='button' id='resourcemanager-datepicker-right' value='&gt;' />
		</p>
		
		<div id='monthly_vision' class='resourcemanager-vision' data-cal_id="<?php echo esc_attr($atts['cal_id'])?>"></div>
		
		<div id="resourcemanager-dialog-form" title="<?php echo esc_html__( 'Occupancy', 'resourcemanager' )?>">
			<form>
				<fieldset>
					<p>
						<?php echo esc_html__( 'Resource', 'resourcemanager' )?>: <span id='resource_id_txt'></span>
						<select name="resource_id" id="resource_id" class="adminView text ui-widget-content ui-corner-all fdata"></select>
						<input type="hidden" name="res_id_before" id='res_id_before' class="fdata" />
						<span class="userView"></span>
					</p>
					
					<p>
						<label for="bookingUser"><?php echo esc_html__( 'reserved for', 'resourcemanager' )?></label><br />
						<select name="bookingUser" id="bookingUser" class="adminView text ui-widget-content ui-corner-all fdata"></select>
						<input type="hidden" name="userType" id="userType" class="fdata" />
						<span class="userView"></span>
					</p>
					
					<div id='number'></div>

					<p>
						<label for="from_d"><?php echo esc_html__( 'from', 'resourcemanager' )?></label>
						<input type="date" name="from_d" id="from_d" class="adminView text ui-widget-content ui-corner-all fdata" />
						<input type="time" name="from_t" id="from_t" class="adminView text ui-widget-content ui-corner-all fdata" />
						<input type="hidden" name="from_d_hidden" id="from_d_hidden" class="fdata" />
						<input type="hidden" name="from_t_hidden" id="from_t_hidden" class="fdata" />
						<span class="userView"></span>
					</p>

					<p>
						<label for="to_d"><?php echo esc_html__( 'to', 'resourcemanager' )?></label>
						<input type="date" name="to_d" id="to_d" class="adminView text ui-widget-content ui-corner-all fdata" />
						<input type="time" name="to_t" id="to_t" class="adminView text ui-widget-content ui-corner-all fdata" />
						<span class="userView"></span>
					</p>
					
					<details class="recurring adminView">
						<summary class="adminView"><input type="hidden" name="recurring" id="recurring" value="0" class="fdata" /><?php echo esc_html__( 'recurring', 'resourcemanager' )?></summary>
						<div class="adminView">
							<!-- Inhalt deines ausklappbaren Abschnitts -->
							<p>
								<label for="every_i"><?php echo esc_html__( 'every', 'resourcemanager' )?></label>
								<input type="number" name="every_i" id="every_i" min="1" step="1" size="2" value="1" class="adminView text ui-widget-content ui-corner-all fdata" />
								<select name="every_u" id="every_u" class="adminView text ui-widget-content ui-corner-all fdata">
									<option value="day"><?php echo esc_html__( 'days', 'resourcemanager' )?></option>
									<option value="week"><?php echo esc_html__( 'weeks', 'resourcemanager' )?></option>
									<option value="month"><?php echo esc_html__( 'months', 'resourcemanager' )?></option>
									<option value="year"><?php echo esc_html__( 'years', 'resourcemanager' )?></option>
								</select>
								<input type="hidden" name="from_d_hidden" id="from_d_hidden" class="fdata" />
							</p>
							
							<p>
								<label for="till_d"><?php echo esc_html__( 'till', 'resourcemanager' )?></label>
								<input type="date" name="till_d" id="till_d" class="adminView text ui-widget-content ui-corner-all fdata" />
								<input type="hidden" name="from_d_hidden" id="from_d_hidden" class="fdata" />
							</p>
							
						</div>
					</details>
					<div class="userView"></div>
					
					<div id='customFields'></div>
					
					<!-- Allow form submission with keyboard without duplicating the dialog button -->
					<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
				</fieldset>
			</form>
		</div>
		
		<div id="resourcemanager-dialog-confirm" title="Confirm action">
			<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span><?php echo esc_html__( 'These items will be permanently deleted and cannot be recovered. Are you sure?', 'resourcemanager' )?></p>
		</div>
		
		<div id="resourcemanager-progressbar-dialog" title="<?php echo esc_html__( 'Loading', 'resourcemanager' )?>..">
			<div></div>
		</div>
		
		<div>
			<a href="./?statistics=1"><?php echo esc_html__( 'Statistics for this group', 'resourcemanager' )?> --&gt;</a>
		</div>
		<?php
	}
}

Config::$instances['shortcode'] = new Shortcode();
add_action('template_redirect', [ Config::$instances['shortcode'], 'init']);
add_filter( 'widget_text', 'do_shortcode' );
?>
