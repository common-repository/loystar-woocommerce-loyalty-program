<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class Wc_Loystar {

    /**
     * The single instance of the class.
     *
     * @var Wc_Loystar
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Exception error 
     * 
     * @var Exception
     */
    private $exception = null;
    
    /**
     * Mumu cache
     * 
     * this stores lists of some responses gotten, so as not to be curled everytime
     * 
     * @var array
     */
    private $cached_data = array('products'=>[],'categories'=>[]);

    /**
     * sync interval
     * 
     * the interval for croning stuff in minutes
     * 
     * @var int
     */
    private $sync_interval = 12 * 60;

    /**
     * Ignored keys
     * 
     * Keys to ignore from $wc_ls_option_meta when checking for login auth
     * 
     * @var array
     */
    public $ignored_keys = array('enabled','loyalty_program','sub_expires','branch','last_cron');
    
    /**
     * Ignored relevant keys
     * 
     * Keys that can be ignored from $wc_ls_option_meta when checking for login auth
     * but when logging out, shouldn't be ignored, forgive me, i didnt have a better name :)
     * 
     * @var array
     */
    public $ignored_relevant_keys = array('sub_expires','branch');

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
     * Gets exception
     * 
     * @return Exception
     */
    public function get_exception(){
        return $this->exception;
    }
    /**
     * Sets exception
     * 
     * @param Exception $value
     */
    public function set_exception($value){
        $this->exception = $value;
    }

    /**
     * Gets cached data
     * 
     * @return array
     */
    public function get_cached_data(){
        return $this->cached_data;
    }
    /**
     * Sets cached data
     * 
     * @var array
     */
    public function set_cached_data($value){
        $this->cached_data = $value;
    }

    /**
     * Clears cached data
     * 
     */
    public function clear_cached_data(){
        $d = array();
        foreach($this->cached_data as $key=>$data){
            $d[$key] = [];
        }
        $this->cached_data = $d;
    }

    /**
     * Class constructor
     */
    public function __construct() {
        if (Wc_Ls_Dependencies::is_woocommerce_active()) {
            $this->define_constants();//define the constants
            $this->includes();//include relevant files
            $this->init_hooks();
        } else {
            add_action('admin_notices', array($this, 'admin_notices'), 15);
        }
    }

    /**
     * Constants define
     */
    private function define_constants() {
        $this->define('WC_LS_ABSPATH', dirname(WC_LS_PLUGIN_FILE) . '/');
        $this->define('WC_LS_PLUGIN_FILE', plugin_basename(WC_LS_PLUGIN_FILE));
        $this->define('WC_LS_ASSETS_PATH', plugins_url('assets/',__FILE__));
        if(trim(strtolower(WC_LS_ENVIRONMENT)) == 'production')
            $this->define('WC_LS_MIN_SUFFIX', '.min');
        else
            $this->define('WC_LS_MIN_SUFFIX', '');

        $this->define('WC_LS_PRODUCT_META_KEY', '_wc_loystar_product_id' );
        $this->define('WC_LS_PRODUCT_IMPORT_META_KEY', '_wc_loystar_product_imported' );
        $this->define('WC_LS_PRODUCT_VAR_META_KEY', '_wc_loystar_product_variant_id' );
        $this->define('WC_LS_PRODUCT_VAR_IMPORT_META_KEY', '_wc_loystar_product_variant_imported' );
        $this->define('WC_LS_PRODUCT_VAR_BRANCH_META_KEY', '_wc_loystar_product_variant_branch_id' );
        $this->define('WC_LS_COUPON_META_KEY', '_wc_loystar_loyalty_id' );
        $this->define('WC_LS_ORDER_RESEND_TRANS_KEY', 'wc_loystar_resend_transaction' );
        $this->define('WC_LS_ORDER_TRANS_SEND_KEY', '_wc_loystar_transaction_already_sent' );
        $this->define('WC_LS_MEDIA_IMPORT_META_KEY', '_wc_loystar_image_imported_from' );
        $this->define('WC_LS_MEDIA_URL_META_KEY', '_wc_loystar_image_url' );
        $this->define('WC_LS_CAT_META_KEY', '_wc_loystar_cat_id' );
        $this->define('WC_LS_CAT_IMPORT_META_KEY', '_wc_loystar_cat_imported' );
        //shortcode code
        $this->define('WC_LS_LOYALTY_WIDGET_SHORTCODE', 'loystar_wc_loyalty_widget' );
        //product time synced key
        $this->define('WC_LS_PRODUCT_SYNC_TIME', '_wc_loystar_product_synced' );
        
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Check request
     * @param string $type
     * @return bool
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined('DOING_AJAX');
            case 'cron' :
                return defined('DOING_CRON');
            case 'frontend' :
                return (!is_admin() || defined('DOING_AJAX') ) && !defined('DOING_CRON');
        }
    }

    /**
     * load plugin files
     */
    public function includes() {
        include_once( WC_LS_ABSPATH . 'includes/class-loystar-install.php' );
        if ($this->is_request('admin')) {
            add_action('init',function(){
            include_once( WC_LS_ABSPATH . 'admin/class-loyalty-programs.php' );
	        include_once( WC_LS_ABSPATH . 'admin/class-add-loyalty-program.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-customers.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-settings.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-woocommerce-orders.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-notices.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-product-page.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-coupon-page.php' );
            include_once( WC_LS_ABSPATH . 'admin/class-sync.php' );
            });
	   }
        if ($this->is_request('frontend')) {
            include_once( WC_LS_ABSPATH . 'public/class-woocommerce-checkout.php' );
            include_once( WC_LS_ABSPATH . 'public/class-woocommerce-product.php' );
            include_once( WC_LS_ABSPATH . 'public/class-loyalty-widget.php' );
        }

        //if ($this->is_request('ajax')) {}
    }

    /**
     * Plugin url
     * @return string path
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', WC_LS_PLUGIN_FILE));
    }

    /**
     * Plugin init
     */
    private function init_hooks() {
        register_activation_hook(WC_LS_PLUGIN_FILE, array('Wc_Ls_Install', 'install'));
        register_deactivation_hook(WC_LS_PLUGIN_FILE,array('Wc_Ls_Install','delete_loyalty'));
    }

    /**
     * Display admin notice
     */
    public function admin_notices() {
        echo '<div class="error"><p>';
        _e('<strong>Loystar for Woocommerce</strong> plugin requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> plugin to be active!', WC_LS_TEXT_DOMAIN);
        echo '</p></div>';
    }

    /**
     * Logo url
     * 
     * @return string
     */
    public function logo_url(){
        return $this->plugin_url().'/assets/images/loystar_logo.png';
    }

    /**
     * Checks if the user enabled the loystar program
     * 
     * @return bool
     */
    public function is_enabled(){
        global $wc_ls_option_meta;
        return get_option($wc_ls_option_meta['enabled'],false);
    }
    	
   /**
	* Check if the merchant is logged in
	*
	* This way we know if the merchant is already logged in and validated
	* @return bool
    */
   public function merchant_logged_in(){
    global $wc_ls_option_meta;
    $option_count = count($wc_ls_option_meta) - count($this->ignored_keys);//ignoring "enabled" key
    $valid_count = 0;
   //loop through and make sure everything is valid
    foreach($wc_ls_option_meta as $key=>$value){
        if($this->ignore_key($key))//ignore this :)
            continue;
        if(!empty( trim( get_option($value,'') ) ) ){
            $valid_count++;//increase valid count
        }
        //for the current version, we need to check if the merchant's time hasn't expired
       // if(Wc_Loystar::login_expired()) return false;
    }
    if($option_count == $valid_count)//no issues
         return true;         
    return false;
   }
    	
   /**
	* Log the merchant out
	*
	* This can be called in case any authentication issue is spotted :), smart
	* @return void
    */
    public function logout_merchant($login_redirect = true){
        global $wc_ls_option_meta;
        $option_count = count($wc_ls_option_meta) - count($this->ignored_keys);//ignoring keys
        $valid_count = 0;
        //loop through and make sure everything is valid
        foreach($wc_ls_option_meta as $key=>$value){
            if($this->ignore_key($key) && !$this->ignore_relevant_key($key))//ignore this :)
                continue;
            if(update_option($value,'')){
                $valid_count++;//increase valid count
            }
        }
        if($login_redirect){
            wp_safe_redirect(wc_loystar()->forced_login_args());
            exit;      
        }
        //return false;
    }

    /**
     * Handles the checking of logged in merchant
     */
    public function merchant_auth(){
        if(!wc_loystar()->merchant_logged_in()){
			//redirect safely to login page
			wp_safe_redirect(wc_loystar()->forced_login_args());
			exit();
		}
    }

    /**
     * Checks keys to ignore in $wc_ls_option_meta
     * 
     * @param $key from $wc_ls_option_meta
     * @return bool
     */
    private function ignore_key($key){
        $key = trim($key);
        if(in_array($key,$this->ignored_keys))
            return true;
        return false;
    }

    /**
     * Checks Keys that can be ignored from $wc_ls_option_meta when checking for login auth  in $wc_ls_option_meta
     * but when logging out, shouldn't be ignored, forgive me, i didnt have a better name :)
     * 
     * @param mixed $key from $wc_ls_option_meta
     * @return bool
     */
    private function ignore_relevant_key($key){
        $key = trim($key);
        if(in_array($key,$this->ignored_relevant_keys))
            return true;
        return false;
    }
    /**
     * gets the selected loyalty program
     * 
     * @return int
     */
    public function loyalty_program(){
        global $wc_ls_option_meta;
       return (int)get_option($wc_ls_option_meta['loyalty_program'],false);
    }
    /**
     * easily display notice messages
     * 
     * @return string
     */
    public function notice_me($msg,$type = 'warning',$dismissible = true){
        $txt = '';
        $msg = trim($msg);
        if(!empty($msg) && current_user_can($this->user_capability())){
            $txt = '<div class="notice notice-'.$type.' loystar-notice '.($dismissible ? 'is-dismissible' : '').'">
            <p>'.$msg.'</p>
            </div>';
        }
        return $txt;
    }
   /**
    * the url args the user is shown when not logged in
    * 
    * @return add_query_args
    */
   public function forced_login_args($msg = ''){
       $msg = (empty(trim($msg)) ? 'You must be logged in to your merchant account.' : trim($msg));
       $msg = urlencode($msg);
       $admin_args = add_query_arg(array('page'=>$_GET['page']),'admin.php');
       return add_query_arg(['forced_login_msg'=>$msg,'red_back'=>admin_url($admin_args)],admin_url('admin.php?page='.$this->parent_slug().'-settings'));
   }

   /**
    * returns the parent url used by this plugin pages
    */
    public function parent_url(){
        return admin_url('page'.$this->parent_slug());
    }

    /**
     * returns the parent used by this plugin
     * @param bool $full_slug (optional) if it should return full, default is false
     */
    public function parent_slug($full_slug = false){
        if($full_slug)
            return 'admin.php?page=loystar-woocommerce';
        return 'loystar-woocommerce';
    }

    /**
     * returns the default user capability of this plugin :)
     */
    public function user_capability(){
        return 'manage_woocommerce';
    }

    /**
     * properly display program type to front end
     * 
     * @return string
     */
    public function program_type_display($program){
        $result = '';
        switch(strtolower(trim($program))){
            case 'simplepoints':
                $result = 'Simple Points';
            break;
            case 'stampsprogram':
                $result = 'Stamps Program';
            break;
            default:
            $result = '';
        }
        return $result;
    }
    
   /**
    * Checks if the expiry date isnt exceeded
    * 
    * @param bool $unix, to know if its a unix time we handling 
	* @return bool
	*/
	public function login_expired($unix = true){
        global $wc_ls_option_meta;
        $f = 'Y-m-d\TH:i:s\Z';//loystar time format i saw
        if($unix){//change to normal
            $f = 'Y-m-d H:i:s';
        }
		$now = new DateTime('now');
		$format = $now->format($f);//format used in the loystar api
		$current_time = strtotime($format);
		$expiring = trim(get_option($wc_ls_option_meta['expiring']));
		if(empty($expiring))
            return false;
        $login_time = $expiring;
        if(! ($unix || is_int($login_time)) ){//convert to that format   
		    $login_time = new DateTime($expiring);
		    $login_time = strtotime($login_time->format($f));
         }
		if($login_time < $current_time)//expired
			return false;
		return true;
    }
    /**
     * Gets the merchant subscription date
     */
    public function get_merchant_sub_expiry_date(){
        global $wc_ls_option_meta;
        return get_option($wc_ls_option_meta['sub_expires'],'');
    }
    /**
     * Checks if a merchant subscription is expired
     * 
     * @return bool
     */
    public function is_merchant_subscription_expired(){
        global $wc_ls_option_meta;
        $expiry_date = $this->get_merchant_sub_expiry_date();
        if(empty($expiry_date)){
            include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
            $api = new Wc_Ls_Api();
            $result = $api->get_merchant_subscription();
            update_option($wc_ls_option_meta['sub_expires'],$result['expires_on']);
            $expiry_date = $result['expires_on'];      
        }
        $expiry_date = strtotime($expiry_date);
        $today = time();
        if($today <= $expiry_date)//still valid
            return false;
        else
            return true;
    }
    /**
     * just to show default message note for users whose subscription have expired
     * 
     * @return string
     */
    public function get_default_subscription_note(){
        return 'Your loystar <strong>subscription</strong> has expired, your account is now limited. <strong>sms messages</strong> and <strong>birthday</strong> messages are also disabled.';
    }
    /**
     * Filters text for proper wp post_status values
     * @param string $value
     * @return string
     */
    public function filter_wp_post_status($value){
        if(stripos($value,'ublish') !== false)
            $value = 'publish';
        else if(stripos($value,'raft') !== false)
            $value = 'draft';
        else if(stripos($value,'rivate') !== false)
            $value = 'private';
        else if(stripos($value,'rash') !== false)
            $value = 'trash';
        //else
        //    $value = 'publish';//default
        return $value;
    }
    /**
     * Gets the loystar product id the woo product is synced with
     * 
     * @param int $product_id
     * @return string
     */
    public function get_equiv_loystar_product($product_id){
        return get_post_meta($product_id,WC_LS_PRODUCT_META_KEY,true);
    }
    /**
     * Gets the loystar product image the woo product image is synced with
     * 
     * @param int $img_id
     * @return string
     */
    public function get_equiv_loystar_product_image($img_id){
        return get_post_meta($img_id,WC_LS_MEDIA_URL_META_KEY,true);
    }
    /**
     * Gets woo customer meta 
     * 
     * @param int $woo_customer_id
     * @param string $meta_key | a valid meta key
     * @param bool $single(optional) | default is true
     * @return mixed
     */
    public function get_woo_customer_meta($woo_customer_id,$meta_key,$single = true){
        $val = get_user_meta($woo_customer_id,$meta_key,$single);
        return $val;
    }
    /**
     * Updates/adds woo customer meta 
     * 
     * @param int $woo_customer_id
     * @param string $meta_key
     * @param string $value
     * @return bool
     */
    public function update_woo_customer_meta($woo_customer_id,$meta_key,$value){
        return update_user_meta($woo_customer_id,$meta_key,$value);
    }
    /**
     * Gets woo product meta 
     * 
     * @param int $woo_product_id
     * @param string $meta_key | a valid meta key
     * @param bool $single(optional) | default is true
     * @return mixed
     */
    public function get_woo_product_meta($woo_product_id,$meta_key,$single = true){
        $val = get_post_meta($woo_product_id,$meta_key,$single);
        return $val;
    }
    /**
     * Updates/adds woo product meta 
     * 
     * @param int $woo_product_id
     * @param string $meta_key 
     * @param string $value
     * @return bool
     */
    public function update_woo_product_meta($woo_product_id,$meta_key,$value){
        return update_post_meta($woo_product_id,$meta_key,$value);
    }
    /**
     * Gets woo order meta 
     * 
     * @param int $woo_order_id
     * @param string $meta_key | a valid meta key
     * @param bool $single(optional) | default is true
     * @return mixed
     */
    public function get_woo_order_meta($woo_order_id,$meta_key,$single = true){
        $val = get_post_meta($woo_order_id,$meta_key,$single);
        return $val;
    }
    /**
     * Updates/adds woo order meta 
     * 
     * @param int $woo_order_id
     * @param string $meta_key 
     * @param string $value
     * @return bool
     */
    public function update_woo_order_meta($woo_order_id,$meta_key,$value){
        return update_post_meta($woo_order_id,$meta_key,$value);
    }
    /**
     * Gets the branch of the store
     * 
     * @return string
     */
    public function get_site_branch(){
        global $wc_ls_option_meta;
        return get_option($wc_ls_option_meta['branch'],'');
    }
    /**
     * Sets the branch of the store
     * 
     * @return string
     */
    public function set_site_branch(){
        global $wc_ls_option_meta;
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $api = new Wc_Ls_Api();
        $branch = $api->get_business_branches('ecommerce<|>-commerce');
        update_option($wc_ls_option_meta['branch'],$branch['id'],false);
    }
    /**
     * Gets products in a branch
     * 
     * @param int $branch_id
     * @param int $product_id(optional)
     * @param bool $single_data_index_arr_form(optional), if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted(optional)
     * @return array|bool
     */
    public function get_products_in_branch($branch_id,$product_id = 0,$single_data_index_arr_form = false,$include_deleted = false){
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $api = new Wc_Ls_Api();
        $result = $api->get_business_branches($branch_id);
        if(!$result)
            return false;
        $products = $result['branch_products'];
        $product_data = array();
        foreach($products as $p_data){
            if(!$include_deleted){
                if($p_data['product']['deleted'] == true)//ignore
                    continue;
            }
            if($product_id > 0){
                if($product_id == $p_data['product']['id']){
                    if($single_data_index_arr_form)
                        $product_data[] = $p_data;
                    else
                        $product_data = $p_data;
                    break;
                }
            }
            else{
                $product_data[] = $p_data;
            }
        }
        return $product_data;
    }
    /**
     * Checks if a loystar_product id is in a branch
     * 
     * @param int $branch_id
     * @param int $product_id
     * @return bool
     */
    public function is_product_in_branch($branch_id,$product_id){
        $product = $this->get_products_in_branch($branch_id,$product_id,true);
        $c_product = !$product ? 0 : count($product);
        if($c_product == 1)
            return true;
        return false;
    }
    /**
     * Gets all woocommerce products linked to a loystar product
     * 
     * @param int $loystar_product_id(optional)
     * @param bool $quick_inspection(optional) | if set to true, $status is irrelevant, this is good for checking if the loystar_prod_id is in the db
     * @param bool $actual(optional) | if set to true, only products linked to a loystar product that actually exists in the current logged in merchant :)
     * @param bool $only_import(optional) | if set to true, only products imported from loystar would be fetched
     * @param bool $variable_product(optional) | if set to true, only variable products imported from loystar would be fetched,if $only_import is true, this will be irrelevant
     * @param string $status(optional) | the default wp post_status values, default is publish
     * @return array|bool | product ids, if $actual is true, and there was an error connecting to the api,returns false
     */
    public function get_linked_woo_products($loystar_product_id = 0,$quick_inspection = false,$actual = false,$only_import = false,$variable_product = false,$status = 'publish'){
        global $wpdb;
        $loystar_product_id = (int) $loystar_product_id;
        $products = array();
        //get from post meta :) awon shortcut
        $extra_statement = '';
        $meta_key = WC_LS_PRODUCT_META_KEY;
        
        if($variable_product)//variable we checking
            $meta_key = WC_LS_PRODUCT_VAR_META_KEY;
        if($only_import)
            $meta_key = WC_LS_PRODUCT_IMPORT_META_KEY;
        $q_args = array($meta_key);
        if($loystar_product_id > 0){
            $extra_statement = ' AND meta_value = %d';
            $q_args[] = $loystar_product_id;
        }
        $prepare = $wpdb->prepare( "SELECT post_id,meta_value FROM {$wpdb->base_prefix}postmeta WHERE meta_key = %s ".$extra_statement, $q_args);
        $p_ids = $wpdb->get_results($prepare,ARRAY_A);
        if(count($p_ids) == 0)//if result is empty
            return array();
        //check if its actual
        $loystar_products = array();//initialise
        if($actual){//get live products
            $loystar_products = $this->get_cachable_data('products',['id'=>$loystar_product_id,'single_data_index_form'=>true]);
            $c_loystar_products = !$loystar_products ? 0 : count($loystar_products) ;
            
            if(is_bool($loystar_products))//api error
                return false;
            if($c_loystar_products < 1)//empty
                return array();
        }
            $q_products = array();
            foreach($p_ids as $id){
                if($actual){
                    //check if the product is in current merchant account
                    foreach($loystar_products as $p){
                        if($p['id'] == $id['meta_value'])
                            $q_products[] = $id['post_id'];
                    }
                }
                else{
                    $q_products[] = $id['post_id'];
                }
            }
            $products = $q_products;

        if(!$quick_inspection){//some filtering
            $status = wc_loystar()->filter_wp_post_status($status);
            $s_products = array();
            for($i=0; $i<count($p_ids); $i++){
                $num = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) from ".$wpdb->posts." WHERE post_type='product' post_status = %s, and id = %d",$status,$p_ids[$i]['id']) );
                if($num == 1){
                    $s_products[$i] = $p_ids[$i]['post_id'];
                }
            }
            $products = $s_products;
        }
        return $products;
    }

    /**
     * Gets all woocommerce products linked to a loystar product that actually exists in the current logged in merchant :)
     *
     * @param int $loystar_product_id(optional)
     * @param bool $quick_inspection(optional) | if set to true, $status is irrelevant, this is good for checking if the loystar_prod_id is in the db
     * @param bool $only_import(optional) | if set to true, only products imported from loystar would be fetched
     * @param bool $variable_product(optional) | if set to true, only variable products imported from loystar would be fetched,if $only_import is true, this will be irrelevant
     * @param string $status(optional) | the default wp post_status values, default is publish
     * @return array|bool | product ids, if $actual is true, and there was an error connecting to the api,returns false
     */
    public function get_actual_linked_woo_products($loystar_product_id = 0,$quick_inspection = false,$only_import = false,$variable_product = false,$status = 'publish'){
        return $this->get_linked_woo_products($loystar_product_id,$quick_inspection,true,$only_import,$variable_product,$status);
    }

    /**
     * Checks if the woo product was imported
     * 
     * @param int $woo_product_id
     * @return bool
     */
    public function is_woo_product_imported($woo_product_id){
        if((int)get_post_meta($woo_product_id,WC_LS_PRODUCT_IMPORT_META_KEY,true) > 0 )
            return true;
        return false;
    }

    /**
     * Gets all woocommerce product categories linked to a loystar product
     * 
     * @param int $loystar_cat_id(optional)
     * @param bool $quick_inspection(optional) | if set to true, $status is irrelevant, this is good for checking if the loystar_cat_id is in the db
     * @param bool $actual(optional) | if set to true, only product categories linked to a loystar category that actually exists in the current logged in merchant :)
     * @param bool $only_import(optional) | if set to true, only categories imported from loystar would be fetched
     * @return array|bool | product category ids, or false
     */
    public function get_linked_woo_categories($loystar_cat_id = 0,$quick_inspection = false,$actual = false,$only_import = false){
        global $wpdb;
        $loystar_cat_id = (int) $loystar_cat_id;
        $cats = array();
        //get from term meta :) awon shortcut
        $extra_statement = '';
        $meta_key = WC_LS_CAT_META_KEY;
        if($only_import)
            $meta_key = WC_LS_CAT_IMPORT_META_KEY;
        $q_args = array($meta_key);
        if($loystar_cat_id > 0){
            $extra_statement = ' AND meta_value = %d';
            $q_args[] = $loystar_cat_id;
        }
        $prepare = $wpdb->prepare( "SELECT term_id,meta_value FROM {$wpdb->base_prefix}termmeta WHERE meta_key = %s ".$extra_statement, $q_args);
        $c_ids = $wpdb->get_results($prepare,ARRAY_A);
        if(count($c_ids) == 0)//if result is empty
            return array();
        //check if its actual
        $loystar_cats = array();//initialise
        if($actual){//get live products
            $loystar_cats = $this->get_cachable_data('categories',['id'=>$loystar_cat_id,'single_data_index_form'=>true]);
            $c_loystar_cats = !$loystar_cats ? 0 : count($loystar_cats);
            if(is_bool($loystar_cats))//api error
                return false;
            if($c_loystar_cats < 1)//empty
                return array();
        }
            $q_cats = array();
            foreach($c_ids as $id){
                if($actual){
                    //check if the product is in current merchant account
                    foreach($loystar_cats as $c){
                        if($c['id'] == $id['meta_value'])
                            $q_cats[] = $id['term_id'];
                    }
                }
                else{
                    $q_cats[] = $id['term_id'];
                }
            }
            $cats = $q_cats;
        return $cats;
    }

    /**
     * Gets all woocommerce product categories linked to a loystar product category that actually exists in the current logged in merchant :)
     *
     * @param int $loystar_cat_id(optional)
     * @param bool $quick_inspection(optional) | if set to true, $status is irrelevant, this is good for checking if the loystar_cat_id is in the db
     * @param bool $only_import(optional) | if set to true, only categories imported from loystar would be fetched
     * @return array|bool | product category ids or false
     */
    public function get_actual_linked_woo_categories($loystar_cat_id = 0,$quick_inspection = false,$only_import = false){
        return $this->get_linked_woo_categories($loystar_cat_id,$quick_inspection,true,$only_import);
    }

    /**
     * Deletes a woo product linked to loystar
     *
     * @param int $loystar_product_id(optional)
     * @param bool $quick_inspection(optional) | if set to true, $status is irrelevant, this is good for checking if the loystar_prod_id is in the db
     * @param bool $actual(optional) | if set to true, deletes only products linked to a loystar product that actually exists in the current logged in merchant :)
     * @param bool $only_import(optional) | if set to true, only products imported from loystar would be fetched
     * @param bool $force true to permanently delete product, false to move to trash.
     * @param string $status(optional) | the default wp post_status values, default is publish
     * @return array | product ids
     */
    public function delete_linked_woo_products($loystar_product_id = 0,$quick_inspection = false,$actual = true,$only_import = false,$force = true,$status = 'publish'){
        $products = array();
        if($actual){
            $products = $this->get_actual_linked_woo_products($loystar_product_id,$quick_inspection,$only_import,$status);
        }
        else{
            $products = $this->get_linked_woo_products($loystar_product_id,$quick_inspection,false,$only_import,$status);
        }
        $d_products = array();
        if($products){
            foreach($products as $p){
                if(!is_wp_error($this->delete_woo_product($p,$force)) ){
                    $d_products[] = $p;
                }
            }
        }
        return $d_products;
    }
    /**
     * Gets coupons linked to loyalty program(s)
     * 
     * Note: if applied_coupons is set to true, the $status param is not relevant
     * 
     * @param int $loyalty_id(optional) default is 0, if 0, lists all coupons linked to loyalty programs
     * @param bool $applied_coupons(optional) if you want to get only coupons in the user cart, default is true
     * @param string $status(optional) the normal post status values
     * @return array
     */
    public function get_coupons_linked_to_loyalty($loyalty_id = 0,$applied_coupons = true, $status = 'publish'){
        global $wpdb;
        $loyalty_id = (int) $loyalty_id;
        $coupons = array();
        //get from post meta :) awon shortcut
        $extra_statement = '';
        $q_args = array(WC_LS_COUPON_META_KEY);
        if($loyalty_id > 0){
            $extra_statement = ' AND meta_value = %d';
            $q_args[] = $loyalty_id;
        }
        $prepare = $wpdb->prepare( "SELECT post_id FROM {$wpdb->base_prefix}postmeta WHERE  meta_key = %s ".$extra_statement, $q_args);
        $coupon_ids = $wpdb->get_results($prepare,ARRAY_A);
        for($i=0; $i < count($coupon_ids); $i++){
            $c_id = $coupon_ids[$i]['post_id'];
            $coupons[$i]['id'] = $c_id;
            $c_data = new WC_COUPON($c_id);//make sure woocommerce stuff are already initialised, will later force this to be handled properly :), but its no biggie, it'll always be initialised
            $coupons[$i]['code'] = $c_data->get_code();
        }
        if($applied_coupons){
            $a_coupons = array();
            for($i = 0; $i<count($coupons); $i++){
                if(WC()->cart->has_discount($coupons[$i]['code'])){
                    $a_coupons[$i]['id'] = $coupons[$i]['id'];
                    $a_coupons[$i]['code'] = $coupons[$i]['code'];
                }
            }
            $coupons = $a_coupons;
        }
        else{//now check for the kind of status, since applied coupons is set to false
            //some filtering
            $status = wc_loystar()->filter_wp_post_status($status);
            $s_coupons = array();
            for($i=0; $i<count($coupons); $i++){
                $num = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) from ".$wpdb->posts." WHERE post_status = %s, and id = %d",$status,$coupons[$i]['id']) );
                if($num == 1){
                    $s_coupons[$i]['id'] = $coupons[$i]['id'];
                    $s_coupons[$i]['code'] = $coupons[$i]['code'];
                }
            }
            $coupons = $s_coupons;
        }
        return $coupons;
    }
    /**
     * Gets data that is cachable like products, categories from loystar api
     * 
     * Has to be endpoint methods that are similar, for now, its only get_merchant_products, get_merchant_categories and a few more
     * 
     * @param string $key | a valid key from cached_data property
     * @param array $args | the arguement for the endpoints, must be ['id'=>int,'include_deleted'=>bool,'single_data_index_form'=>bool] in the format
     * @param bool $renew | if set to true, it'll fetch from the endpoint
     * @return array|bool | false if there was an api error
     */
    public function get_cachable_data($key,$args,$renew = false){
        $c_data = array();//$this->get_cached_data()[$key];
        if(empty($c_data) || $renew){
            include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
            $api = new Wc_Ls_Api();
            $content = false;
            $single_index_form = isset($args['single_data_index_form']) ? $args['single_data_index_form'] : true;
            $args['id'] = isset($args['id']) ? $args['id'] : 0;
            $inc_del = isset($args['include_deleted']) ? $args['include_deleted'] : false;
            switch($key){
                case 'products':
                    $content = $api->get_merchant_products($args['id'],$single_index_form,$inc_del);
                break;
                case 'categories':
                    $content = $api->get_merchant_product_categories($args['id'],$single_index_form,$inc_del);
                break;
                    case 'loyalty':
                    $content = $api->get_merchant_loyalty($args['id'],$single_index_form,$inc_del);
                break;
                case 'customers'://id can be email
                    $content = $api->get_merchant_customers($args['id'],$single_index_form,$inc_del);
                break;
                //add more to come
            }
           // $array = $this->cached_data;
            //$array[$key] = $content;
            //$this->set_cached_data($array);
            //$c_data = $this->get_cached_data()[$key]; has little misbehaviour, so ignore
            $c_data = $content;
        }
        return $c_data;
    }

    ####################################################################
    /**
     * Uploads media
     * 
     * @param string $image_url
     * @return int | attachment id
     */
    public function upload_media($image_url){
        require_once(ABSPATH.'wp-admin/includes/image.php');
        require_once(ABSPATH.'wp-admin/includes/file.php');
        require_once(ABSPATH.'wp-admin/includes/media.php');
        $media = media_sideload_image($image_url,0);
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => null,
            'post_parent' => 0,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ));
        $id = $attachments[0]->ID;
        update_post_meta($id,WC_LS_MEDIA_IMPORT_META_KEY,$image_url);
        update_post_meta($id,WC_LS_MEDIA_URL_META_KEY,$image_url);
        return $id;
    }
    /** 
     * sorts the attribute creation from loystar variant
     * @param array $variants, the variant data
     * @return array
     */
    public function sort_woo_attr_from_loystar_variants($variants){
        //first we do some technical ish, first,find out how many different type of variants the product has
        //for this we use, the following keys: "value","type"
        $options_val = array();
        $type_val = array();
        $attr = array();
        foreach($variants as $v){
            //check if value is already there
            if(!in_array($v['type'],$type_val)){
                $type_val[$v['type']] = $v['type'];
                $options_val[$v['type']] = array();// to avoid warning ;)
            }
        }
        //loop again for option
        foreach($variants as $v){
            //for option val, loop through type
            $t = 0;
            foreach($type_val as $type => $value){
             if($v['type'] == $type){//its the type
                 //now check if value is already there
                 $op = $options_val[$type];
                if(!in_array($v['value'],$op)  )//its not there, add
                    $options_val[$type][] = $v['value'];
             }
             $t++;
            }
        }
        $t_v =0;
        foreach($type_val as $type){
            $attr[] = array(
                "name"=>$type,
                "options"=>$options_val[$type],
                "position"=>($t_v+1),
                "visible"=>1,
                "variation"=>1
            );
            $t_v++;
        }
        return $attr;
    }

   /**
    * sorts the variation for adding
    *
    * @param $variant, the woocommerce variant
    * @return array
    */
    public function sort_woo_variations_from_loystar_variants($variants){
       $variations = array();
       foreach($variants as $v){
           $regular_price = (isset($v['original_price']) && !empty($v['original_price']) ? $v['original_price'] : $v['price'] );
           $price = $v['price'];
           $sale_price = '';//($regular_price > $price ? $price : '');//incase loystar decides to start adding sale price to their variants
           $variations[] = array(
               'regular_price'=>$regular_price,
               'price'=>$price,
               'sale_price'=>$sale_price,
               'sku'=>'',
               'manage_stock'=>($v['quantity'] > 0 ? '1' : '0'),
               'stock_quantity'=>$v['quantity'],
               'attributes'=>array(array("name"=>$v['type'],"option"=>$v['value'])),//based on loystar variant attr pattern
               '_business_branch_id'=>$v['branch_product_id'],
               '_loystar_variant_id'=>$v['id']
           );
       }
       return $variations;
        /*
        $variations = array(
            array("regular_price"=>10.11,"price"=>10.11,"sku"=>"ABC1","attributes"=>array(array("name"=>"Size","option"=>"L"),array("name"=>"Color","option"=>"Red")),"manage_stock"=>1,"stock_quantity"=>10),
            array("regular_price"=>10.11,"price"=>10.11,"sku"=>"ABC2","attributes"=>array(array("name"=>"Size","option"=>"XL"),array("name"=>"Color","option"=>"Red")),"manage_stock"=>1,"stock_quantity"=>10)
        );*/
    }

    /**
     * Creates a woocommerce product from a loystar product
     * 
     * @param int $loystar_product_id
     * @param array $product_list(optional) | the loystar product list, this saves resources incase this function is called in the loop, the
     * endpoint shouldn't be called everything
     * @param bool $link_product(optional) | if set to true, the created woocommerce product will be linked to the loystar product
     * @param bool $update(optional) | if set to true, it updates an existing product
     * @param bool $only_import(optional) | if set to true, it updates only products that were imported from loystar direct.relevant when $update is true
     * //(not in use)param bool $product_args(optional) | the parts of the loystar products you want to edit, works when update is true, leave empty if you want all to be updated
     * @param bool $allow_duplicate(optional) | if set to true, it'll allow a product created already to be created again
     * @return int
     */
    public function add_woo_product_from_loystar($loystar_product_id,$product_list = array(),$link_product = true,$update = false,$only_import = false,$allow_duplicate = false){
        wc_loystar()->set_exception(null);//empty
        $l_product = array();
        if(empty($product_list)){
            $l_product = $this->get_cachable_data('products',['id'=>$loystar_product_id,'single_data_index_form'=>false]);
        }
        else{//loop through product list and find the id
            foreach($product_list as $p_data){
                if($p_data['id'] == $loystar_product_id){
                    $l_product = $p_data;
                    break;
                }
            }
        }
        
        if(!$l_product)
            return 0;
        $already_linked_id = $this->get_actual_linked_woo_products($loystar_product_id,true);
        $c_already_linked_id = count($already_linked_id);
        if(!$allow_duplicate && !$update){//check if duplicate is not allowed and its a new product to be added
            if(is_bool($already_linked_id))//api error
                return 0;
            if($c_already_linked_id == 1)//already exists, prevent
                return 0;
        }
        //count variation
        $count_variants = count($l_product['variants']);
        //do some filtering
        $l_product = wc_loystar()->replace_value($l_product);
        $p = 0;
        if($update){
            $ps = $this->get_actual_linked_woo_products($loystar_product_id,true,$only_import);
            if(is_bool($ps))//api error
                return 0;
            $p = (int)$ps[0];
        }
        $wc_p = new WC_Product($p);//wc_get_product();
        if($count_variants > 0)
            $wc_p = new WC_Product_Variable($p);//variable product
        //set stuff
        if(!$update){//only when newly added, these are default stuff
            $wc_p->set_stock_status('instock'); // in stock or out of stock value
            $wc_p->set_backorders('no');
            $wc_p->set_reviews_allowed(true);
            $wc_p->set_sold_individually(false);
            $wc_p->set_status("publish");  // can be publish,draft or any wordpress post status
            $wc_p->set_catalog_visibility('visible'); // add the product visibility status
        }
        if(!$update || ($update && $only_import) ){//quite confusing ba?, if its a new product or its an update that was imported
        $wc_p->set_name($l_product['name']);
        $wc_p->set_description($l_product['description']);
        try{//to prevent error
            $wc_p->set_sku($l_product['sku']); //can be blank in case you don't have sku, but You can't add duplicate sku's
        }
        catch(Exception $e){
            wc_loystar()->set_exception($e);
        }
        $wc_p->set_price($l_product['price']); // set product price
        $wc_p->set_regular_price($l_product['original_price']); // set product regular price
        $wc_p->set_manage_stock($l_product['track_inventory']); // true or false
        $wc_p->set_stock_quantity($l_product['quantity']);
        if(!$update)//if its an update, for now, ignore
            $wc_p->set_category_ids(array($this->add_woo_cat_from_loystar($l_product['merchant_product_category_id']) ) ); // array of category ids, 
        //for the image part
        if(!$update){
            $product_images_ids = array(); // define an array to store the media ids.
            $images = array($l_product['picture']); // images url array of product
            foreach($images as $image){
                if(empty(trim($image)))
                    continue;
                $media_id = $this->upload_media($image); // calling the uploadMedia function and passing image url to get the uploaded media id
                if($media_id){ 
                    $product_images_ids[] = $media_id; // storing media ids in a array.
                }
            }
            if($product_images_ids){
                $wc_p->set_image_id($product_images_ids[0]); // set the first image as primary image of the product
                //in case we have more than 1 image, then add them to product gallery. 
                if(count($product_images_ids) > 1){
                    $wc_p->set_gallery_image_ids($product_images_ids);
                }
            }
        }
        else{//update
            if( $this->get_equiv_loystar_product_image($wc_p->get_image_id()) != $l_product['picture']){//image has been changed,
                $media_id = $this->upload_media($l_product['picture']);
                if($media_id)
                    $wc_p->set_image_id($media_id);
            }
        }

        }
        else{//for now, products that weren't imported, we only update the quantity
            $wc_p->set_stock_quantity($l_product['quantity']);
        }
        ########## done with images
        $wc_p_id = $wc_p->save(); //save the product
        if(!$update){//a new product
            update_post_meta($wc_p_id,WC_LS_PRODUCT_IMPORT_META_KEY,$loystar_product_id);
            //link
            if($link_product){
                if( $c_already_linked_id < 1)//not linked yet
                    update_post_meta($wc_p_id,WC_LS_PRODUCT_META_KEY,$loystar_product_id);
            }
        }  
        //now check if the product has variants or if its an update that wasn't imported
        if($count_variants < 1 || ($update && !$only_import))
            return $wc_p_id;
        //so it has, since the code got here :)
        $l_v_products = $l_product['variants'];
        //add product attributes
        ###################################################    
        $attributes = wc_loystar()->sort_woo_attr_from_loystar_variants($l_v_products);
        if($attributes){
            $product_attributes=array();
            foreach($attributes as $attribute){
                $a_name = $attribute["name"];
                $attr = wc_sanitize_taxonomy_name(stripslashes($a_name)); // remove any unwanted chars and return the valid string for taxonomy name
                $attr = 'pa_'.$attr; // woocommerce prepend pa_ to each attribute name
                ###################################
                //create product attr
                if(wc_loystar()->create_woo_product_attribute($a_name) == 0){//attr already exists,do nothing
                }
                if($attribute["options"]){
                    foreach($attribute["options"] as $option){
                        //If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
                        if( ! taxonomy_exists( $attr ) ){
                        register_taxonomy(
                            $attr,
                            'product',
                            array(
                            'hierarchical' => false,
                            'label' => ucfirst( $attr ),
                            'query_var' => true,
                            'rewrite' => array( 'slug' => $attr), // The base slug
                            )
                        );
                        }

                        $relate = wp_set_object_terms($wc_p_id,$option,$attr,true); // save the possible option value for the attribute which will be used for variation later
                        if(is_wp_error($relate)){//an error while setting relationship,this is bad for business :)
                            //delete the product
                            wc_loystar()->delete_woo_product($wc_p_id,true);//delete permanently
                            return 0;
                        }
                    }
                }
                $product_attributes[sanitize_title($attr)] = array(
                    'name' => sanitize_title($attr),
                    'value' => $attribute["options"],
                    'position' => $attribute["position"],
                    'is_visible' => $attribute["visible"],
                    'is_variation' => $attribute["variation"],
                    'is_taxonomy' => '1'
                );
            }
            update_post_meta($wc_p_id,'_product_attributes',$product_attributes); // save the meta entry for product attributes
        }
        #########################################################

        //add product variations
        //############################################
        $variations = wc_loystar()->sort_woo_variations_from_loystar_variants($l_v_products);
        if($variations){
            //add variation

            $wc_p_variations = $this->create_woo_product_variations($wc_p_id,$variations,$link_product,$update,$only_import,$allow_duplicate);
            if(!$update){//only matters if it's a new product
                if(count($wc_p_variations) != count($variations)){//not all variations were successfully added
                    //an error,delete the whole product, just to be safe :)
                    wc_loystar()->delete_woo_product($wc_p_id,true);//delete permanently
                    return 0;
                }
            }
        }
        #########################################################################
        return $wc_p_id;
    }
   /**
    * Create a product variation for a defined variable product ID.
    *
    * @param int   $product_id | Post ID of the product parent variable product.
    * @param array $variations_data | The variations data to insert in the product.
    * @param bool $link_product(optional),true | if set to true, the created woocommerce product will be linked to the loystar product
    * @param bool $update(optional) if set to true, it updates an existing product
    * @param bool $only_import(optional) if set to true, it updates only products that were imported from loystar direct.relevant when $update is true
    * @param bool $allow_duplicate(optional) if set to true, it'll allow a product created already to be created again
    * @return array | variation ids
    */
   public function create_woo_product_variations( $product_id, $variations_data, $link_product = true,$update = false,$only_import = false,$allow_duplicate = false){
    try{
        $data = array();
        $already_linked_id = $this->get_actual_linked_woo_products(0,true,$only_import,true);
        $c_already_linked_id = count($already_linked_id);
        if(!$allow_duplicate && !$update){//check
            if($c_already_linked_id > 0 )//already exists, prevent//this is how i do it, assuming that variants from loystar cant have duplicate id, as i was told
                return array();
        }
       // Get the Variable product object (parent)
       $product = wc_get_product($product_id);

       $variation_post = array(
           'post_title'  => $product->get_title(),
           'post_name'   => 'product-'.$product_id.'-variation',
           'post_status' => 'publish',
           'post_parent' => $product_id,
           'post_type'   => 'product_variation',
           'guid'        => $product->get_permalink()
       );
    // Iterating through the variations data
    foreach($variations_data as $variation_data){ 
        $loystar_variant_id = $variation_data['_loystar_variant_id'];
       // Creating the product variation
       $variation_id = 0;
        if($update){//update
            $variation_ids = $this->get_actual_linked_woo_products($loystar_variant_id,true,$only_import,true);
            if(is_bool($variation_ids))//api error
                continue;//on to the next one!
            $variation_id = (int)$variations_ids[0];
        }
        else
            $variation_id = wp_insert_post( $variation_post );

       // Get an instance of the WC_Product_Variation object
       $variation = new WC_Product_Variation( $variation_id );
       
        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute ){
           $term_name = $attribute['option'];
           $taxonomy = strtolower('pa_'.$attribute['name']); // The attribute taxonomy

           // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
           if( ! taxonomy_exists( $taxonomy ) ){
               register_taxonomy(
                   $taxonomy,
                  'product_variation',
                   array(
                       'hierarchical' => false,
                       'label' => ucfirst( $taxonomy ),
                       'query_var' => true,
                       'rewrite' => array( 'slug' => '$taxonomy'), // The base slug
                   )
               );
           }

           // Check if the Term name exist and if not we create it.
           if( ! term_exists( $term_name, $taxonomy ) )
               wp_insert_term( $term_name, $taxonomy ); // Create the term
   
           $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug
   
           // Get the post Terms names from the parent variable product.
           $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );
   
           // Check if the post term exist and if not we set it in the parent variable product.
           if( ! in_array( $term_name, $post_term_names ) )
               wp_set_object_terms( $product_id, $term_name, $taxonomy, true );
   
           // Set/save the attribute data in the product variation
           update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );
        }

       ## Set/save all other data
       if(!$update || ($update && $only_import) ){//quite confusing ba?, if its a new product or its an update with only import
       // SKU
       if( !empty( $variation_data['sku'] ) )
           $variation->set_sku( $variation_data['sku'] );
       // Prices
       if( empty( $variation_data['sale_price'] ) ){
           $variation->set_price( $variation_data['regular_price'] );
       } else {//theres awuuf
           $variation->set_price( $variation_data['sale_price'] );
           $variation->set_sale_price( $variation_data['sale_price'] );
       }
       $variation->set_regular_price( $variation_data['regular_price'] );

       // Stock
       if( !empty($variation_data['stock_qty']) ){
           $variation->set_stock_quantity( $variation_data['stock_qty'] );
           $variation->set_manage_stock(true);
           $variation->set_stock_status('');
       } else {
           $variation->set_manage_stock(false);
       }

       }
       else{//its casual change, only inventory
        $variation->set_stock_quantity( $variation_data['stock_qty'] );
       }
        $id = $variation->save(); // Save the data
        if($id > 0)
            $data[] = $id;//saved
            //link
            if($link_product){//link variation to loystar variant
                update_post_meta($id,WC_LS_PRODUCT_VAR_META_KEY,$loystar_variant_id);
                update_post_meta($id,WC_LS_PRODUCT_VAR_BRANCH_META_KEY,$variation_data['_business_branch_id']);
                if(!$update)//show it was imported
                    update_post_meta($id,WC_LS_PRODUCT_VAR_IMPORT_META_KEY,$loystar_variant_id);
            }
       }//end of parent loop
       return $data;
     }
     catch(Exception $ex){
        return array();
     }
    }

    /**
     * Updates a woocommerce product from a loystar product
     * 
     * @param int $loystar_product_id
     * @param array $product_list(optional) the loystar product list, this saves resources incase this function is called in the loop, the
     * endpoint shouldn't be called everything
     * @param bool $only_import(optional) if set to true, it updates only products that were imported from loystar direct
     * //(not in use)param bool $product_args(optional) , the parts of the loystar products you want to edit, works when update is true, leave empty if you want all to be updated
     * @return int
     */
    public function update_woo_product_from_loystar($loystar_product_id,$product_list = array(),$only_import = false){
        return $this->add_woo_product_from_loystar($loystar_product_id,$product_list,true,true,$only_import,false);
    }

    /**
     * Updates woocommerce product variations
     * 
     * @param int   $product_id | Post ID of the product parent variable product.
    * @param array $variations_data | The variations data to insert in the product.
    * @param bool $link_product(optional),true | if set to true, the created woocommerce product will be linked to the loystar product
    * @param bool $only_import(optional) if set to true, it updates only products that were imported from loystar direct.relevant when $update is true
    * @return array | variation ids
    */
    public function update_woo_product_variations( $product_id, $variations_data ,$link_product = true,$only_import = false){
        return $this->create_woo_product_variations($product_id,$variations_data,$link_product,true,$only_import,false);
    }

    /**
     * Updates products with recent loystar
     * 
     * @param int $woo_product_id
     * @param array $product_list(optional) | an array of loystar product list, makes loading faster
     * @return int|bool | the id affected
     */
    public function update_woo_product($woo_product_id,$product_list = array()){
        $l_p_id = $this->get_equiv_loystar_product($woo_product_id);
        $id = false;
        if(!empty($l_p_id)){
            $only_import = false;
            if($this->is_woo_product_imported($woo_product_id))
                $only_import = true;
            $id = $this->update_woo_product_from_loystar($l_p_id,$product_list,$only_import);
        }
        if($id > 0)//valid, update time
            $this->update_woo_product_meta($id,WC_LS_PRODUCT_SYNC_TIME,time());
        return $id;
    }

    /**
     * Register taxonomy
     * 
     * helps in registering product taxonomy for loystar(not in use atm)
     */
    public function register_product_taxonomies(){
        $l_products = array();
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$api = new Wc_Ls_Api();
        $l_products = $this->get_cachable_data('products',['id'=>0,'single_data_index_form'=>true]);
        if(!$l_products)
            return;
        //stufffff
        $type_val = array();
        $attr = array();
            
        foreach($l_products as $l_product){
            //now check if the product has variants
            if(count($l_product['variants']) < 1)
                continue;
            //so it has, since the code got here :)
            $variants = $l_product['variants'];
            //first we do some technical ish, first,find out how many different type of variants the product has
            //for this we use, the following keys: "value","type"
            #################################################################################
            foreach($variants as $v){
                //check if value is already there
                if(!in_array($v['type'],$type_val)){
                    $type_val[$v['type']] = $v['type'];
                }
            }
        }
        $t_v =0;
        foreach($type_val as $type){
            $attr[] = array(
                 "name"=>$type,
            );
            $t_v++;
         }
         /*$attributes = array(
             array("name"=>"Size","options"=>array("S","L","XL","XXL"),"position"=>1,"visible"=>1,"variation"=>1),
             array("name"=>"Color","options"=>array("Red","Blue","Black","White"),"position"=>2,"visible"=>1,"variation"=>1)
         );*/

        //add product attributes
        ###################################################
        $attributes = $attr;
        if($attributes){
             $product_attributes=array();
            foreach($attributes as $attribute){
             $a_name = $attribute["name"];
             $a_name = wc_sanitize_taxonomy_name(stripslashes($a_name)); // remove any unwanted chars and return the valid string for taxonomy name
             $attr = 'pa_'.$a_name; // woocommerce prepend pa_ to each attribute name
             //register taxonomy first
             ########################################
             $permalinks = get_option( 'woocommerce_permalinks' );

             $taxonomy_args = array(
                 'hierarchical'          => false,//can't have descendants
                 'update_count_callback' => '_update_post_term_count',
                 'labels'                => array(
                         'name'              => $a_name.'s',
                         'singular_name'     => $a_name,
                         'search_items'      => __( 'Search '.$a_name.'s', WC_LS_TEXT_DOMAIN ),
                         'all_items'         => __( 'All '.$a_name.'s', WC_LS_TEXT_DOMAIN ),
                         'parent_item'       => null,
                         'parent_item_colon' =>null,
                         'edit_item'         => __( 'Edit '.$a_name, WC_LS_TEXT_DOMAIN ),
                         'update_item'       => __( 'Update '.$a_name, WC_LS_TEXT_DOMAIN ),
                         'add_new_item'      => __( 'Add New '.$a_name, WC_LS_TEXT_DOMAIN ),
                         'new_item_name'     => __( 'New '.$a_name, WC_LS_TEXT_DOMAIN )
                     ),
                 'show_ui'           => false,
                 'query_var'         => true,
                 'rewrite'           => array(
                     'slug'         => empty( $permalinks['attribute_base'] ) ? '' : trailingslashit( $permalinks['attribute_base'] ) . sanitize_title( $a_name.'s' ),
                     'with_front'   => false,
                     'hierarchical' => true
                 ),
                 'sort'              => false,
                 'public'            => true,
                 'show_in_nav_menus' => false,
                 'capabilities'      => array(
                     'manage_terms' => 'manage_product_terms',
                     'edit_terms'   => 'edit_product_terms',
                     'delete_terms' => 'delete_product_terms',
                     'assign_terms' => 'assign_product_terms',
                 )
             );
             register_taxonomy($attr,'product',$taxonomy_args);
            }
        }
    }
    /**
     * Creates a woocommerce product category from loystar
     * 
     * @param int $cat_id | the loystar category id
     * @return int | the equivalent woocommerce category
     */
    public function add_woo_cat_from_loystar($cat_id){
        $l_cat = array();
        $l_cats = $this->get_cachable_data('categories',['id'=>$cat_id,'single_data_index_form'=>true]);
        if(!is_array($l_cats))
            return 0;
        foreach($l_cats as $c_data){
            if($c_data['id'] == $cat_id){
                $l_cat = $c_data;
                break;
            }
        }
        
        if(!$l_cat)
            return 0;
        $taxonomy = 'product_cat';
        $id = 0;
        //check if a category like that already exists
        $cat_exist = term_exists($l_cat['name'],$taxonomy);
        if(isset($cat_exist['term_id'])){
            $id = $cat_exist['term_id'];
            update_term_meta($id,WC_LS_CAT_META_KEY,''.$l_cat['id'].'');
        }
        else{//create
           $new_cat = wp_insert_term($l_cat['name'],$taxonomy);
           if(isset($new_cat['term_id'])){//created
             $id = $new_cat['term_id'];
             update_term_meta($id,WC_LS_CAT_META_KEY,''.$l_cat['id'].'');
             update_term_meta($id,WC_LS_CAT_IMPORT_META_KEY,''.$l_cat['id'].'');
           }
        }
        return (int)$id;
    }
    /**
     * Creates a woocommerce product attribute
     * 
     * @param string $label_name
     * @return int|WP_Error , returns the id on success
     */
    public function create_woo_product_attribute( $label_name ){
        global $wpdb;
        $slug = sanitize_title( $label_name );
    
        if ( strlen( $slug ) >= 28 ) {
            return new WP_Error( 'invalid_product_attribute_slug_too_long', sprintf( __( 'Name "%s" is too long (28 characters max). Shorten it, please.', 'woocommerce' ), $slug ), array( 'status' => 400 ) );
        } elseif ( wc_check_if_attribute_name_is_reserved( $slug ) ) {
            return new WP_Error( 'invalid_product_attribute_slug_reserved_name', sprintf( __( 'Name "%s" is not allowed because it is a reserved term. Change it, please.', 'woocommerce' ), $slug ), array( 'status' => 400 ) );
        } elseif ( taxonomy_exists( wc_attribute_taxonomy_name( $label_name ) ) ) {
            //return new WP_Error( 'invalid_product_attribute_slug_already_exists', sprintf( __( 'Name "%s" is already in use. Change it, please.', 'woocommerce' ), $label_name ), array( 'status' => 400 ) );
            return 0;
        }
        $data = array(
            'attribute_label'   => $label_name,
            'attribute_name'    => $slug,
            'attribute_type'    => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public'  => 0, // Enable archives ==> true (or 1)
        );    
        $results = $wpdb->insert( "{$wpdb->prefix}woocommerce_attribute_taxonomies", $data );
    
        if ( is_wp_error( $results ) ) {
            return new WP_Error( 'cannot_create_attribute', $results->get_error_message(), array( 'status' => 400 ) );
        }
        $id = $wpdb->insert_id;
        do_action('woocommerce_attribute_added', $id, $data);
        wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
        delete_transient('wc_attribute_taxonomies');
        return $id;
    }

  /**
    * Method to delete Woo Product
    * 
    * @param int $id the product ID.
    * @param bool $force true to permanently delete product, false to move to trash.
    * @return WP_Error|boolean
    */
    public function delete_woo_product($id, $force = false)
    {
       $product = wc_get_product($id);
   
       if(empty($product))
           return new WP_Error(999, sprintf(__('No %s is associated with #%d', 'woocommerce'), 'product', $id));
   
       // If we're forcing, then delete permanently.
       if ($force)
       {
           if ($product->is_type('variable'))
           {
               foreach ($product->get_children() as $child_id)
               {
                   $child = wc_get_product($child_id);
                   $child->delete(true);
               }
           }
           elseif ($product->is_type('grouped'))
           {
               foreach ($product->get_children() as $child_id)
               {
                   $child = wc_get_product($child_id);
                   $child->set_parent_id(0);
                   $child->save();
               }
           }
   
           $product->delete(true);
           $result = $product->get_id() > 0 ? false : true;
       }
       else
       {
           $product->delete();
           $result = 'trash' === $product->get_status();
       }
   
       if (!$result)
       {
           return new WP_Error(999, sprintf(__('This %s cannot be deleted', 'woocommerce'), 'product'));
       }
   
       // Delete parent product transients.
       if ($parent_id = wp_get_post_parent_id($id))
       {
           wc_delete_product_transients($parent_id);
       }
       return true;
    }

    /**
     * Displays in number format 
     * 
     * @param int $value
     * @return string
     */
    public function number($value){
        $value = (int)$value;
        return number_format($value,0,'',',');
    }

    /**
     * Replace values in array to what you want
     * 
     * @return array
     */
    public function replace_value($array,$value = null,$replace = ''){
        return array_replace($array,
         array_fill_keys(
                array_keys($array, $value),
                $replace
        ));
    }
}
