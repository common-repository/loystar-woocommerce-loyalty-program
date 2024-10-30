<?php
/**
 * For handling the extra fields displayed on checkout
 */
Class Wc_Ls_Checkout{

    /**
     * The html dob css class name
     * 
     * @var string
     */
    private $dob_html_class = 'wc-ls-dob';

    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){
            //henqueue
            add_action( 'wp_enqueue_scripts', array($this,'enqueue_css' ));
            add_action( 'wp_enqueue_scripts', array($this,'enqueue_js' ));
            //woocommerce things
            add_filter('woocommerce_billing_fields', array($this,'add_billing_fields'),20,1);
            add_action('woocommerce_after_checkout_validation', array($this,'checkout_validate'));
            add_action('woocommerce_thankyou', array($this,'after_order_submit'));
            //add for later :)
            //add_filter('woocommerce_review_order_after_order_total', array($this,'redeem_voucher_process'));
        }
    }
    
    /**
     * enqueues all necessary scripts
     */
    public function enqueue_js(){
        //p_enqueue_script('NameMySccript','path/to/MyScript','dependencies_MyScript', 'VersionMyScript', 'InfooterTrueorFalse');
        //wp_register_script('wc_ls_jquery-ui','https://code.jquery.com/ui/1.12.1/jquery-ui.min.js',array('jquery','jquery-migrate'),WC_LS_PLUGIN_VERSION,true);
        wp_register_script('wc_ls_intl-phones-lib',wc_loystar()->plugin_url().'/assets/vendor/js/intlTelInput-jquery.min.js',array('jquery'),WC_LS_PLUGIN_VERSION,true);
        $script_dep = array('jquery-ui-datepicker','wc_ls_intl-phones-lib');
        if(is_checkout())//for checkout, to load properly
            $script_dep[] = 'wc-checkout';
        wp_register_script('wc_ls_js-script',wc_loystar()->plugin_url().'/assets/js/frontend'.WC_LS_MIN_SUFFIX.'.js',$script_dep,WC_LS_PLUGIN_VERSION,true);
        //localise script,
        global $wc_ls_woo_custom_field_meta;
        $wcjson = array('phoneValidatorName'=>$wc_ls_woo_custom_field_meta['billing_hidden_phone_field'],'url'=> esc_url_raw( rest_url() ),'pageUrl'=>get_page_link(),'userId'=>get_current_user_id(),'nonce'=>wp_create_nonce( 'wp_rest' ),'dobInput' => $this->dob_html_class, 'dateRange' => $this->date_range());
        $wcjson['phoneValidatorErrName'] = $wc_ls_woo_custom_field_meta['billing_hidden_phone_err_field'];
        //get phone value for international lib use
        $phone = wc_loystar()->get_woo_customer_meta($wcjson['userId'],'billing_phone');
        if(!empty($phone)){
            $wcjson['userPhone'] = $phone;
        }
        $wcjson['utilsScript'] = wc_loystar()->plugin_url().'/assets/vendor/js/utils.js';
        wp_localize_script( 'wc_ls_js-script', 'wcLsJson', $wcjson );
		//
		//wp_enqueue_script( 'wc_ls_jquery-ui');
        wp_enqueue_script('wc_ls_intl-phones-lib');
        wp_enqueue_script('wc_ls_js-script');
    }
    /**
     * enqueues all necessary css
     */
    public function enqueue_css(){
        wp_enqueue_style( 'wc_ls_jqueryui-css',wc_loystar()->plugin_url().'/assets/css/jquery-ui.min.css');
        wp_enqueue_style( 'wc_ls_intl-phones-lib-css',wc_loystar()->plugin_url().'/assets/vendor/css/intlTelInput.min.css');
        wp_enqueue_style( 'wc_ls_css-style',wc_loystar()->plugin_url().'/assets/css/style'.WC_LS_MIN_SUFFIX.'.css');
    }
    
    /**
     * Adds extra fields to woocommerce billing form
     */
    public function add_billing_fields($fields){
        global $wc_ls_woo_custom_field_meta;
        //$fields['billing_phone']['label'] .= __(' <small>(please add your country code. E.g. +234802111..., +233024343...)</small>', WC_LS_TEXT_DOMAIN);
        //add custom classes for jquery manipulations
        $fields['billing_phone']['class'][0] .= ' wc-ls-phone wc-ls-intl';
        //for email
        $fields['billing_email']['class'][0] .= ' wc-ls-email';

        $fields[$wc_ls_woo_custom_field_meta['billing_dob']] = array(
            'label' => __('Date of Birth &nbsp;<small>(You get a text from us with birthday offers. 😉)</small>', WC_LS_TEXT_DOMAIN), // Add custom field label
            'placeholder' => _x('dd/mm/yyyy','placeholder', WC_LS_TEXT_DOMAIN), // Add custom field placeholder
            'required' => true, // if field is required or not
            'clear' => false, // add clear or not
            'type' => 'text', // add field type
            'class' => array('form-row-wide '), // add class name
            'input_class' => array($this->dob_html_class)
        );
        $fields[$wc_ls_woo_custom_field_meta['billing_gender']] = array(
            'label' => __('Gender', WC_LS_TEXT_DOMAIN), // Add custom field label
            'placeholder' => _x('Select Gender','placeholder', WC_LS_TEXT_DOMAIN), // Add custom field placeholder
            'required' => true, // if field is required or not
            'clear' => false, // add clear or not
            'type' => 'select', // add field type
            'class' => array('form-row-wide wc-ls-style'), // add class name
            'options' => array(
                '' => 'Select Gender',
               'M' => 'Male',
               'F' => 'Female' 
            )
        );
        return $fields;
    }

    /**
     * For extra custom validation
     * 
     * @param array $data | the external data
     * @hook woocommerce_after_checkout_validation
     */
    public function checkout_validate($data){
        global $wc_ls_woo_custom_field_meta;
        $phone_name = $wc_ls_woo_custom_field_meta['billing_hidden_phone_field'];
        $phone_err_name = $wc_ls_woo_custom_field_meta['billing_hidden_phone_err_field'];
        $phone_valid_field = isset($_POST[$phone_name]) ? trim(strtolower($_POST[$phone_name])) : false;
        $phone_valid_err_field = isset($_POST[$phone_err_name]) ? trim($_POST[$phone_err_name]) : false;
        if(isset($_POST['billing_email']) && !$phone_valid_field){//there was an error, this way we know its coming directly from normal woocommerce, so no conflict :)
            wc_add_notice( __( $phone_valid_err_field, WC_LS_TEXT_DOMAIN ), 'error');
        }
    }

    /**
     * After order submit
     * 
     * @param int $order_id
     */
    public function after_order_submit($order_id){
        $resend_meta = strtolower(trim(get_post_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY,true) ) );
        if(empty($resend_meta))//make sure it doesnt exist at all, just in case
            update_post_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY,'no');
    }

    /**
     * Handles the loystar redeem qualification voucher process
     */
    public function redeem_voucher_process(){
        //get the rest api
        include_once( WC_LS_ABSPATH . 'includes/api/class-rest-api.php' );
        ?>
        <div class="wc-ls-redeem-comment"></div>
        <?php
    }

    /**
     * Gives the date range for datepicker
     * 
     * @param int $go_back(optional) , how many years to stop before the current year, default is 18 years
     * @return string, '[start_date]:[end_date]'
     */
    private function date_range($go_back = 18){
        $year = (int)date("Y") - $go_back;
        return '1910:'.$year.'';
    }
}
new Wc_Ls_Checkout();