<?php
/**
 * For handling the coupon page
 */
Class Wc_Ls_Coupon{
    
    /**    
     * The Loystar api object
     * 
     * @var Wc_Ls_Api obj
     */
    private $loystar_api = null;

    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){//the user has enabled it and is authenticated :)
            add_action('woocommerce_coupon_options', array($this,'add_custom_fields'));
            add_action('woocommerce_coupon_options_save', array($this,'save_custom_fields'));
        }
    }
    /**
     * Custom fields 
     */
    public function add_custom_fields(){
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
        $this->loystar_api = new Wc_Ls_Api();
        $list = $this->loystar_api->get_merchant_loyalty();
        if($list){
            $loyalty_list = array();
            $loyalty_list[''] = __( '', WC_LS_TEXT_DOMAIN);
           
            foreach($list as $loyalty){
                $loyalty_list[''.$loyalty['id'].''] = __( $loyalty['name'].' ('.$loyalty['reward'].')', WC_LS_TEXT_DOMAIN);
            }
            // Select
            woocommerce_wp_select(
                array(
                'id'      => WC_LS_COUPON_META_KEY,
                'label'   => __( 'Select Loystar Loyalty program you want to associate with this coupon', WC_LS_TEXT_DOMAIN), 
                'desc_tip' => 'true',
                'description' => __('Which Loyalty program do you want this coupon to work with? Note: once the associated loyalty program is activated on your site, then a qualified customer can automatically redeem this coupon',WC_LS_TEXT_DOMAIN),
                'options' => $loyalty_list
                )
            );
            if(isset($_GET['ls_coupon_link_to'])){//its coming from loyalty redirect, so set id
            ?>
                <script type="text/javascript">
                    var $ = jQuery;
                    $('#<?php echo WC_LS_COUPON_META_KEY; ?>').val('<?php echo $_GET['ls_coupon_link_to']; ?>');
                </script>
            <?php
            }
        }
    }
    /**
     * Saves the fields
     * 
     * @param $post_id
     */
    public function save_custom_fields($post_id){
        $loyalty_program = isset($_POST[WC_LS_COUPON_META_KEY]) ? $_POST[WC_LS_COUPON_META_KEY] : '';
        update_post_meta( $post_id, WC_LS_COUPON_META_KEY, esc_attr( $loyalty_program ) );
    }
}
new Wc_Ls_Coupon();