<?php
/**
 * Loystar External Api stuff here
 */
class Wc_Ls_Api{

    /**
     * Endpoint namespace.
     * 
     * Leave the trailing slash '/' :)
     *
     * @var string
     */
    // protected $base_url = 'https://api.loystar.co/api/v2/';
    protected $base_url = 'https://api.loystar.co/api/v2/';

    /**
     * Environment
     *
     * this determines which api to use,values should either be 'test' or 'production'
     * if changed to production, uses the live api, if changed to test, uses the staging api
     * @var string
     */
    protected $_env = WC_LS_ENVIRONMENT;

    /**
     * Error message.
     * 
     * @var string
     */
    public $error = '';
    
    /**
     * Transaction error
     * 
     * @var string
     */
    private $transaction_err = '';
  
    /**
     * Transaction error type
     * 
     * -1 is system error, 1 is user error,
     * @var int
     */
    private $transaction_err_type = '';

    /**
     * Is wp_error 
     * 
     * @var bool
     */
    public $wp_error = false;
    
    /**
     * Error type
     * 
     * -1 is internal server error,1 is unathorised,2 is wp error, 3 is invalid details
     * @var int
     */
    public $error_type = 0;

    /**
     * wp_remote response
     */
    private $response = null;

    /**
     * wp_response_code
     * 
     * @var int
     */
    private $response_code = 0;
    /**
     * Header response
     * 
     * @var wp_remote_retrieve_headers
     */
    private $header_response = null;

    /**
     * body response
     * 
     * @var array
     */
    private $body_response = array();

	/**
	 * Constructor
	 */
    public function __construct(){
        if( trim( strtolower($this->_env) ) != 'production' ){
			$this->_env = 'test';
            $this->base_url = 'https://loystar-api.herokuapp.com/api/v2/';
        }
    }

    /**
     * Clears errors
     */
    private function clear_error(){
        $this->error = '';
        $this->is_wp_error = false;
        $this->error_type = 0;
    }

    /**
     * Clears transaction errors
     */
    private function clear_transaction_error(){
        $this->transaction_err = '';
        $this->transaction_err_type = 0;
    }
    /**
     * Gets the transaction error
     * 
     * @return string
     */
    public function get_transaction_error(){
        return $this->transaction_err;
    }
    
    /**
     * Gets the transaction error type
     * 
     * @return int
     */
    public function get_transaction_error_type(){
        return $this->transaction_err_type;
    }
    /**
     * sets the transaction error prop
     * 
     * @param string $msg
     * @param int $type
     */
    public function set_transaction_error_prop($msg,$type){
        $this->transaction_err = $msg;
        $this->transaction_err_type = $type;
    }
    
    /**
     * Resets values
     */
    private function reset(){
        $this->header_response = null;
        $this->response_code = 0;
        $this->body_response = array();
        $this->clear_error();
    }
    /**
     * Gets the response_code
     * 
     * @return int
     */
    public function get_response_code(){
        return $this->response_code;
    }
    /**
     * Gets header response
     * 
     * @return wp_remote_retrieve_headers
     */
    public function get_header_response(){
        return $this->header_response;
    }
    
    /**
     * Gets body response
     * 
     * @return array
     */
    public function get_body_response(){
        return $this->body_response;
    }

    /**
     * checks for Response errors
     * 
     * @param WP_ERROR|ARRAY $response
     * @return bool
     */
    public function check_for_errors($response){
        if(is_wp_error($response)){
            $this->error = $response->get_error_message();
            $this->is_wp_error = true;
            $this->error_type = 2;
            return true;
         }
        if(!is_array($response))
            $response = json_decode($response,true);
        $response_body = json_decode($response['body'],true);
        $this->response_code = wp_remote_retrieve_response_code($response);

        if($this->response_code == 422){
            //body stuff, so json decode
            $this->error = (isset($response_body['errors'][0]) ? $response_body['errors'][0] : $response_body['full_messages'][0]);
            //error msg varies :|, so check to an extent
            if(empty($this->error))
                $this->error = isset($response_body['error']) ? $response_body['error'] : 'a 422 error code, unprocessed inputs';
            $this->is_wp_error = false;
            $this->error_type = 4;
            return true;
         }
         else if($this->response_code != 200 && $this->response_code != '' && $this->response_code != 401){
            $this->error = $response['response']['code'].':'.$response['response']['message'];
            $this->is_wp_error = false;
            $this->error_type = 1;
            return true;
         }
         else if($this->response_code == 401){
            $this->error = $this->response_code;
            $this->is_wp_error = false;
            $this->error_type = 3;
            return true;
         }
         return false;
    }

    /**
     * Returns the normally used headers required by loystar api
     * 
     * @param array $args, in case you want to change some default values
     * @return array
     */
    protected function remote_headers($args = array()){
        global $wc_ls_option_meta;
        $default = array(
            'Content-Type'=>'application/json',
            'Uid' => get_option($wc_ls_option_meta['uid']),
            'Client' => get_option($wc_ls_option_meta['client_token']),
            'Access-Token' => get_option($wc_ls_option_meta['access_token']),
            'Expiry' => get_option($wc_ls_option_meta['expiring']),
            'Token' => 'Bearer'
        );
        if(empty($args))
            return $default;
        return wp_parse_args($args,$default);
    }

    /**
     * Returns the normally used wp_remote arg used by loystar api
     * 
     * @param array $args, in case you want to change some default values
     * @return array
     */
    protected function remote_args($args = array()){
        global $wc_ls_option_meta;
        $default = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            //'sslverify' => false,
            'blocking'    => true,
            'headers'     => $this->remote_headers()
        );
        if(empty($args))
            return $default;
        return wp_parse_args($args,$default);
    }

    /**
     * sends to a url
     * 
     * @param $url
     * @param $args
     * @return bool
     */
    public function get_endpoint($url,$args){
        $this->reset();
        $response = wp_remote_post($url, $args);
        if($this->check_for_errors($response))
            return false;
        //store
        $this->header_response = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        $r_data = json_decode($body,true);
        $this->body_response = $r_data;
       return true;
    }

    /**
     * Filters the response data to produce the relevant
     * 
     * @param array $r_data | the response data
     * @param int $id(optional)
     * @param bool $index_arr_form(optional) | if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted (optional)
     * @return array|bool
     */
    protected function filter_data($r_data,$id = 0,$single_data_index_arr_form = false,$include_deleted = false){
        $return_data = false;
        $id = (int)$id;
        foreach($r_data as $data){
            if(!$include_deleted){
                if($data['deleted'] == true)//ignore
                    continue;
            }
            if($id > 0){
                if($id == $data['id']){
                    if($single_data_index_arr_form)
                        $return_data[] = $data;
                    else
                        $return_data = $data;
                    break;
                }
            }
            else{
                $return_data[] = $data;
            }
        }
        return $return_data;
    }

    /**
     * Signs in the user
     * 
     * @param $email
     * @param $password
     * @return bool
     */
    public function sign_in($email,$password){
        global $wc_ls_option_meta;
        $url = $this->base_url.'auth/sign_in';
        $args = $this->remote_args([
        'headers'     => array('Content-Type'=>'application/x-www-form-urlencoded'),
        'body'        => array('email' => trim($email),'password' => $password)
        ]);
        if(!$this->get_endpoint($url,$args))
            return false;
        //update to the options table
        $headers = $this->get_header_response();
        $r_data = $this->get_body_response();
        //clear the prevent auth,if any, so as to prevent ish
        wc_loystar()->logout_merchant(false);
        $a_token = update_option($wc_ls_option_meta['access_token'],$headers['access-token'],false);
        $c_token = update_option($wc_ls_option_meta['client_token'],$headers['client'],false);
        $uid = update_option($wc_ls_option_meta['uid'],$headers['uid'],false);
        $m_id = update_option($wc_ls_option_meta['m_id'],$r_data['data']['id'],false);
        $expire = update_option($wc_ls_option_meta['expiring'],$headers['expiry'],false);
        $sub = $this->get_merchant_subscription();
        $sub_expire = update_option($wc_ls_option_meta['sub_expires'],$sub['expires_on'],false);
        wc_loystar()->set_site_branch();//set the default branch for ecommerce
       if(!($a_token && $c_token && $uid && $m_id && $expire) ){
           //they cant be updated and the values arent the same, chai, forgive my logic :)
           $this->error = 'couldn\'t update values';
           $this->error_type = -1;
           $this->is_wp_error = false;
           return false;
       }
       //all went through
       return true;
    }

    /**
     * Get merchant loyalty program
     * 
     * @param int $id(optional) | if 0, returns all
     * @param bool $single_data_index_arr_form(optional), if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted (optional)
     * @return array|bool
     */
    public function get_merchant_loyalty($id = 0,$single_data_index_arr_form = false,$include_deleted = false){
        $id = (int)$id;
        $url = $this->base_url.'get_merchant_loyalty_programs';
        $data = array('data' => array('time_stamp'=>0) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)])) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant();
            return false;
        }
       $r_data = $this->get_body_response();
       $return_data = $this->filter_data($r_data,$id,$single_data_index_arr_form,$include_deleted);
        return $return_data;
    }

    /**
     * Create merchant loyalty
     * 
     * @return array|bool
     */
    public function create_merchant_loyalty($name,$reward,$threshold,$program_type){
        $url = $this->base_url.'merchant_loyalty_programs';
        $data = array('data' => array('name'=>$name,'reward'=>$reward,'threshold'=>$threshold,'program_type'=>$program_type) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)])) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant();
            return false;
        }
        $r_data = $this->get_body_response();
        //all went through
        return $r_data;        
    }

    /**
     * Update merchant loyalty
     * 
     * @param int $id
     * @param array $args
     * @return array|bool
     */
    public function update_merchant_loyalty($id,$args){
        $url = $this->base_url.'merchant_loyalty_programs/'.(int)$id;
        $data = array('data' => $args );
        if(!$this->get_endpoint($url, $this->remote_args(['method'=>'PUT','body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant();
            return false;
        }
        $r_data = $this->get_body_response();
        //all went through
        return $r_data;   
    }
    /**
     * Get the customers
     * 
     * @param mixed $id (optional) the user_id number or email of the customer you want to look for
     * @param bool $single_data_index_arr_form(optional), if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted (optional)
     * @return array|bool
     */
    public function get_merchant_customers($id = '',$single_data_index_arr_form = false,$include_deleted = false){
        $url = $this->base_url.'get_latest_merchant_customers';
        $data = array('data' => array('time_stamp'=>0) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        $return_data = false;
        foreach($r_data as $data){
            if(!$include_deleted){
                if($data['deleted'] == true)//ignore
                    continue;
            }
            if($id > 0){
                if($id == $data['email'] || $id == $data['user_id']){
                    if($single_data_index_arr_form)
                        $return_data[] = $data;
                    else
                        $return_data = $data;
                    break;
                }
            }
            else{
                $return_data[] = $data;
            }
        }
        return $return_data;
    }

    /**
     * Creates merchant customer
     * 
     * even if the user already exists, it still returns the user,so we're still safe,
     * it most times creates a new user when the email and phone are different/new
     * 
     * @param string $fname
     * @param string $lname
     * @param string $email
     * @param string $phone
     * @param string $dob
     * @param string $sex
     * @param string $created_at(optional)
     * @return array|bool
     */
    public function create_merchant_customer($fname,$lname,$email,$phone,$dob,$sex,$created_at = ''){
        $url = $this->base_url.'add_user_direct';
        $data = array('data' => array(
            'first_name'=> $fname,
            'last_name'=>$lname,
            'email'=>$email,
            'phone_number'=>$phone,
            'date_of_birth'=>date("Y-m-d",strtotime($dob)),
            'sex'=>$sex,
            'created_at'=>$created_at//can be empty
            ) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
           // if($this->error_type != 4){//ignore error 4 :) 
                if($this->error_type == 3)//unauthorised,logout
                    wc_loystar()->logout_merchant(false);
                return false;
            //}
        }
        $r_data = $this->get_body_response();
        return $r_data;
    }

    /**
     * Updates merchant customer
     * 
     * even if the user already exists, it still returns the user,so we're still safe
     * 
     * @param int $id
     * @param array $args 
     * @return array|bool
     */
    public function update_merchant_customer($id,$args){
        $data_args = $args;
        $url = $this->base_url.'customers/update_customer/'.(int)$id;
        $data = array('data' => $data_args );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data = $this->get_body_response();
        return $r_data;
    }

    /**
     * Gets all product of merchant
     * 
     * @param int $id(optional), the id of the product you looking for
     * @param bool $single_data_index_arr_form(optional), if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted (optional)
     * @return array|bool
     */
    public function get_merchant_products($id = 0,$single_data_index_arr_form = false,$include_deleted = false){
        $url = $this->base_url.'get_latest_merchant_products?page[size]=50000';
        // $url = $this->base_url.'get_latest_merchant_products';
        $data = array('data' => array('time_stamp'=>0) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        $return_data = $this->filter_data($r_data,$id,$single_data_index_arr_form,$include_deleted);
        return $return_data;
    }

    /**
     * Gets all product categories of merchant
     * 
     * @param int $id(optional), the id of the product you looking for
     * @param bool $index_arr_form(optional), if true, a single data will be returned as data[0][...]
     * @param bool $include_deleted (optional)
     * @return array|bool
     */
    public function get_merchant_product_categories($id = 0,$single_data_index_arr_form = false,$include_deleted = false){
        $url = $this->base_url.'get_latest_merchant_product_categories';
        $data = array('data' => array('time_stamp'=>0) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        $return_data = $this->filter_data($r_data,$id,$single_data_index_arr_form,$include_deleted);
        return $return_data;
    }

    /**
     * Adds product category
     * 
     * 
     * @param string $cat_name
     * @return array|bool
     */
    public function add_merchant_product_category($cat_name){
        $url = $this->base_url.'add_product_category';
        $data = array('data' => array('name'=>$cat_name) );
        if(!$this->get_endpoint($url, $this->remote_args(['body' => json_encode($data)] )) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        return $r_data;
    }

    /**
     * Gets merchant subscription state
     * 
     * @return array|bool
     */
    public function get_merchant_subscription(){
        $url = $this->base_url.'get_merchant_current_subscription';
        //$data = array();
        if(!$this->get_endpoint($url, $this->remote_args(['method'=>'GET']) ) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        return $r_data;
    }

    /**
     * Gets a list of business branches
     * 
     * @param mixed $branch(optional), if not empty, returns the particular branch,values can be id or name
     * can be a kind of csv, so you check for more than one word, use <|> as delimiter
     * @return array|bool
     */
    public function get_business_branches($branch = ''){
        $url = $this->base_url.'business_branches';
        //$data = array();
        if(!$this->get_endpoint($url, $this->remote_args(['method'=>'GET']) ) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        $r_data =  $this->get_body_response();
        if(!empty($branch) || $branch != 0){//select a particular one
            $key = 'name';
            if((int)($branch) > 0)//its a number
                $key = 'id';
            $data = array();
            $b_words = explode('<|>',$branch);
            foreach($r_data as $d){
                foreach($b_words as $word){
                  if(stripos($d[$key],$word) !== false || $d[$key] == $word){
                        $data = $d;
                        break;
                    }
                }
            }
            $r_data = $data;
        }
        return $r_data;
    }

    /**
     * Add product to a business branch
     * 
     * @param int $branch_id
     * @param int $product_id
     * @param int $qauntity
     * @return array|bool
     */
    public function add_product_to_branch($branch_id,$product_id,$qauntity){
        $url = $this->base_url.'add_inventory';
        $data = array('data' => array(
            'business_branch_id' => (int)$branch_id,
            'product_id' => (int)$product_id,
            'quantity' => (int)$qauntity
            ) );
        if(!$this->get_endpoint($url, $this->remote_args(['method'=>'POST','body' => json_encode($data)]) ) ){
            if($this->error_type == 3)//unauthorised,logout
                wc_loystar()->logout_merchant(false);
            return false;
        }
        //$r_data =  $this->get_body_response();
        return true;
    }

    /**
     * Adds transaction to loystar
     * 
     * @param WC_ORDER|INT $order
     * @return bool
     */
    public function add_transaction($order){
        $this->clear_transaction_error();
        add_filter( 'http_request_timeout', array($this,'extend_timeout') );//to prevent timeout wahala :)
        if(is_int($order)){
            try{
                $order = new WC_Order( $order );
            }
            catch(Exception $ex){//fake order, you're under harrest :)
                return false;
            }
        }
        $order_id = $order->ID;
        //the transaction was attempted to be sent, so update the meta
        wc_loystar()->update_woo_order_meta($order_id,WC_LS_ORDER_TRANS_SEND_KEY,'1');
        //continue :)
        $branch = wc_loystar()->get_site_branch();
        if(empty($branch)){//couldnt get, add the log
            $this->set_transaction_error_prop('no ecommerce branch.',1);
            $this->add_log($order_id,'branch_id_error',$this->get_transaction_error());
            return false;
        }
        $o_data = $order->data['billing'];
        $fname = $o_data['first_name'];
        $lname = $o_data['last_name'];
        $email = $o_data['email'];
        $phone = $o_data['phone'];
        //from the custom data, fetch differently
        global $wc_ls_woo_custom_field_meta;
        $dob = trim(wc_loystar()->get_woo_order_meta($order_id,'_'.$wc_ls_woo_custom_field_meta['billing_dob'],true));
        $sex = strtoupper( trim(wc_loystar()->get_woo_order_meta($order_id,'_'.$wc_ls_woo_custom_field_meta['billing_gender'],true)) );
        $total = $order->get_total();

        global $wc_ls_option_meta;
        //somewhere around here, youre to check if the customer already exists, if not insert
        $customer = $this->create_merchant_customer($fname,$lname,$email,$phone,$dob,$sex);
      
        if(!$customer){//couldnt add, add the log
            $this->set_transaction_error_prop('error checking for customer:'.$this->error.':'.$this->error_type,-1);
            $this->add_log($order_id,'adding_customer_error',$this->get_transaction_error());
            return false;
        }
        //update merchant customer
        $c_a = [
            'first_name'=> $fname,
            'last_name'=>$lname,
            'email'=>$email,
            'phone_number'=>$phone,
            'date_of_birth'=>date("Y-m-d",strtotime($dob)),
            'sex'=>$sex
        ];
        $update_customer = $this->update_merchant_customer($customer['id'],$c_a);
        if(!$update_customer){//couldnt update, add the log
            $this->set_transaction_error_prop('error trying to update customer_'.$customer['id'].':'.$this->error.':'.$this->error_type,-1);
            $this->add_log($order_id,'updating_customer_error',$this->get_transaction_error());
            return false;
        }
        //get admin stuff
        $loyalty_id =  (int)get_option($wc_ls_option_meta['loyalty_program']);
        $merchant_id =  (int)get_option($wc_ls_option_meta['m_id']);
        $loyalty_program = $this->get_merchant_loyalty($loyalty_id);
        if($loyalty_id == 0){
            $this->set_transaction_error_prop('admin hasn\'t set an active loyalty program.',1);
            $this->add_log($order_id,'admin_error','admin hasn\'t set an active loyalty program');
            return false;
        }
        if(!$loyalty_program){//couldnt get, add the log
            $this->set_transaction_error_prop('error trying to retrieve loyalty program with id '.$loyalty_id.' :'.$this->error.':'.$this->error_type,-1);
            $this->add_log($order_id,'loyalty_program_error',$this->get_transaction_error());
            return false;
        }
        $send_data = array('sale'=> [
            "is_paid_with_cash" => "false",
            "is_paid_with_card" => "true",
            "is_paid_with_mobile" => "false",
            "business_branch_id" => $branch,
			"user_id" => $customer['user_id'],
            "payment_reference" => "woo_comm_".$order_id,
            "reference_code" => time(),
            "transactions" => array()          
            ]);
        $transaction_data = array();//for product
		$transaction_data_array = array(
                "points"=> 0,
                "stamps"=> 0,
                "program_type" => $loyalty_program['program_type'],
                "amount" => 0,
                "user_id" => $customer['user_id'],//use the user_id this time
                "customer_id" => $customer['id'],
                "merchant_id" => $merchant_id,
                "merchant_loyalty_program_id" => $loyalty_id
                );
		if(strtolower($loyalty_program['program_type']) == 'simplepoints')
			unset($transaction_data_array['stamps']);
		else
            unset($transaction_data_array['points']);
        //incase theres a 500 error while adding product to branch
        $add_p_to_branch_500_error = false;
        $p_to_branch_500_error_data = array();
        //404
        $add_p_to_branch_404_error = false;
        $p_to_branch_404_error_data = array();
        //store wc_products in array for later meta update
        $wc_product_ids = array();
        // Iterating through each "line" items in the order
        foreach ($order->get_items() as $item_id => $item_data) {
            $item_total = $item_data->get_total();//Get the item line total
            $wc_product_id = $item_data['product_id'];
            $wc_product_ids[] = $wc_product_id;
            $loystar_equiv_product_id = (int)wc_loystar()->get_equiv_loystar_product($wc_product_id);
            if($loystar_equiv_product_id > 0)//solves the transaction error issue for product id with zero
                $transaction_data_array['product_id'] = $loystar_equiv_product_id;
            //check if product is already in the branch
            if($loystar_equiv_product_id > 0){//valid
                $branch_product = wc_loystar()->get_products_in_branch($branch,$loystar_equiv_product_id);
                $branch_product_q = $branch_product ? $branch_product['quantity'] : 0;
                if(!$branch_product || $branch_product_q < $item_data->get_quantity()){//product isnt in branch, or branch product quantity less than item quantity, add
                    if(!$this->add_product_to_branch($branch,$loystar_equiv_product_id,$item_data->get_quantity())){
                        if($this->get_response_code() == 500){
                            $p_to_branch_error_500_data[] = array('wc_product_id'=>$wc_product_id,'ls_product_id'=>$loystar_equiv_product_id);
                            $add_p_to_branch_500_error = true;
                        }
                        elseif($this->get_response_code() == 404){//incase of this, let the admin know
                            $p_to_branch_404_error_data[] = array('wc_product_id'=>$wc_product_id,'ls_product_id'=>$loystar_equiv_product_id);
                            $add_p_to_branch_404_error = true;
                        }
                    }
                }
            }
            //quantity of item 
            $transaction_data_array['quantity'] = $item_data->get_quantity();
            //timestamp
            $transaction_data_array['created_at'] = time();
            //product type
            $transaction_data_array['product_type'] = "product";

            //update transaction_data_array
			if(strtolower($loyalty_program['program_type']) == 'simplepoints')
				$transaction_data_array['points'] = (int)$item_total;
			else
				$transaction_data_array['stamps'] = $item_data->get_quantity();
		
			$transaction_data_array['amount'] = $item_total;
            $transaction_data[] = $transaction_data_array;
        }
        $send_data['sale']['transactions'] = $transaction_data;
        // var_dump($send_data); die;
        //check if there was error 500 while adding product to branch error
        if($add_p_to_branch_500_error){//record
            $this->add_log($order_id,'add_product_to_branch_500_error','500 error while trying to add product(s) to branch:'.json_encode($p_to_branch_500_error_data));
        }
        $url = $this->base_url.'sales';
        $send_data_json = json_encode($send_data);
        // print_r($send_data_json); die;
        $remote_args =  $this->remote_args(['body' => $send_data_json]);
        if(!$this->get_endpoint($url, $remote_args)){
            if($this->error_type == 4)//unprocessed entity, show admin
                $this->set_transaction_error_prop('error recording sales to loystar: <strong>'.$this->error.'</strong>.',1);
            else if($this->get_response_code() == 500){
                //check if there was a 404 error, that'll most likely be what caused it.
                if($add_p_to_branch_404_error){//record
                    $this->set_transaction_error_prop('error recording sales to loystar: <strong>Couldn\'t add order product(s) to business branch due to server error</strong>.',1);
                    $this->add_log($order_id,'add_product_to_branch_400_error','404 error while trying to add product(s) to branch:'.json_encode($p_to_branch_404_error_data));
                    return false;
                }
                $this->set_transaction_error_prop('error while fetching api:'.$this->error.':'.$this->error_type,-1);
            }
            else
            $this->set_transaction_error_prop('error while fetching api:'.$this->error.':'.$this->error_type,-1);
            //add to log with the data that was to be sent for easier debugging stuff :)
            $this->add_log($order_id,'transaction_error',$this->get_transaction_error());
            $this->add_log($order_id,'transaction_error_data',$send_data_json);
            //wc_loystar()->update_woo_order_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY,'yes');//need to resend, since it wasn't successfully sent
            return false;
        }
        $r_data = $this->get_body_response();
        //successful
        //all went through
        $this->add_log($order_id,'transaction_sent','true');
        //add others, this isn't necessary, just to keep record
        foreach($r_data as $key=>$value){
            if(is_array($value))
                $value = json_encode($value);
            $this->add_log($order_id,$key,$value);
        }
        //update the products meta
        foreach($wc_product_ids as $p_id){//set sync time to empty to allow sync for the products
            wc_loystar()->update_woo_product_meta($p_id,WC_LS_PRODUCT_SYNC_TIME,'');
        }
        $resent = strtolower(trim(wc_loystar()->get_woo_order_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY) ) );
        if($resent == 'yes' || $resent == 1)
            $this->add_log($order_id,'resent','1');
        //add the custom_order_meta
        wc_loystar()->update_woo_order_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY,'no');//no need to resend, since it was successfully sent
        return true;
    }

    /**
     * Handling the query for the log table
     * 
     * Must be any wp_meta table format
     */
    public function add_log($order_id,$meta_key,$meta_value){
        global $wpdb;
        $table_name = "{$wpdb->prefix}loystar_wc_transaction_log";
        $prepare = $wpdb->prepare( "INSERT INTO ".$table_name." (wc_order_id,meta_key,meta_value)  VALUES(%d,%s,%s)", $order_id,$meta_key,$meta_value );
        $wpdb->query($prepare);
    }
    /**
     * Checks if a transaction has already been sent succesfully to loystar
     * 
     * @return bool
     */
    public function check_transaction_success($order_id){
        global $wpdb;
        $table_name = "{$wpdb->prefix}loystar_wc_transaction_log";
        $prepare = $wpdb->prepare( "SELECT meta_value FROM ".$table_name." WHERE  wc_order_id = %d AND meta_key = 'transaction_sent'", $order_id );
        $result = $wpdb->get_results($prepare,ARRAY_A);
        if(empty($result))
            return false;
        if($result[0]['meta_value'] != 'true')
            return false;
        return true;
    }

    /**
     * change time out, in case, cause we're looping
     */
    public function extend_timeout($time = 10){
        //Default timeout is 5
        return (int)$time;
    }
}
