<?php
/**
 * Shows the list of customers of a merchant
 */
class Wc_Ls_Customers {

	/**
	 * The single instance of the class.
	 *
	 * @var Wc_Ls_Customer
	 * @since 1.0
	 */
	protected static $_instance = null;

	/**
	 * Wc_Ls_Customer_Details Class Object
	 * @var Wc_Ls_Customer_Details 
	 */
	public $customer_details_table = NULL;

	/**
	 * Customer count
	 * 
	 * @var int
	 */
	public $customer_count = 0;
	
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
	//no need for transient ish as per success message

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
		$this->page_slug = wc_loystar()->parent_slug().'-customers';
		$this->capability = wc_loystar()->user_capability();
		$this->current_user_id = get_current_user_id();
		add_filter('set-screen-option', array($this, 'set_customer_screen_options'), 10, 3);
		add_action('admin_menu', array($this, 'menu'), 10);
	}

	/**
	 * init menu
	 */
	public function menu() {
		$parent_slug = wc_loystar()->parent_slug();
		//menu stuff
		$page_title = __('Loystar customers', WC_LS_TEXT_DOMAIN);
		$menu_title = __('Customers', WC_LS_TEXT_DOMAIN);
		$menu_slug = $this->page_slug;
		$capability = $this->capability;
		$function = array($this,'customer_details_page');
		$menu_page_hook_view = add_submenu_page($parent_slug,$page_title, $menu_title, $capability, $menu_slug, $function);
		add_action("load-$menu_page_hook_view", array($this, 'add_customer_details_option'));
	}

	/**
	 * set customer screen option
	 */
	public function set_customer_screen_options($screen_option, $option, $value){
		if('results_per_page' === $option){
			$screen_option = $value;
		}
		return $screen_option;
	}

	/**
	 * customer details page initialization
	 */
	public function add_customer_details_option() {
		//redirect safely to login if merchant isnt authorised
		wc_loystar()->merchant_auth();
		$option = 'per_page';
		$args = array(
			'label' => 'Number of items per page:',
			'default' => 15,
			'option' => 'results_per_page'
		);
		add_screen_option($option, $args);
		include_once( WC_LS_ABSPATH . 'includes/class-loystar-customer-details.php' );
		$this->customer_details_table = new Wc_Ls_Customer_Details();
		$this->customer_details_table->prepare_items();
	}

	/**
	 * Display customer details page
	 */
	public function customer_details_page() {
		?>
		<div class="wrap loystar-wc-table">
			<h3><?php _e('Customers', WC_LS_TEXT_DOMAIN); ?> </h3>
			<?php 
				//add count
				$this->customer_count = $this->customer_details_table->result_count;
				$c = $this->customer_count;
				$txt = '<strong>'.$c.'</strong> Total Customers';
				$t_type = 'info';
				if($c == 1)
					$txt = '<strong>'.$c.'</strong> Customer';
				else if($c == 0)
					$txt = 'No customer record.';
				else if($c == -1){//ha, api error
					$txt = 'Couldn\'t fetch customer list from loystar, please try again later.';
					$t_type = 'error';
				}
				echo wc_loystar()->notice_me($txt,$t_type,false);
			?>
			<form id="posts-filter" method="get">
			<?php /*$this->customer_details_table->search_box(__('Search Users', WC_LS_TEXT_DOMAIN), 'search_id');*/ ?>
		<?php $this->customer_details_table->display(); ?>
			</form>
			<div id="ajax-response"></div>
			<br class="clear"/>
		</div>
		<?php
	}
}
new Wc_Ls_Customers();
