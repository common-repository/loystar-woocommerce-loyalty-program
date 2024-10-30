<?php
/**
 * Shows the list of loyalty programs of a merchant
 */
class Wc_Ls_Loyalty {

	/**
	 * The single instance of the class.
	 *
	 * @var Wc_Ls_Loyalty
	 * @since 1.0
	 */
	protected static $_instance = null;

	/**
	 * Wc_Ls_Loyalty_Details Class Object
	 * @var Wc_Ls_Loyalty_Details 
	 */
	public $loyalty_details_table = NULL;
	
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
		$this->page_slug = wc_loystar()->parent_slug();
		$this->capability = wc_loystar()->user_capability();
		$this->current_user_id = get_current_user_id();
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'), 10);
		add_filter('set-screen-option', array($this, 'set_loyalty_screen_options'), 10, 3);
		add_action('admin_menu', array($this, 'menu'), 10);
		add_action('admin_footer',array($this,'add_table_style'));
	}

	/**
	 * init menu
	 */
	public function menu() {
		$parent_slug = wc_loystar()->parent_slug();
		//menu stuff
		$page_title = __('Loystar Woocommerce', WC_LS_TEXT_DOMAIN);
		$menu_title = __('Loystar', WC_LS_TEXT_DOMAIN);
		$menu_slug = $this->page_slug;
		$capability = $this->capability;
		$function = array($this,'loyalty_details_page');
		$icon = 'https://loystar.co/wp-content/uploads/2016/04/favicon-32x32-3.png';
		$position = 58;
		 add_menu_page($page_title,$menu_title,$capability,$menu_slug,$function,$icon,$position);
		 //create duplicate of menu page in sub menu for it to work properly :( https://developer.wordpress.org/reference/functions/add_submenu_page/
		//although it finally worked without doing this,but oh well 
		$menu_page_hook_view = add_submenu_page($parent_slug,__('Loyalty Programs', WC_LS_TEXT_DOMAIN), __('Loyalty Programs', WC_LS_TEXT_DOMAIN), $capability, $parent_slug, $function);
		add_action("load-$menu_page_hook_view", array($this, 'add_loyalty_details_option'));
	}

	/**
	 * Register and enqueue admin styles and scripts
	 */
	public function admin_scripts() {
		// register styles
		wp_register_style('wc_ls_admin_styles', wc_loystar()->plugin_url() . '/assets/css/admin-main'.WC_LS_MIN_SUFFIX.'.css', array(), WC_LS_PLUGIN_VERSION);
		wp_enqueue_style('wc_ls_admin_styles');
		// Register scripts
		//wp_register_script('wc_ls_admin_js', wc_loystar()->plugin_url() . '/assets/js/admin-main'.WC_LS_MIN_SUFFIX.'.js', array('jquery'), WC_LS_PLUGIN_VERSION);
		//wp_enqueue_script('wc_ls_admin_js');
	}
	
	/**
	 * adds a style to the table
	 * 
	 */
	public function add_table_style(){
		global $wc_ls_option_meta;
		$id = get_option($wc_ls_option_meta['loyalty_program']);
		?>
		<script type="text/javascript">
		var $ = jQuery;
		<?php
		if(!empty($id)){
			?>
			$('#the-list tr').has('#<?php echo $id; ?>').css('background-color','rgba(189, 100, 140,0.3)');
			<?php
		}
		?>
		$('#the-list .ls-cool-btn').css('margin','1px 10px');
		$('#the-list .ls-cool-btn').css('position','relative');
		$('#the-list .ls-cool-btn').css('top','6px');
		$('#the-list a[disabled]').attr('href','#!');
		$('.loystar-wc-table .wp-list-table').removeClass('fixed');//to fix the layout
		$('a[href="<?php echo wc_loystar()->parent_slug(true); ?>-loyalty"]').hide();//hide add page
		<?php
			if(wc_loystar()->is_merchant_subscription_expired()){
            ?>
			    $(".wc-ls-action-btn").click(function(e){e.preventDefault();});    
			    $(".ls[disabled]").click(function(e){e.preventDefault();});    
			<?php
			}
		?>
		</script>
		<?php
	}

	/**
	 * set loyalty screen option
	 */
	public function set_loyalty_screen_options($screen_option, $option, $value){
		if('results_per_page' === $option){
			$screen_option = $value;
		}
		return $screen_option;
	}

	/**
	 * Loyalty details page initialization
	 */
	public function add_loyalty_details_option() {
		if(!wc_loystar()->loyalty_program() && !wc_loystar()->is_merchant_subscription_expired()){//remind user to select a loyalty program
			add_action('admin_notices',function(){
				echo wc_loystar()->notice_me('Please Choose a Loyalty program to be active on this site');	
			},15);
		}
		//redirect safely to login if merchant isnt authorised
		wc_loystar()->merchant_auth();
		$option = 'per_page';
		$args = array(
			'label' => 'Number of items per page:',
			'default' => 15,
			'option' => 'results_per_page'
		);
		add_screen_option($option, $args);
		include_once( WC_LS_ABSPATH . 'includes/class-loystar-loyalty-program-details.php' );
		$this->loyalty_details_table = new Wc_Ls_Loyalty_Details();
		$this->loyalty_details_table->prepare_items();
	}

	/**
	 * Display loyalty details page
	 */
	public function loyalty_details_page() {
		$this->update_loyalty_program();//run the updater on top
		?>
		<div class="wrap loystar-wc-table">
			<h3><?php _e('Loyalty Programs', WC_LS_TEXT_DOMAIN); ?> </h3>
			<?php
				$dis = '';
				if(wc_loystar()->is_merchant_subscription_expired())
					$dis = ' disabled title="Subscribe to enable this feature." ';
				?>
				<a class="ls page-title-action"<?php echo $dis; ?> href="<?php echo add_query_arg(array(),admin_url(wc_loystar()->parent_slug(true).'-loyalty')); ?>"><span class="dashicons dashicons-plus" style="vertical-align: middle;"></span> Add Loyalty Program</a>
			<?php 
			$c = $this->loyalty_details_table->result_count;
			$t_type = 'warning';
			$dismissible = false;
			$txt = '';
			if($c == -1){
				$txt = 'An error occured while fetching your Loyalty program list, please try again later.';
				$t_type = 'error';
			}
			else if($c == 0){
				$txt = 'You do not have any loyalty program.';
				$t_type = 'warning';
			}
			else{//perfect
			}
				echo wc_loystar()->notice_me($txt,$t_type,$dismissible);
			 ?>
			<form id="posts-filter" method="get">
			<?php /*$this->loyalty_details_table->search_box(__('Search Users', WC_LS_TEXT_DOMAIN), 'search_id');*/ ?>
		<?php $this->loyalty_details_table->display(); ?>
			</form>
			<div id="ajax-response"></div>
			<br class="clear"/>
		</div>
		<?php
	}

	/**
	 * updates the chosen program
	 */
	public function update_loyalty_program(){
		global $wc_ls_option_meta;
		if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ls-update-program']) && wp_verify_nonce($_GET['ls-update-program'], 'ls-update-program')) {
			
			if(wc_loystar()->is_merchant_subscription_expired()){//prevent users with expired subscription
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				set_transient($this->transient_f_value.$this->current_user_id,'Sorry, your subscription is expired, you are not allowed to activate a loyalty program.',$this->transient_timeout);
				//redirect safely to show new value
				wp_safe_redirect(add_query_arg(array('notice'=>true), admin_url(wc_loystar()->parent_slug(true) ) ));
				exit();
			}
			$box = filter_input(INPUT_GET, 'wc_ls_updater');
			
			if( (get_option($wc_ls_option_meta['loyalty_program'],false) == $box ) || update_option($wc_ls_option_meta['loyalty_program'],$box)){
				$transient_value = 'Loyalty program activated successfully';
				//Delete transient for the failed value
				delete_transient($this->transient_f_value.$this->current_user_id);
				set_transient($this->transient_s_value.$this->current_user_id,$transient_value,$this->transient_timeout);
			}
			else{
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				set_transient($this->transient_f_value.$this->current_user_id,'Sorry, an error occurred while trying to activate, Try again later.',$this->transient_timeout);
			}
			//redirect safely to show new value
			wp_safe_redirect(add_query_arg(array('notice'=>true), admin_url(wc_loystar()->parent_slug(true) ) ));
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
	 * Faild submit message
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
}
new Wc_Ls_Loyalty();
