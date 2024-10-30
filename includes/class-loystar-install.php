<?php

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Wc_Ls_Install Class
 */
class Wc_Ls_Install {

    public function __construct() {
       // self::install();
    }

    /**
     * Plugin install
     * @return void
     */
    public static function install() {
        if (!is_blog_installed()) {
            return;
        }
        self::create_tables();
        self::enable_loyalty();
    }

    /**
     * plugins table creation
     * @global object $wpdb
     */
    private static function create_tables() {
        global $wpdb;
        $wpdb->hide_errors();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta(self::get_schema());
    }

    /**
     * Plugin table schema
     * @global object $wpdb
     * @return string
     */
    private static function get_schema() {
        global $wpdb;
        $collate = '';
        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }
        $table = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}loystar_wc_transaction_log` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
         `wc_order_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
        `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
         `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
         PRIMARY KEY (`id`),
         KEY `wc_order_id` (`wc_order_id`),
         KEY `meta_key` (`meta_key`(191))
        )$collate COMMENT='Stores sales transactions relating to order';";
     return $table;
    }

    /**
     * Enables the loyalty program option
     */
    private static function enable_loyalty(){
        $wc_ls_option_meta = ['enabled'=>'wc_loystar_is_enabled'];//on registeration hook,the global variable isnt recognised yet,so.. :)
        //global $wc_ls_option_meta;
        if(get_option($wc_ls_option_meta['enabled'],'empty') == 'empty')
            update_option($wc_ls_option_meta['enabled'],true,false);
    }

    /**
     * deletes the loyalty program option
     */
    public static function delete_loyalty(){
        global $wc_ls_option_meta;
            delete_option($wc_ls_option_meta['enabled']);
    }

    /**
     * Update DB version to current.
     *
     * @param string|null $version New WooCommerce DB version or null.
     */
    public static function update_db_version($version = null) {
   //     delete_option($wc_ls_prefix.'db_version');
     //   add_option($wc_ls_prefix.'db_version', is_null($version) ? WS_LS_PLUGIN_VERSION : $version );
    }
}
new Wc_Ls_Install();