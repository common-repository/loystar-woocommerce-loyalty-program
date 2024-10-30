<?php
/**
 * For handling the product page stuff
 */
Class Wc_Ls_Product{
    
    /**    
     * The Loystar api object
     * 
     * @var Wc_Ls_Api obj
     */
    private $loystar_api = null;

    /**
     * The loystar products
     * 
     * @var array
     */
    private $loystar_products = array();

    /**
     * Product id
     * 
     * @var int
     */
    private $product_id = 0;

    /**
     * Construcdur :)
     */
    public function __construct(){
        if(wc_loystar()->is_enabled() && wc_loystar()->merchant_logged_in()){//the user has enabled it and is authenticated :)
            $p = $this->is_product_edit_page(); 
            if($p){
                $this->loystar_products = wc_loystar()->get_cachable_data('products',['id'=>0,'single_data_index_form'=>true]);
                //update the product from loystar
                wc_loystar()->update_woo_product($p,$this->loystar_products);
            }
            add_action('woocommerce_product_options_general_product_data', array($this,'add_custom_general_fields'));
            add_action('woocommerce_process_product_meta', array($this,'save_custom_general_fields'));
            //for variations
            add_action( 'woocommerce_product_after_variable_attributes',array($this,'add_custom_variation_fields'), 10, 3 );
            add_action('woocommerce_save_product_variation', array($this,'save_custom_variation_fields'), 10, 2 );
            
        }
    }
    /**
     * Custom general fields 
     */
    public function add_custom_general_fields(){
        
        $list = $this->loystar_products;
        if($list){
            $product_list = array();
            $product_list[''] = __( '', WC_LS_TEXT_DOMAIN);
            $product_list['0'] = __( 'None', WC_LS_TEXT_DOMAIN);

            foreach($list as $product){
                $product_list[''.$product['id'].''] = __( $product['name'], WC_LS_TEXT_DOMAIN);
            }
            // Select
            woocommerce_wp_select(
                array(
                'id'      => WC_LS_PRODUCT_META_KEY,
                'label'   => __( '<strong>Select Loystar Product to sync with</strong>', WC_LS_TEXT_DOMAIN), 
                'desc_tip' => 'true',
                'description' => __('Which product on your loystar account does this woocommerce product belong to',WC_LS_TEXT_DOMAIN),
                'custom_attributes' =>array(
                    'required' => 'true'),
                'options' => $product_list
                )
            );
        }
    }
    /**
     * Saves the fields
     * 
     */
    public function save_custom_general_fields($post_id){
        $loystar_product = isset($_POST[WC_LS_PRODUCT_META_KEY]) ? $_POST[WC_LS_PRODUCT_META_KEY] : '';
        if(!empty($loystar_product))
            update_post_meta( $post_id, WC_LS_PRODUCT_META_KEY, esc_attr( $loystar_product ) );
    }
    /**
     * Custom general fields
     * 
     * @param $loop
     * @param $variation_data
     * @param $variation
     */
    public function add_custom_variation_fields($loop, $variation_data, $variation){
        $product_id = wc_get_product()->get_id();
        $l_p_id = wc_loystar()->get_equiv_loystar_product($product_id);
        $l_product = wc_loystar()->get_cachable_data('products',['id'=>$l_p_id,'single_data_index_form'=>false]);
        $list = (isset($l_product['variants']) ? $l_product['variants'] : '');
        if(!empty($list)){
            $product_list = array();
            $product_list[''] = __( '', WC_LS_TEXT_DOMAIN);
            $product_list['0'] = __( 'None', WC_LS_TEXT_DOMAIN);

            foreach($list as $p){
                $product_list[''.$p['id'].''] = __( $p['value'], WC_LS_TEXT_DOMAIN);
            }
            // Select
            woocommerce_wp_select(
                array(
                'id'      => WC_LS_PRODUCT_VAR_META_KEY.'['.$loop.']',
                'label'   => __( '<strong>Select Loystar variant Product to sync with</strong>', WC_LS_TEXT_DOMAIN), 
                'value' => get_post_meta( $variation->ID, WC_LS_PRODUCT_VAR_META_KEY, true ),
                'desc_tip' => 'true',
                'description' => __('Which product on your loystar account does this woocommerce product belong to',WC_LS_TEXT_DOMAIN),
                'custom_attributes' =>array(
                    'required' => 'true'),
                'options' => $product_list
                )
            );
        }
    }
    /**
     * Saves the fields
     * 
     * @param $post_id
     */
    public function save_custom_variation_fields($variation_id, $i){
        $loystar_product = isset($_POST[WC_LS_PRODUCT_VAR_META_KEY][$i]) ? $_POST[WC_LS_PRODUCT_VAR_META_KEY] [$i]: '';
        if(!empty($loystar_product))
            update_post_meta( $variation_id, WC_LS_PRODUCT_VAR_META_KEY, esc_attr( $loystar_product ) );
    }

	/**
	 * Check if its a product edit page
	 * 
	 * @return int|bool | returns the product id or false
	 */
	protected function is_product_edit_page(){
		//global $pagenow;
		//$page = $pagenow.'?'.$_SERVER['QUERY_STRING'];
        $post_id = isset($_GET['post']) ? $_GET['post'] : '';
        $act = isset($_GET['action']) ? strtolower($_GET['action']) : '';
		if(empty($post_id))
			return false;
		try{//if this block works, its a product
			$product = new WC_Product($post_id);
			$id = $product->get_id();
			if($id == 0 || $act != 'edit')//not also an edit stuff
                return false;
			return $id;
		}
		catch(Exception $ex){
			//incase it didnt get a post id thats not a product
			return false;
		}
	}
}
new Wc_Ls_Product();