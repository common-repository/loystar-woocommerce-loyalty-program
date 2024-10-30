<?php
/**
 * For processing and displaying the loyalty widget
 */
Class Wc_Ls_Loyalty_widget{

    /**
     * Woo customer details
     * 
     * @var array
     */
    private $woo_customer_details = array();

    /**
     * Current woo customer id
     * 
     * @var int
     */
    private $current_woo_c_id = 0;
    
    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){
            //get the rest api
            include_once( WC_LS_ABSPATH . 'includes/api/class-rest-api.php' );
            add_shortcode(WC_LS_LOYALTY_WIDGET_SHORTCODE,array($this,'shortcode'));
        }
    }

   /**
    * Handles the loyalty widget shortcode
    *
    * @param array $atts
    * @param string $content
    * @return string
    */
    public function shortcode($atts,$content = null){
        $this->current_woo_c_id = get_current_user_id();
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $api = new Wc_Ls_Api();
        $active_loyalty = (int)wc_loystar()->loyalty_program();
        $loyalty = $api->get_merchant_loyalty($active_loyalty);
        if(!$loyalty || $active_loyalty == 0){//best way to handle an api error for now :)
            return $this->show_error();
        }
        $loyalty_name = $loyalty['name'].' - '.$loyalty['reward'];
        $result = '
        <div class="ls-loyalty-widget-box">
            <div class="head">
            <h3>'.$loyalty_name.'</h3>
            </div>
            <div class="body">';
        
        if(!( $this->is_woo_customer_logged_in() && $this->is_already_a_customer($this->current_woo_c_id) ) )
            $result .= $this->form();
        else
            $result .= $this->user_points();
        $result .='
            </div>
        </div>';
        return $result;
    }

    /**
     * Checks if a customer is logged in
     * 
     * @return bool
     */
    public function is_woo_customer_logged_in(){
        if(get_current_user_id() > 0)
            return true;
        return false;
    }

    /**
     * Gets the customer billing details
     * 
     * @param int $woo_customer_id
     * @return array|bool
     */
    public function get_woo_customer_details($woo_customer_id){
        $customer = new WC_Customer($woo_customer_id);
        if(empty($customer->get_email()))
            return false;
        $result = $customer->get_billing();
        //add custom billing fields to it
        global $wc_ls_woo_custom_field_meta;
        $result['gender'] = wc_loystar()->get_woo_customer_meta($woo_customer_id,$wc_ls_woo_custom_field_meta['billing_gender']);
        $this->woo_customer_details = $result;
        return $result;
    }

    /**
     * Checks if a woo customer is already a loystar customer
     * 
     * @param int $woo_customer_id
     * @return bool
     */
    public function is_already_a_customer($woo_customer_id){
        $email = isset($this->get_woo_customer_details($woo_customer_id)['email']) ? $this->get_woo_customer_details($woo_customer_id)['email'] : '';
        if(empty($email))
            return false;
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$api = new Wc_Ls_Api();
        $result = $api->get_merchant_customers($email);
        if($result)
            return true;
        return false;
    }

    /**
     * shows the form
     * 
     * @return string
     */
    public function form(){
        $this->get_woo_customer_details($this->current_woo_c_id);
        $result = '
        <form class="ls-loyalty-widget-box" method="post">
            <div class="wc-ls-alert wc-ls-success hidden"></div>
            <label>First Name <span class="required">*</span></label>
            <input class="form-control" type="text" name="fname" placeholder="First Name" value="'.$this->get_c_value('first_name').'" required>
            <label>Last Name <span class="required">*</span></label>
            <input class="form-control" type="text" name="lname" placeholder="Last Name" value="'.$this->get_c_value('last_name').'" required>
            <label>Email</label>
            <input class="form-control" type="email" name="email" placeholder="Email Address" value="'.$this->get_c_value('email').'">
            <label>Phone Number <span class="required">*</span></label>
            <span class="wc-ls-intl"><input class="form-control" type="tel" name="phone" value="'.$this->get_c_value('phone').'" required></span>
            <label>Gender<span class="required">*</span></label>
            <select name="gender" placeholder="Select gender" class="form-control" required>
            <option value = ""></option>
            <option value = "M">Male</option>
            <option value = "F">Female</option>
            </select>
            <small>fields marked with <span class="required">*</span> are required</small><br/>';
            //wp_nonce_field('wp_rest', 'nonce');
	    $result .='
            <button type="submit" class="cool-btn">
                Register
            </button>
            </form>
            <script type="text/javascript">
                var $ = jQuery;
                $(document).ready(function(){
                    $("form.ls-loyalty-widget-box").submit(function(e){
                    e.preventDefault();
                    wcLsLoyaltySubmit();
                });
                $("form.ls-loyalty-widget-box select").val("'.$this->get_c_value('gender').'");
                });
            </script>';
	return $result;
    }

    /**
     * The point shower
     *
     * @return string
     */
    public function user_points(){
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$api = new Wc_Ls_Api();
      
        $points = 1;
        $result = '
        <div class="points">
            <p>You have <strong>'.$points.'</strong> point'.($points > 1 || $points < 1 ? 's' : '').'
        </div>';
	return $result;
    }

    /**
     * gets the value of woo_customer_details
     * 
     * @param string $val | the key value, must be a valid key
     * @return string
     */
    public function get_c_value($val){
        if(empty($this->woo_customer_details))
            return '';
        if(!isset($this->woo_customer_details[$val]))
            return '';
        return $this->woo_customer_details[$val];
    }

    /**
     * Displays a nice error
     * 
     * @param string $msg(optional),'' | if empty, shows the default msg
     * @return string
     */
    public function show_error($msg = ''){
        $msg = isset($msg) ? trim($msg) : '';
        if(empty($msg)){
        $msg = '
        <div class="ls-loyalty-widget-box">
            <div class="body">
            <div class="ls-alert ls-danger">
            <p style="text-align:center;margin:0;">
                Sorry, an error occured while trying to display loyalty widget, please try again later. <br/>
                <i class="fa fa-3x fa-frown-o"></i>
            </p>
        </div>
        </div>
        </div>';
        
        }
	return $msg;
    }
}
new Wc_Ls_Loyalty_Widget();
