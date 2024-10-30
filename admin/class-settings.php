<?php
/**
 * The settings page
 */
class Wc_Ls_Settings {

	/**
	 * The single instance of the class.
	 *
	 * @var Wc_Ls_Settings
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
	//all sub pages have the same transient value, so that way, we call add notice only on this page :)
	/**
	 * Transient value for failed note
	 * @var string
	 */
	private $transient_f_value = 'wc_loystar-setting-note-f';

	/**
	 * Transient timeout in seconds
	 * @var int
	 */
	private $transient_timeout = 150;

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
		$this->page_slug = wc_loystar()->parent_slug().'-settings';
		$this->capability = wc_loystar()->user_capability();
		$this->current_user_id = get_current_user_id();
		include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$this->loystar_api = new Wc_Ls_Api();
		add_action('admin_menu', array($this, 'setting_menu'), 50);
		add_action('admin_footer',array($this,'add_jquery'));	
		if(wc_loystar()->merchant_logged_in()){
			add_action('admin_head',array($this,'add_nologin_style'));
		}
	//	add_action('admin_init',function(){//this serves as same admin notice for all pages since they always load together, 
			//that was what was causing the error repeat :)
			if(isset($_GET['notice'])){
				add_action('admin_notices',array($this,'success_submit_notice'));
				//if(!$_GET['forced_login_msg'])//no need to add again
				add_action('admin_notices',array($this,'failed_submit_notice'));
			}
			else if(isset($_GET['forced_login_msg']) && !isset($_GET['notice'])){//user was forced here, add a message,also avoid message conflict
				set_transient($this->transient_f_value.$this->current_user_id,$_GET['forced_login_msg'],$this->transient_timeout);
				add_action('admin_notices',array($this,'failed_submit_notice'));	
			}
			//Delete transient for the other values, incase, doesn't work for some reason, so commented
			//delete_transient($this->transient_s_value.$this->current_user_id);
			//delete_transient($this->transient_f_value.$this->current_user_id);
				
		//});
	}

	/**
	 * init setting menu
	 */
	public function setting_menu() {
		$parent_slug = wc_loystar()->parent_slug();
		$page_title = __('Loystar Settings', WC_LS_TEXT_DOMAIN);
		$menu_title = __('Settings', WC_LS_TEXT_DOMAIN);
		$menu_slug = $this->page_slug;
		$capability = $this->capability;
		$function = array($this,'settings_page');
		add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
	}

	/**
	 * Register and enqueue admin styles and scripts
	 */
	public function admin_scripts() {
		//first file to be loaded already does the work :)
	}

	/**
	 * adds a style to the checkbox holder
	 * 
	 * Cause when the login box is not there, it goes down to the middle :)
	 */
	public function add_nologin_style(){
		?>
		<style type="text/css">
			.ls-checkbox-holder{
				position:absolute;
				top:8px;
			}
		</style>
		<?php
	}
	/**
	 * Adds jquery
	 */
	public function add_jquery(){
		?>
		<script type="text/javascript">
		var $ = jQuery;
		function loystarChangeBox(element){
			if($(element).is(':checked')){
				$(element).css('visibility','hidden');
			//	$(this+':before').css('visibility','visible');
			}
			else{
				$(element).css('visibility','visible');
			}
		}
		$(document).ready(function(){
			loystarChangeBox('.ls-checkbox-holder input[type=checkbox]');
			$('.ls-checkbox-holder input[type=checkbox]').change(function(){
				loystarChangeBox(this);
			});
			//highlight text when shortcode text is clicked
			$('.shortcode-readonly').click(function(){
				$(this).select();
			});
		});
		</script>
		<?php
	}

	/**
	 * Display settings page
	 */
	public function settings_page(){
		if(!current_user_can(wc_loystar()->user_capability()))
			wp_die(__('You do not have sufficient permission to access this page.',WC_LS_TEXT_DOMAIN));
			
		//check if there are already keys so no need to show login
		if(wc_loystar()->merchant_logged_in()){
			$this->logout();//incase the user decides to logout
			global $wc_ls_option_meta;
			$email = get_option($wc_ls_option_meta['uid']);
			$form = '<form method="post">
				'.wp_nonce_field('ls-logout-key', 'ls-logout-key').'
				<button type="submit" class="ls-cool-btn" title="logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
			</form>';
			$this->notice_msg = 'Your Loystar account <strong>"'.$email.'"</strong> is connected and Active.'.$form;
			//Delete transient for the other values, incase
			delete_transient($this->transient_s_value.$this->current_user_id);
			delete_transient($this->transient_f_value.$this->current_user_id);
			echo wc_loystar()->notice_me($this->notice_msg,'success',false);
			$shortcode_msg = 'For your loyalty widget, copy and past this shortcode anywhere on your site <input type="text" readonly 
			value="['.WC_LS_LOYALTY_WIDGET_SHORTCODE.']" class="shortcode-readonly"><br/> Note:the widget is only active if you\'ve <strong>enabled 
			loystar on your store</strong>';
			echo wc_loystar()->notice_me($shortcode_msg,'info',false);
			?>
		<div class="ls-login-main">
			<div class="limiter">
				<div class="container-login100">
				
		<?php
			$this->enable_box_display();
		}
		else{
			?>
			<div class="ls-login-main">
			<div class="limiter">
				<div class="container-login100">
		<?php
			//call the function that validates
			$this->validate();
			$this->login_box();
		}
		?>
		</div></div></div>
		<?php
	}

	/**
	* Display Enable checkbox
	*/
	public function enable_box_display(){
		global $wc_ls_option_meta;
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls-enable']) && wp_verify_nonce($_POST['ls-enable'], 'ls-enable')) {
		
			$checkbox = filter_input(INPUT_POST, 'wc_ls_enable_box');
			$transient_value = '';
			$checkbox_result = false;
			$doing_what = '';
			if(!empty($checkbox)){//update options table
				$transient_value = 'Loystar Loyalty Program <strong>Enabled</strong> on your site successfully!';
				$checkbox_result = 1;
				$doing_what = 'enable';
			}
			else{
				$transient_value = 'Loystar loyalty program <strong>disabled</strong> on your site successfully!';
				$checkbox_result = false;
				$doing_what = 'disable';
			}
			$old_option = get_option($wc_ls_option_meta['enabled'],false);
			if( (!empty($old_option) && $old_option == $checkbox_result ) || update_option($wc_ls_option_meta['enabled'],$checkbox_result)){
				//Delete transient for the failed value
				delete_transient($this->transient_f_value.$this->current_user_id);
				set_transient($this->transient_s_value.$this->current_user_id,$transient_value,$this->transient_timeout);
			}
			else{
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				set_transient($this->transient_f_value.$this->current_user_id,'Sorry, an error occurred while trying to <strong>'.$doing_what.'</strong> the Loystar loyalty program, Try again later.',$this->transient_timeout);
			}
			//redirect safely to show new value
			wp_safe_redirect(add_query_arg(array('notice'=>true) ));
			exit();
		}
		?>
		<div class="ls-checkbox-holder">
			<form method="post">
			<div class="row">
			<div class="col-sm-9">
				<input type="checkbox" value="true" <?php if(wc_loystar()->is_enabled()){ echo "checked";};?> name="wc_ls_enable_box">
				 <strong style="margin-left:7px;">Enable Loystar loyalty program</strong>
				<?php  wp_nonce_field('ls-enable', 'ls-enable'); ?>
			</div>
			<div class="col-sm-3">
				<button type="submit">Submit</button>
			</div>
			</div>
			</form>
		</div>
		<?php
	}
   
  /**
   * Display login box
   */
  public function login_box(){
	?>
			<div class="wrap-login100">
				<form class="login100-form validate-form" method="post">
					<span class="login100-form-logo">
						<img src="<?php echo wc_loystar()->logo_url(); ?>" style="width:100%" alt="loystar logo"/>
					</span>
					<span class="login100-form-title p-b-34 p-t-27">
						Sign in
					</span>
					<div class="wrap-input100 validate-input" data-validate = "Enter email">
						<input class="input100" type="email" name="wc_ls_email" placeholder="Email Address" value="<?php echo $this->remember_value('wc_ls_email'); ?>" required>
						<span class="focus-input100 fa email"></span>
					</div>
					<div class="wrap-input100 validate-input" data-validate="Enter password">
						<input class="input100" type="password" name="wc_ls_password" placeholder="Password" required>
						<span class="focus-input100 password fa"></span>
					</div>
					<?php  wp_nonce_field('ls-submit-key', 'ls-submit-key'); ?>
					<div class="container-login100-form-btn">
						<button type="submit" class="login100-form-btn">
							Sign In to Loystar
						</button>
					</div><br/>
					<p class="ls-note">No Loystar account? <a href="https://loystar.co/signup" target="_blank" title="Request">Request an Account</a>. </p>
				</form>
			</div>
	  <?php
  }

	/**
	 * Handles login/key validation
	 */
	public function validate(){
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls-submit-key']) && wp_verify_nonce($_POST['ls-submit-key'], 'ls-submit-key')) {	
			$email = filter_input(INPUT_POST, 'wc_ls_email');
			$password = filter_input(INPUT_POST, 'wc_ls_password');
			$sign_result = $this->loystar_api->sign_in($email,$password);
				if($sign_result){
					//Delete transient for the failed value
					delete_transient($this->transient_f_value.$this->current_user_id);
					set_transient($this->transient_s_value.$this->current_user_id,'Your Loystar account has been validated!',$this->transient_timeout);
					if(isset($_GET['red_back'])){//user was forced here
						unset($_GET['forced_login_msg']);
						//redirect safely to show new value
						//$url = $_SERVER['REQUEST_SCHEME'].'//'.$_SERVER['HTTP_HOST'].$_GET['red_back'];
						wp_safe_redirect(add_query_arg(array(), $_GET['red_back'] ) );
						exit();	
					}	
				}
				else{
					//Delete transient for the success value
					delete_transient($this->transient_s_value.$this->current_user_id);
					//check for the type of error
					switch($this->loystar_api->error_type){
						// -1 is internal server error,1 is unathorised,2 is wp error, 3 is invalid details
						case 3:
							$this->notice_msg = 'Invalid Login Details, Please try again';
						break;
						default:
							$this->notice_msg = 'Sorry, an error occured, Please try again later';
					}
					set_transient($this->transient_f_value.$this->current_user_id,$this->notice_msg,$this->transient_timeout);
				}
				$this->remember_values();
				//redirect safely to show new value
				wp_safe_redirect(add_query_arg(array('notice'=>true) ));
				exit();
			}
	}
	/**
	 * Logout the merchant
	 */
	public function logout(){
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls-logout-key']) && wp_verify_nonce($_POST['ls-logout-key'], 'ls-logout-key')) {
			wc_loystar()->logout_merchant(false);
			if(!wc_loystar()->merchant_logged_in()){//the logout worked
				//Delete transient for the failed value
				delete_transient($this->transient_f_value.$this->current_user_id);
				set_transient($this->transient_s_value.$this->current_user_id,'Logged out successfully!',$this->transient_timeout);
			}
			else{//didnt work
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				set_transient($this->transient_f_value.$this->current_user_id,'Sorry, an error occured trying to logout, try again later.',$this->transient_timeout);		
			}
			//redirect safely to show new value
			wp_safe_redirect(add_query_arg(array('notice'=>true) ));
			exit();
		}
	}

	/**
	 * Successful submit message
	 */
	public function success_submit_notice(){
		$t_value = $this->transient_s_value.$this->current_user_id;
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
		return '';			
	}

	/**
	 * Helps store the form values in transient
	 * 
	 */
	private function remember_values(){
		foreach($_POST as $key=>$value){
			if($key != 'wc_ls_password')
				set_transient($key.$this->current_user_id,$value,0);
		}
	}
}
new Wc_Ls_Settings();
