<?php
/**
 * The settings page
 */
class Wc_Ls_Add_Loyalty {

	/**
	 * The single instance of the class.
	 *
	 * @var Wc_Ls_Add_Loyalty
	 * @since 1.0
	 */
	protected static $_instance = null;

	/**    
     * The Loystar api object
     * 
     * @var Wc_Ls_Api obj
     */
	private $loystar_api = null;
	
	/**
	 * The notice message
	 * 
	 * @var string
	 */
	protected $notice_msg = '';

	/**
	 * Logged in user id
	 * 
	 * @var int
	 */
	protected $current_user_id = 0;

	/**
	 * Page slug
	 * 
	 * @var string
	 */
	private $page_slug = '';

	/**
	 * User capability
	 * 
	 * @var string
	 */
	private $capability = '';

	/**
	 * Transient value for success note
	 * @var string
	 */
	private $transient_s_value = 'wc_loystar-setting-note-s';

	/**
	 * Transient value for failed note
	 * @var string
	 */
	private $transient_f_value = 'wc_loystar-setting-note-f';

	/**
	 * Transient timeout in seconds
	 * @var int
	 */
	private $transient_timeout = 5;

	/**
	 * Text to show, either create or edit
	 * @var array
	 */
	private $text = array('Create','Created');

	/**
	 * if its a new program to be added, or its being edited
	 * @var string
	 */
	private $adding = true;

	/**
	 * the loyalty data
	 * @var array
	 */
	private $data = array();

	/**
	 * Coupon page
	 * @var string
	 */
	private $coupon_page = '';

	/**
	 * Main instance
	 * @return class object
	 */
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	public function __construct() {
		if(wc_loystar()->is_merchant_subscription_expired()){
			return;
		}
		$this->coupon_page = admin_url('post-new.php?post_type=shop_coupon&ls_coupon_link_to=');
		$this->capability = wc_loystar()->user_capability();
		$this->page_slug = wc_loystar()->parent_slug().'-loyalty';
		$this->current_user_id = get_current_user_id();
		include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$this->loystar_api = new Wc_Ls_Api();
		add_action('admin_menu', array($this, 'menu'), 10);
		add_action('admin_footer',array($this,'add_little_jquery'));
		if($_GET['loyalty_id']){//editing
			$this->data = $this->loystar_api->get_merchant_loyalty($_GET['loyalty_id']);
			if($this->data){//worked, carry on with editing
				$this->adding = false;
				$this->text = ['Update','updated'];
			}
		}
	}

	/**
	 * init menu
	 */
	public function menu() {
		$parent_slug = wc_loystar()->parent_slug();
		$title = (!$this->adding ? 'Edit':'Add');
		$page_title = __($title.' Loyalty Program', WC_LS_TEXT_DOMAIN);
		$menu_title = __('', WC_LS_TEXT_DOMAIN);//best way to hide from menu
		$menu_slug = $this->page_slug;
		$capability = $this->capability;
		$function = array($this,'_page');
		add_submenu_page($parent_slug,$page_title, $menu_title, $capability, $menu_slug, $function);
	}

	/**
	 * adds a jquery
	 */
	public function add_little_jquery(){
		?>
		<script type="text/javascript">
			//get the drop down value
			var $ = jQuery;
			var wcCurrency = '<?php echo get_woocommerce_currency_symbol(); ?>';
			<?php 
			$program_type_val = '';
			if(!$this->adding){
				$program_type_val = $this->data['program_type'];
			}
			?>
			function lsToggleProgram(){
				var el = 'select[name="wc_ls_program_type"]';
				if($(el).val() == "SimplePoints"){
					$('.threshold label').html('Spending target <strong>('+wcCurrency+')</strong>');
					$('.threshold small').html('Spending target is how much a customer must spend to earn a reward. <strong>'+wcCurrency+'1 = 1 point</strong>');
					$('.threshold input').removeAttr('max');
				}
				else{
					$('.threshold label').text('Stamps target');
					$('.threshold small').text('Stamps target is how many stamps a customer must collect to earn the reward. E.g 5');
					$('.threshold input').attr('max','20');
				}
			}
			$(document).ready(function(){
				$('select[name="wc_ls_program_type"]').val("<?php echo ((!empty($program_type_val)) ? $program_type_val : $this->remember_value('wc_ls_program_type') ); ?>");
				lsToggleProgram();
				$('select[name="wc_ls_program_type"]').change(function(){
					lsToggleProgram();
				});
			<?php if(isset($_GET['coupon_red']) && isset($_GET['loyalty_p'])){//a coupon redirect is to be done
				?>
				window.setTimeout(function(){
					window.location.href = "<?php echo $this->coupon_page.$_GET['loyalty_p']; ?>";
				},6000);
			<?php
				}
			?>
			});
		</script>
		<?php	
	}

	/**
	 * Display page
	 */
	public function _page() {
		//redirect safely to login if merchant isnt authorised
		wc_loystar()->merchant_auth();		
		if(!current_user_can(wc_loystar()->user_capability()))
			wp_die(__('You do not have sufficient permission to access this page.',WC_LS_TEXT_DOMAIN));
		if(wc_loystar()->is_merchant_subscription_expired())
			wp_die(__(wc_loystar()->get_default_subscription_note(),WC_LS_TEXT_DOMAIN));
		?>
		<style type="text/css">
		.input100{
			margin:3px 0 !important;
		}
		.wrap-input100 label{
			margin:10px 0 3px 0;
			font-size:16px;
		}
		.wrap-input100 label:after{
			content:'*';
			margin-left:5px;
			margin-top:-1px;
			font-size:13px;
			font-weight:700;
			color:#ff0000;
		}
		</style>			
			<div class="ls-login-main">
			<div class="limiter">
				<div class="container-login100">
		<?php
			//call the function that validates
			$this->validate();
			$this->add_box();
		?>
		</div></div></div>
		<?php
	}

  /**
   * Display login box
   */
  public function add_box(){
	?>
			<div class="wrap-login100">
				<form class="login100-form validate-form" method="post">
					<span class="login100-form-logo">
						<img src="<?php echo wc_loystar()->logo_url(); ?>" style="width:100%;" alt="loystar logo"/>
					</span>

					<span class="login100-form-title p-b-34 p-t-27">
						<?php echo $this->text[0]; ?> loyalty Program
					</span>

					<?php 
					$adds_for_edit = ' hidden disabled';
					if($this->adding){//only valid for adding, not updating
						$adds_for_edit = '';
					}
						 ?>
					<div class="wrap-input100 validate-input<?php echo $adds_for_edit; ?>">
						<label>Select Program type</label>
						<select class="input100" type="text" name="wc_ls_program_type" placeholder="Program Type" <?php echo $adds_for_edit; ?> required>
						<option value=''>Select Program Type</option>
						<option value='SimplePoints'>Simple Points</option>
						<option value='StampsProgram'>Stamp Programs</option>
							  
						</select>
					</div>
					<div class="wrap-input100 validate-input">
						<label>Name of program</label>
						<input class="input100" type="text" name="wc_ls_name" placeholder="Name" value="<?php echo $this->remember_value('wc_ls_name'); ?>" required>
					</div>
					<div class="wrap-input100 validate-input">
						<label>What is the reward?</label>
						<input class="input100" type="text" name="wc_ls_reward" placeholder="Reward" value="<?php echo $this->remember_value('wc_ls_reward'); ?>" required>
						<small>E.g. Free airtime</small>
					</div>
					
					<div class="wrap-input100 validate-input">
						
						<input class="" type="checkbox" name="wc_ls_coupon_action" value="1"> Remind me to associate a woocommerce coupon to this loyalty program
						<small>(This will redirect you to the coupon page after submission.)</small>
					</div> 
					<div class="wrap-input100 validate-input threshold">
						<label>Stamps target</label>
						<input class="input100" type="number" name="wc_ls_threshold" placeholder="Threshold" value="<?php echo $this->remember_value('wc_ls_threshold'); ?>" required>
						<small></small>
					</div>
					<?php  wp_nonce_field('ls-loyalty-submit-key', 'ls-loyalty-submit-key'); ?>
					<div class="container-login100-form-btn">
						<button type="submit" class="login100-form-btn">
						<?php echo $this->text[0]; ?> Program
						</button>
					</div>

				</form>
			</div>
	  <?php
  }

	/**
	 * Handles login/key validation
	 */
	public function validate() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls-loyalty-submit-key']) && wp_verify_nonce($_POST['ls-loyalty-submit-key'], 'ls-loyalty-submit-key')) {
		
			$threshold = filter_input(INPUT_POST, 'wc_ls_threshold');
			$reward = filter_input(INPUT_POST, 'wc_ls_reward');
			$program_type = filter_input(INPUT_POST, 'wc_ls_program_type');
			$name = filter_input(INPUT_POST, 'wc_ls_name');
			$result = false;
			$link_args = array('notice'=>true);
			$this->remember_values();
			if(strtolower($program_type) == 'stampsprogram' && $threshold > 20){//incase an oversabi wants to bypass ;)
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				set_transient($this->transient_f_value.$this->current_user_id,'Maximum value for Stamps target is 20',$this->transient_timeout);
			}
			else{

			if(!$this->adding){//update loyalty program
				$result = $this->loystar_api->update_merchant_loyalty($_GET['loyalty_id'],[
					'name'=>$name,
					'reward'=>$reward,
					'threshold'=>$threshold,
					//'program_type'=>$program_type
					]);
			}
			else{
				$result = $this->loystar_api->create_merchant_loyalty($name,$reward,$threshold,$program_type);
			}
				if($result){
					//Delete transient for the failed value
					delete_transient($this->transient_f_value.$this->current_user_id);
					$extra_note='<a href="'.admin_url(wc_loystar()->parent_slug(true)).'" title="Go back to your loyalty list">Go back</a>'.( ($this->adding) ? ' to activate':'' ).'.';
					$coupon_action = isset($_POST['wc_ls_coupon_action']) ? 1 : 0;
					if($coupon_action == 1){//redirect
						$link = $this->coupon_page.$result['id'];
						$extra_note=' You\'ll be redirected to create a coupon shortly, you can also click <a href="'.$link.'" title="Go to coupon page">here</a> to create the coupon
						';
						$link_args['coupon_red'] = '1';
						$link_args['loyalty_p'] = $result['id'];
					}
					$note = 'Loyalty program '.$this->text[1].' successfully! '.$extra_note;
					set_transient($this->transient_s_value.$this->current_user_id,$note,$this->transient_timeout);
					if($this->adding)
						$this->remove_values();//clear for a new stuff
				}
				else{
					//Delete transient for the success value
					delete_transient($this->transient_s_value.$this->current_user_id);
					//check for the type of error
					switch($this->loystar_api->error_type){
						// -1 is internal server error,1 is unathorised,2 is wp error, 3 is invalid details
						case 4:
							$this->notice_msg = 'Couldn\'t '.$this->text[0].' loyalty program, check your details and try again';
						break;
						default:
							$this->notice_msg = 'Sorry, an error occured, Please try again later';
					}
					set_transient($this->transient_f_value.$this->current_user_id,$this->notice_msg,$this->transient_timeout);		
				}
			}
				//redirect safely to show new value
				wp_safe_redirect(add_query_arg($link_args));
				exit();
			}
	}

	/**
	 * Successful submit message
	 */
	public function success_submit_notice(){
		$t_value = $this->transient_s_value.$this->current_user_id;
		$success_f_value = $this->transient_f_value.$this->current_user_id;//remove the failed transient value 
		$transient = get_transient($t_value);
		if(!empty($transient)){
		?>
		<div class="notice notice-success is-dismissible">
    		<p><?php echo $transient; ?></p>
		</div>
		<?php
		}
	}

	/**
	 * Successful submit message
	 */
	public function failed_submit_notice(){
		$t_value = $this->transient_f_value.$this->current_user_id;
		$success_t_value = $this->transient_s_value.$this->current_user_id;//remove the success transient value 
		$transient = get_transient($t_value);
		if(!empty($transient)){
		?>
		<div class="error notice">
    		<p><?php echo $transient; ?></p>
		</div>
		<?php
		}

	}

	/**
	 * Helps echo the form values
	 * 
	 * @param string $name , the name attr of the input
	 * @return string
	 */
	private function remember_value($name){
		$value = get_transient($name.$this->current_user_id);
		if($value)
			return $value;
		elseif(!$this->adding){
			$name = str_replace('wc_ls_','',$name);
			return $this->data[$name];
		}
		return '';			
	}

	/**
	 * Helps store the form values in transient
	 * 
	 */
	private function remember_values(){
		foreach($_POST as $key=>$value){
			set_transient($key.$this->current_user_id,$value,$this->transient_timeout);
		}
	}

	/**
	 * Helps remove the form values in transient
	 * 
	 */
	private function remove_values(){
		foreach($_POST as $key=>$value){
			delete_transient($key.$this->current_user_id);
		}
	}

}

new Wc_Ls_Add_Loyalty();
