<?php
/**
 * Ajax and Api stuff here
 */
class Wc_Ls_REST_Api extends WP_REST_Controller{

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wc_ls/v1/';

    /**
     * METHOD
     *
     * @var string
     */
    public $method = 'POST';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'redeem';

    public function __construct(){
        /*api calling */
        add_action( 'rest_api_init', array($this,'register_routes'));
    }

    /**
     * Registers routes :)
     */
    public function register_routes() {
        register_rest_route( $this->namespace, 'redeem/', array(
        'methods' => $this->method,
        'callback' => array($this,'user_redeem_qualification'),
        'permission_callback' => array($this,'get_permission')
        ) );
        //for loyalty widget
        register_rest_route( $this->namespace, 'loyalty_widget/', array(
            'methods' => $this->method,
            'callback' => array($this,'add_user_for_loyalty'),
            'permission_callback' => array($this,'get_permission')
            ) );
    }
    
    /**
     * Handles checking the user for redeem qualification
     * 
     * @return WP_REST_Response
     */
    public function user_redeem_qualification(){
        global $wc_ls_option_meta;
        $active_loyalty = wc_loystar()->loyalty_program();
        if($active_loyalty < 1)//no active program
            return new WP_REST_Response('', 200);
        $loyalty_coupons = wc_loystar()->get_coupons_linked_to_loyalty($active_loyalty,false);
        if(count($loyalty_coupons) < 1)//no coupon associatioted with active program
            return new WP_REST_Response('',200);
        $a_loyalty_coupons = wc_loystar()->get_coupons_linked_to_loyalty($active_loyalty);
        if(count($loyalty_coupons) == count($a_loyalty_coupons))// applied active loyalty coupons is same amount as active loyalty coupons
            return new WP_REST_Response('',200);
        //now get loyalty details
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $api = new Wc_Ls_Api();
        $loyalty_d = $api->get_merchant_loyalty($active_loyalty);
        if(!$loyalty_d)
            return new WP_REST_Response('', 401);
        //get user details
        //still to fix this proper
        /*$user = $api->create_merchant_customer('','',$_POST['email'],$_POST['phone'],'','');
        $u_points_prop = 'simplepoints';//set points prop for user
        if(strtolower($loyalty_d['program_type']) == 'stampsprogram')//stamsprogram
            $u_points_prop = 'stampsprogram';
        $user_points = (int)$user[$u_points_prop];
        if($user_points <= $loyalty_d['threshold'])//user doesnt qualify :)
            return new WP_REST_Response('',200);
        *///////////////////////////////////////
        //now sort the unused loyalty coupons related to the active program
        $unused_coupons = array();
        foreach($loyalty_coupons as $c){
            foreach($a_loyalty_coupons as $a_c){
                if($a_c['id'] != $c['id'])//not yet applied
                    $unused_coupons[] = $c;
            }
        }
        //now compose msg
        $count_u = count($unused_coupons);
        $msg = 'You are qualified to redeem voucher code'.($count_u > 1 ? 's' : '').' ';
        for($i=0; $i<$count_u; $i++){
            $comma = ', ';
            $and_word = '';
            if(($count_u - $i) == 1){//last one
                $comma = '';
                if($count_u > 1)//use the and word
                    $and_word = 'and';
            }
            $msg .= $and_word.'<strong><span class="uppercase">'.$unused_coupons[$i]['code'].'</span></strong>'.$comma;
        }
        $msg .= ' from loystar.';
        return new WP_REST_Response($msg,200);
        //to be continued
    }

    /**
     * Adds user to loystar
     * 
     * @return WP_REST_Response
     */
    public function add_user_for_loyalty(){
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $gender = trim($_POST['gender']);
        $woo_id = trim($_POST['u_id']);
        if(empty($fname) || empty($lname) || empty($phone) || empty($gender) ){
            return new WP_REST_Response('Please fill in all the required fields.',422);
        }
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $api = new Wc_Ls_Api();
        $response = $api->create_merchant_customer($fname,$lname,$email,$phone,'',$gender);
        if(!$response)// didnt work, an error
            if($api->get_response_code() == 422)//entity ish, so most likely an existing email is to be registered with a new phone
                return new WP_REST_Response('Sorry, that email already exists on a loystar account',422);
            else    
                return new WP_REST_Response('Sorry, an error occured while submitting your data, please try again later',503);
        //worked
        //save the fields to woocommerce
        if($woo_id > 0){
            global $wc_ls_woo_custom_field_meta;
            wc_loystar()->update_woo_customer_meta($woo_id,$wc_ls_woo_custom_field_meta['billing_gender'],$gender);//custom field
            $cust = new WC_Customer($woo_id);
            $cust->set_billing_phone($phone);
            $cust->set_billing_email($email);
            $cust->save();
        }
        return new WP_REST_Response('Congrats '.$fname.', you\'re now a member of this loyalty program',200);
    }

    /**
	 * Checks if the api request is valid 
	 * @return bool true if valid,false otherwise
	 */
    public function get_permission(){
        $result = true;
        $nonce = $_POST['nonce'];
        if(wp_verify_nonce( $nonce, 'wp_rest' ) == false)
            $result = false;
        return $result;
    }
}
new Wc_Ls_REST_Api();