<?php
/**
 * For handling the order processing
 */
Class Wc_Ls_Orders{
    
    /**    
     * The Loystar api object
     * 
     * @var Wc_Ls_Api obj
     */
    private $loystar_api = null;
    
	/**
	 * Transient value for success note
	 * @var string
	 */
	private $transient_s_value = 'wc_loystar-order-trans-note-s';

	/**
	 * Transient value for failed note
	 * @var string
	 */
	private $transient_f_value = 'wc_loystar-order-trans-note-f';

	/**
	 * Transient timeout in seconds
	 * @var int
	 */
	private $transient_timeout = 5;

	/**
	 * Notice msg
	 * 
	 * @var string
	 */
	private $notice_msg = '';

	/**
	 * Note extra
	 * 
	 * @var string
	 */
	private $note_extra = '';
	/**
	 * Logged in user id
	 * 
	 * @var int
	 */
	protected $current_user_id = 0;

    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){//the user has enabled it and is authenticated :)
			$this->current_user_id = get_current_user_id();
			//to avoid typing twice :)
			$this->note_extra = ' by setting the custom field value of <strong>'.WC_LS_ORDER_RESEND_TRANS_KEY.'</strong> 
			to <strong>\'yes\'</strong> and updating the order. <a id="ls-go-to-meta"></a>';
			//this is what this->notice() will use, in case the transaction wasn't recorded.
			//if there's an admin notice, it empties the value, so as not to show
			$this->notice_msg = 'This transaction wasn\'t recorded to loystar, you can resend it'.$this->note_extra;
            //notice
            add_action('admin_notices',array($this,'success_submit_notice'));
			add_action('admin_notices',array($this,'failed_submit_notice'));
			//echo the notice of resending transaction. note, if the notice msg, is empty, it doesnt display
			add_action('admin_notices',array($this,'notice'));
			//$this->notice();
            //this hook is triggered when the status has been changed from something else to completed
			add_action( 'woocommerce_order_status_completed', array($this,'process_to_loystar_order'), 10, 1);
			add_action('woocommerce_process_shop_order_meta', array($this,'process_shop_order'), 0, 2);
		 //   add_action('woocommerce_order_status_changed', array($this,'process_to_loystar_order'), 10, 3);
		 add_action('admin_footer',array($this,'scroll_to_meta'));
	
        }
    }
    
    /**
     * Processes to loystar order
     * 
     * Proccesses the order to loystar api
	 * 
	 * @param int $order_id
     */
    public function process_to_loystar_order($order_id){
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $this->loystar_api = new Wc_Ls_Api();
        $api = $this->loystar_api;
		$order_success = $api->check_transaction_success($order_id);
		$resend = strtolower(trim(wc_loystar()->get_woo_order_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY) ) );
        if(!$order_success || ($resend == 'yes' || $resend == 1)){//hasn't been sent before or was requested to be sent again, you can send now :)
			//process to loystar api
            if($api->add_transaction($order_id)){
                $transient_value = 'Transaction has been recorded to loystar successfully!';
	            //Delete transient for the failed value
                delete_transient($this->transient_f_value.$this->current_user_id);
                set_transient($this->transient_s_value.$this->current_user_id,$transient_value,$this->transient_timeout);
            }
            else{
                //Delete transient for the success value
				delete_transient($this->transient_s_value.$this->current_user_id);
                $transient_value = 'Sorry an error occured while sending transaction to loystar.';
                //check what type of error
                if($api->get_transaction_error_type() == 1)//user at fault
					$transient_value = $api->get_transaction_error();
					if($resend != 'yes' && $resend != 1)//remind user
						$transient_value .= ' you can resend this transaction to loystar'.$this->note_extra;
               	set_transient($this->transient_f_value.$this->current_user_id,$transient_value,$this->transient_timeout);
			}
       }
	}

	/**
	 * Processes shop order
	 * 
	 * @param int $post_id
	 * @param $post
	 */
	public function process_shop_order($post_id, $post){
		$order_id = $post_id;
		$resend = strtolower(trim(wc_loystar()->get_woo_order_meta($order_id,WC_LS_ORDER_RESEND_TRANS_KEY) ) );
		if($resend == 'yes' || $resend == 1)//yes, so lets send
			$this->process_to_loystar_order($order_id);
	}

	/**
	 * Check if its an order edit page
	 * 
	 * @return int|bool | returns the order id or false
	 */
	protected function is_order_edit_page(){
		//global $pagenow;
		//$page = $pagenow.'?'.$_SERVER['QUERY_STRING'];
		$post_id = isset($_GET['post']) ? $_GET['post'] : '';
		if(empty($post_id))
			return false;
		try{//if this block works, its an order
			$order = new WC_Order($post_id);
			$id = $order->get_id();
			if($id == 0)
				return false;
			return $id;
		}
		catch(Exception $ex){
			//incase it didnt get a post id thats not an order
			return false;
		}
	}

	/**
	 * Show a notice on edit order page
	 */
	public function notice(){
		if(empty($this->notice_msg))
			return;//no need for stress
		$order_id = $this->is_order_edit_page();
		if($order_id){
			// if order was attempted to be sent
			$order_attempt = (int)wc_loystar()->get_woo_order_meta($order_id,WC_LS_ORDER_TRANS_SEND_KEY);
			include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        	$this->loystar_api = new Wc_Ls_Api();
        	$api = $this->loystar_api;
			$order_success = $api->check_transaction_success($order_id);
			if($order_attempt == 1 && !$order_success){//remind user that they can resend order
				echo wc_loystar()->notice_me($this->notice_msg,'warning');
			}
		}
	}

	/**
	 * Js to scroll to the custom meta
	 * 
	 * @return string
	 */
	public function scroll_to_meta(){
		if(!$this->is_order_edit_page())
			return;
		?>
		<script type="text/javascript">
			var $ = jQuery;
			$(document).on('ready',function(){
			const cId = $('input[value="<?php echo WC_LS_ORDER_RESEND_TRANS_KEY; ?>"]').closest('tr').attr('id');
			const linkMeta = $('#ls-go-to-meta');
			linkMeta.attr('href',`#${cId}`);
			linkMeta.attr('title',`Click here to set the value of <?php echo WC_LS_ORDER_RESEND_TRANS_KEY; ?>`);
			linkMeta.text('Click here to set');
			linkMeta.click(function(){
				const cIdE = $('#'+cId);
				$('html,body').animate({
				scrollTop: (cIdE.offset().top - 12)//on web, should be a bit up so the bar doesnt block it :)
				}, 1000);
			});
			});
		</script>
		<?php
	}
    
	/**
	 * Successful submit message
	 */
	public function success_submit_notice(){
		$t_value = $this->transient_s_value.$this->current_user_id;
		$success_f_value = $this->transient_f_value.$this->current_user_id;//remove the failed transient value 
		$transient = get_transient($t_value);
		if(!empty($transient)){
			//empty
			$this->notice_msg = '';
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
			//empty
			$this->notice_msg = '';
		?>
		<div class="error notice">
    		<p><?php echo $transient; ?></p>
		</div>
		<?php
		}
	}
}
new Wc_Ls_Orders();