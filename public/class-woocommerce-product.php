<?php
/**
 * For handling the product syncing, rather than doing cron, basic syncing can be done when page loads
 * 
 * These 3 pages so far will be enough to cover unupdated holes, far better than cron :)
 */
Class Wc_Ls_Product_Front{

    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){
            //when the product is added to cart
            add_action('woocommerce_add_cart_item_data', array($this,'add_to_cart_update'),10,2);
            //when on the product page
            add_action('woocommerce_before_single_product', array($this,'on_product_page'),15 );
        }
    }

    /**
     * Updates products on shop page
     * 
     * @hook woocommerce_before_shop_loop
     */
    public function on_shop_page(){
        include_once( WC_LS_ABSPATH . 'includes/api/class-api.php' );
		$api = new Wc_Ls_Api();
		$loystar_products = $api->get_merchant_products();//get all to reduce load time
        $products = wc_get_products(array(
            'status'=>'publish',
            'limit'=>'-1'//print all
        ));
        foreach($products as $p){
            wc_loystar()->update_woo_product($p->get_id(),$loystar_products);
        }
    }

    /**
     * Updates products with recent loystar info when added to cart
     * 
     * @hook woocommerce_add_cart_item_data
     * @param mixed $cart_item_data
     * @param int $product_id
     */
    public function add_to_cart_update($cart_item_data,$product_id){
        if($this->is_time_to_sync($product_id))
            wc_loystar()->update_woo_product($product_id);
    }

    /**
     * Updates current product on page
     * 
     * @hook woocommerce_before_single_product
     */
    public function on_product_page(){
        $id = wc_get_product()->get_id();
        if($this->is_time_to_sync($id))
            wc_loystar()->update_woo_product($id);
    }

    /**
     * Check if its time to sync
     * 
     * returns true if the interval is or greater than 24 hours, for now, there shouldnt be an interval
     * i thought it through, and its cool its updated almost immediately :)
     * @param int $product_id
     * @return bool
     */
    private function is_time_to_sync($product_id){
        //$synced_time = (int)wc_loystar()->get_woo_product_meta($product_id,WC_LS_PRODUCT_SYNC_TIME);
        //$now = strtotime('now');
        //$hours = 60*60;//hours in seconds
        //$minutes = 60;
        //$ans = ($now-$synced_time)/$minutes;
        //if($ans >= 24)//already 24 hours or more
            return true;
        //return false;
    }

}
new Wc_Ls_Product_Front();