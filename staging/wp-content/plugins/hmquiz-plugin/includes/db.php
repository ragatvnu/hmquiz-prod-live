<?php
if (!defined('ABSPATH')) exit;

function hmqz_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hmqz_leads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        email varchar(100) NOT NULL,
        name varchar(100) NOT NULL,
        score int(11) NOT NULL,
        total int(11) NOT NULL,
        percent float NOT NULL,
        level int(11) NOT NULL,
        levels int(11) NOT NULL,
        status varchar(50) NOT NULL,
        badge varchar(50) NOT NULL,
        categories text NOT NULL,
        topics text NOT NULL,
        elapsed_ms int(11) NOT NULL,
        title varchar(255) NOT NULL,
        url varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(HMQZ_PLUGIN_DIR . 'hmquiz-plugin.php', 'hmqz_create_leads_table');
