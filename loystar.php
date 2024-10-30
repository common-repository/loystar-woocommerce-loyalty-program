<?php
/**
 * Plugin Name: Loystar - Woocommerce Loyalty Program
 * Plugin URI: https://loystar.co/wordpress
 * Description: Keep your customers coming back with the Loystar woo-commerce Loyalty Program Plugin. Connect your Loystar account with your online store and have all your transaction, customer and loyalty records in one place. Also send automatic birthday messages and offers and more.

 * Author: Loystar

 * Author URI: http://loystar.co

 * Version: 2.1.1
 * Requires at least: 4.9.0
 * Tested up to: 6.3.1
 * WC requires at least: 3.0
 * WC tested up to: 8.1
 * 

 * Text Domain: loystar-woocommerce-loyalty-program

 *

 */



if (!defined('ABSPATH')) {

    exit;

}

//make sure you update the version values when necessary

define( 'WC_LS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );

define( 'WC_LS_PLUGIN_FILE', __FILE__ );

define('WC_LS_TEXT_DOMAIN', 'loystar-woocommerce-loyalty-program');

define('WC_LS_PLUGIN_VERSION','2.0.0');

/**  environment, should be either test or production */
if( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ){ 
    define( 'WC_LS_ENVIRONMENT','test');
} else {
    define( 'WC_LS_ENVIRONMENT','production');
}


//for global option meta access :)

$wc_ls_option_meta = array(

    'enabled'=>'wc_loystar_is_enabled',

    'access_token'=>'wc_loystar_access_token',

    'client_token'=>'wc_loystar_client_token',

    'expiring'=>'wc_loystar_login_expire',

    'uid'=>'wc_loystar_uid',

    'm_id'=>'wc_loystar_m_id',//merchant id

    'loyalty_program'=>'wc_loystar_chosen_loyalty',

    'sub_expires'=>'wc_loystar_subscription_expiry',

    'branch'=>'wc_loystar_branch_id',

    'last_cron'=>'wc_loystar_last_cron'

);

//custom fields names

$wc_ls_woo_custom_field_meta = array(

    'billing_gender' =>'billing_wc_ls_gender',

    'billing_dob' =>'billing_wc_ls_dob',

    'billing_hidden_phone_field' =>'_wc_ls_phone_validator',

    'billing_hidden_phone_err_field'=>'_wc_ls_phone_validator_err',

);

// include dependencies file

if(!class_exists('Wc_Ls_Dependencies')){

    include_once dirname(__FILE__) . '/includes/class-loystar-dependencies.php';

}

// Include the main class.

if(!class_exists('Wc_Loystar')){

    include_once dirname(__FILE__) . '/includes/class-loystar.php';

}

function wc_loystar(){

    return Wc_Loystar::instance();

}

$GLOBALS['wc_loystar'] = wc_loystar();

