<?php
/**
 * Helps display subscription notice
 */
class Wc_Ls_Sub {
	
	/**
	 * The notice message
	 * 
	 * @var array
	 */
    protected $notice_msgs = array();
    
    /**
     * Dismissible
     * 
     * @var bool
     */
    protected $dismissible = false;    
    /**
     * message type
     * 
     * @var bool
     */
    protected $type = 'warning';
    /**
     * List of pages to show the notice
     * 
     * full slug
     * 
     * @var array
     */
    protected $pages_to_show = array('edit.php?post_type=shop_order','edit.php?post_type=product');

	/**
	 * Class constructor
     * 
     * @param string $msg
     * @param bool $is_dismissible
	 */
	public function __construct($msg = array(),$type= 'warning',$dismissible = false) {
        $this->notice_msg = $msg;
        $this->type = $type;
        $this->dismissible = $dismissible;
		global $pagenow;
		 $page = $pagenow.'?'.$_SERVER['QUERY_STRING'];
        if(in_array($page,$this->pages_to_show) || strpos($page,'loystar') !== false){
            include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
            $api = new Wc_Ls_Api();
            $is_there_note = false;
            if(wc_loystar()->merchant_logged_in()){
                $loyalty_id = wc_loystar()->loyalty_program();
                $is_merchant_subscribtion_exp = wc_loystar()->is_merchant_subscription_expired();
                if(!wc_loystar()->is_enabled()){
                    $msg = 'Please enable loystar loyalty program on this site for proper inventory and tracking
                    <br/><br/>
				<a href="'.admin_url(wc_loystar()->parent_slug(true).'-settings').'" class="ls-cool-btn">Click here</a> to enable now.';
                    if($page == wc_loystar()->parent_slug(true).'-settings'){//no need to show this again
                        $msg = '';
                    }
                    $is_there_note = true;
                    $this->notice_msg[] = $msg;
                }
                if($is_merchant_subscribtion_exp){
                    //leave empty cause the msg is the default subscription message
                    $is_there_note = true;
                    $this->notice_msg[] = wc_loystar()->get_default_subscription_note();
                }
                if(empty( wc_loystar()->get_site_branch() ) ){
                    wc_loystar()->set_site_branch();//try setting
                    if(empty(wc_loystar()->get_site_branch() ) ){//if it's still empty, alert
                        $this->notice_msg[] = 'You do not have an <strong>ecommerce branch</strong>, Please create one on your loystar account.';
                        $is_there_note = true;
                    }
                }
                if(!($loyalty_id > 0 && !empty($api->get_merchant_loyalty($loyalty_id)) ) && !$is_merchant_subscribtion_exp){//remind user to select a valid loyalty program
                    $msg = 'Please Activate a Loyalty program on this site
                    <br/><br/><a href="'.admin_url(wc_loystar()->parent_slug(true)).'" class="ls-cool-btn">Click here</a> to proceed.';
                    $loyalty_list_link = wc_loystar()->parent_slug(true);
                    $loyalty_add_link = $loyalty_list_link.'-loyalty';
                    if($page == $loyalty_list_link || $page == $loyalty_add_link){//no need to show this again
                        $msg = '';
                    }
                    $this->notice_msg[] = $msg;
                    $is_there_note = true;
                }
            }
            else{//merchant is logged out, notify them
                $this->notice_msg[] = 'You\'re not logged in to your loystar account. inventories won\'t be tracked';
                $is_there_note = true;
            }
            if($is_there_note)//show
                add_action('admin_notices',array($this,'notice'));
		}
    }
    /**
     * Sets notice message
     * 
     * @param array $value
     */
    public function set_notice_msg($value){
        $this->notice_msg = $value;
    }
    /**
     * Gets notice message
     * 
     * @return array
     */
    public function get_notice_msg(){
        return $this->notice_msg;
    }
    /**
	 * echo notice message
	 */
	public function notice(){
        $msgs = $this->get_notice_msg();
        foreach($msgs as $msg){
            echo wc_loystar()->notice_me($msg,'warning',$dismissible);
        }
	}
}
new Wc_Ls_Sub();
