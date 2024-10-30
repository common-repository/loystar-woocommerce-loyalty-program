<?php

/**
 * Loystar Woocommerce loyalty Uninstall
 *
 * Uninstalling Loystar Woocommerce loyalty and deleting it's table.
 *
 * @author  Loystar Solutions <hello@loystar.co>
 * @version 1.0.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {

    exit;

}

global $wpdb, $wp_version;

//keys

$wpdb->query("DELETE FROM {$wpdb->base_prefix}options WHERE option_name LIKE '%wc_loystar%'");

$wpdb->query("DELETE FROM {$wpdb->base_prefix}options WHERE option_name LIKE '%wc_ls_%'");//delete those transient ish, incase :)

// Table

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->base_prefix}loystar_wc_transaction_log");

// Clear any cached data that has been removed

wp_cache_flush();

