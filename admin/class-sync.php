<?php
/**
 * The Sync page
 */
class Wc_Ls_Sync {

	/**
	 * The single instance of the class.
	 *
	 * @var Wc_ls_Sync
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
	private $transient_timeout = 150;

	/**
	 * loystar products
	 * @var array
	 */
	public $l_products = array();

	/**
	 * Actual synced loystar products
	 * @var array
	 */
	public $synced_l_products = array();

	/**
	 * Not synced products
	 * @var int
	 */
	public $not_synced_products = 0;

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
		$this->page_slug = wc_loystar()->parent_slug().'-sync';
		$this->capability = wc_loystar()->user_capability();
		$this->current_user_id = get_current_user_id();
		include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$this->loystar_api = new Wc_Ls_Api();
		add_action('admin_footer',array($this,'add_jquery'));
		add_action('admin_menu', array($this, 'sync_menu'), 50);
	}

	/**
	 * init sync menu
	 */
	public function sync_menu() {
		$parent_slug = wc_loystar()->parent_slug();
		$page_title = __('Sync Loystar Products with Woocommerce', WC_LS_TEXT_DOMAIN);
		$menu_title = __('Sync Products', WC_LS_TEXT_DOMAIN);
		$menu_slug = $this->page_slug;
		$capability = $this->capability;
		$function = array($this,'sync_page');
		add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
	}
	
	/**
	 * Adds jquery
	 */
	public function add_jquery(){
		?>
		<script type="text/javascript">
		var $ = jQuery;
		$(document).ready(function(){
			const el = '#sync-form button';
			$('#sync-form').submit(function(){
				$(el+' .dashicons').attr('class','dashicons dashicons-update spin');
				$(el+' .text').text('Syncronising, plase wait, might take a while');
				$(el).attr('title','Syncronising, if loading seems to be longer than usual, try reloading the page');
				$(el).attr('disabled','true');
			});
			//if esc key is pressed
			$(document).keyup(function(e) {
   				if(e.key === "Escape") { // escape key maps to keycode 27
					$(el+' .dashicons').attr('class','dashicons dashicons-admin-links');
					$(el+' .text').text('Sync loystar products with woocommerce');
					$(el).removeAttr('disabled');
					$(el).attr('title','sync all your loystar products with your woocommerce store');
    			}
			});
		});
		</script>
		<?php
	}

	/**
	 * Display sync page
	 */
	public function sync_page() {
		//redirect safely to login if merchant isnt authorised
		wc_loystar()->merchant_auth();
		if(!current_user_can(wc_loystar()->user_capability()))
			wp_die(__('You do not have sufficient permission to access this page.',WC_LS_TEXT_DOMAIN));

		echo wc_loystar()->notice_me('Sync your loystar products with your woocommerce store for proper inventory tracking.','info',true);
		//echo wc_loystar()->notice_me('Note: if you want to re-import already synced products, make sure you delete the products <strong>permanently</strong> from your woocommerce store','info',true);
		$api = $this->loystar_api;
		$this->l_products = $api->get_merchant_products();
		$this->synced_l_products = wc_loystar()->get_actual_linked_woo_products(0,true);
		$this->not_synced_products = (!$this->l_products ? 0 : count($this->l_products) ) - (!$this->synced_l_products ? 0 : count($this->synced_l_products) );
			?>
		<div class="ls-login-main">
			<div class="limiter">
				<div class="container-login100">
		<?php 
			$this->box_display();
		?>
		</div></div></div>
		<?php
	}

	/**
	* Display box
	*/
	public function box_display(){
		$this->sync_products();
		//records
		$api = $this->loystar_api;
		$l_products = $this->l_products;
		$synced_l_products = $this->synced_l_products;
		$not_synced_products = $this->not_synced_products;
		$details = array(
			'Loystar Products' => wc_loystar()->number( (!$l_products ? '0':count($l_products) ) ),//count(false)returns 1, php 5.6 shaa :|
			'Synced Loystar Products' => wc_loystar()->number( (!$synced_l_products ? '0':count($synced_l_products) ) ),
			'Not synced Loystar Products' => wc_loystar()->number( ($not_synced_products < 0 ? '0' : $not_synced_products) )
		);
		?>
		<div class="ls-checkbox-holder sync-details">
			<form id="sync-form" method="post">		
			<div class="row">
			<?php
			foreach($details as $key=>$value){
				?>
				<div class="row inner-row">
				<div class="col-sm-7 key">
				 <strong><?php echo $key; ?></strong>
			</div>
			<div class="col-sm-4 value"><?php echo $value; ?></div>
			</div>
			<?php
			}
			?>
			</div>
			<?php  wp_nonce_field('ls-sync-products', 'ls-sync-products'); ?>
			<div class="row">
				<div class="col-md-4">
				<p class="ls-note" style="margin-top:5px;">
				<?php
					$disabled_attr = 'title="sync all your loystar products with your woocommerce store"';
					if($not_synced_products < 1)
						$disabled_attr = 'disabled title="Cant sync products at the moment"';
					if($l_products && ($not_synced_products == 0 || $not_synced_products < 0) ){//valid count
				?>
				<span class="dashicons dashicons-yes"></span>All loystar products Synced.</p>
				<?php
					}
					else if(!$l_products){
				?>
				<span class="dashicons dashicons-no-alt"></span>
				Couldn't Retrieve Product information from loystar, please <a href="<?php echo wc_loystar()->parent_slug(true).'-sync'; ?>">reload</a> page.
				</p>
				<?php
					}
				?>
				</div>
				<div class="col-md-8" style="margin-top:4px;">
					<button type="submit" <?php echo $disabled_attr; ?>>
						<span class="dashicons dashicons-admin-links"></span>
						<span class="text">Sync Loystar products with woocommerce</span>
					</button>
				</div>
			</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles Product syncronisation
	 */
	public function sync_products(){
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls-sync-products']) && wp_verify_nonce($_POST['ls-sync-products'], 'ls-sync-products')) {
			//start work :)
			$l_products = $this->l_products;
			if(!$l_products || count($l_products) < 1){
				//Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
				$this->notice_msg = "An error occured, couldn't sync loystar products, try again later";
				if(empty($api->error)){
					$this->notice_msg = "Couldn't find any loystar product to Sync.";
				}
				set_transient($this->transient_f_value.$this->current_user_id,$this->notice_msg,$this->transient_timeout);
			}
			else{//worked
				$synced_num = 0;
				foreach($l_products as $p){
					//call the baba function
					$product_id = wc_loystar()->add_woo_product_from_loystar($p['id'],$l_products,true,false);
					if($product_id > 0){
						$synced_num++;
					}
				}
				//Delete transient for the failed value
				delete_transient($this->transient_f_value.$this->current_user_id);
				$msg = 'Successfully Synced <strong>'.wc_loystar()->number( $synced_num ).'</strong> loystar product'.($synced_num > 1 ? 's':'').' with your woocommerce store';
				if( $synced_num == 0 && $this->not_synced_products == 0 && $l_products)//sincerely all products are synced :)
					$msg = 'All Loystar products have been synced successfully';
				set_transient($this->transient_s_value.$this->current_user_id,$msg,$this->transient_timeout);
			}
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
}
new Wc_ls_Sync();
