<?php
/**
 * class-auth-install.php
 * Path: modules/auth/class-auth-install.php
 *
 * Creates the password requests table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYA_Auth_Install {

    public static function install() {
        global $wpdb;

        $table = $wpdb->prefix . 'mya_password_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            new_password varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at datetime NOT NULL,
            approved_at datetime DEFAULT NULL,
            note text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        add_option( 'mya_password_requests_table_version', '1.0' );
    }

    // call this on plugin activation from your loader or myadvisers.php:
    // MYA_Auth_Install::install();
}
