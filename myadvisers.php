<?php
/**
 * Plugin Name: MyAdvisers
 * Description: Scalable community plugin with frontend user system: account dashboard, leads, commissions, business directory, messages, and admin tools.
 * Version: 5.1
 * Author: Biswajit
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ----------------------------------------------------------------
 * Plugin Constants (Core)
 * ---------------------------------------------------------------- */
if ( ! defined( 'MYA_PLUGIN_DIR' ) ) {
    define( 'MYA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MYA_PLUGIN_PATH' ) ) {
    define( 'MYA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) ); 
}

if ( ! defined( 'MYA_PLUGIN_URL' ) ) {
    define( 'MYA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MYA_PLUGIN_VERSION' ) ) {
    define( 'MYA_PLUGIN_VERSION', '5.1' );
}

/* ----------------------------------------------------------------
 * Core Includes
 * ---------------------------------------------------------------- */
$activator_file = MYA_PLUGIN_DIR . 'includes/class-activator.php';
if ( file_exists( $activator_file ) ) {
    require_once $activator_file;
}

/* ----------------------------------------------------------------
 * Load Modules (Account System & User Dashboard)
 * ---------------------------------------------------------------- */
$account_module = MYA_PLUGIN_DIR . 'modules/account/module.php';
if ( file_exists( $account_module ) ) {
    require_once $account_module;
}

/* Account Shortcodes */
$shortcodes_file = MYA_PLUGIN_DIR . 'modules/account/shortcodes.php';
if ( file_exists( $shortcodes_file ) ) {
    require_once $shortcodes_file;
}

/* Admin Module */
$admins_loader = MYA_PLUGIN_DIR . 'modules/admins/admin.php';
if ( file_exists( $admins_loader ) ) {
    require_once $admins_loader;
}

/* ----------------------------------------------------------------
 * Activation / Deactivation Hooks
 * ---------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'MyA_Activator', 'activate' ) );
register_activation_hook( __FILE__, 'mya_flush_rewrite_on_activation' );
register_deactivation_hook( __FILE__, 'mya_on_deactivate' );

function mya_flush_rewrite_on_activation() {
    if ( class_exists( 'MYA_Account_Rewrite' ) && method_exists( 'MYA_Account_Rewrite', 'flush_rewrites' ) ) {
        MYA_Account_Rewrite::flush_rewrites();
    }
    flush_rewrite_rules();
}

function mya_on_deactivate() {
    flush_rewrite_rules();
}

/* ----------------------------------------------------------------
 * Admin Menu
 * ---------------------------------------------------------------- */
add_action( 'admin_menu', 'mya_register_admin_menu' );

function mya_register_admin_menu() {

    add_menu_page(
        'MyAdvisers Dashboard',
        'MyAdvisers',
        'manage_options',
        'myadvisers-dashboard',
        'mya_render_dashboard_page',
        'dashicons-admin-site',
        25
    );

    add_submenu_page( 'myadvisers-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'myadvisers-dashboard', 'mya_render_dashboard_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Users List', 'Users List', 'edit_users', 'listed-users', 'mya_render_users_list_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Leads', 'Leads', 'manage_options', 'mya-leads', 'mya_render_leads_list_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Commissions', 'Commissions', 'manage_options', 'mya-commissions', 'mya_render_commission_list_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Business', 'Business', 'manage_options', 'mya-business', 'mya_render_business_list_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Directory', 'Directory', 'manage_options', 'mya-directory', 'mya_render_directory_list_page' );
    add_submenu_page( 'myadvisers-dashboard', 'Messages', 'Messages', 'manage_options', 'mya-messages', 'mya_render_messages_list_page' );

    // Hidden: Edit User
    add_submenu_page( null, 'Edit User Profile', 'Edit User Profile', 'edit_users', 'mya-edit-user', 'mya_render_edit_user_page' );
}

/* ----------------------------------------------------------------
 * Admin View Loaders
 * ---------------------------------------------------------------- */
function mya_render_dashboard_page() { mya_load_view( 'dashboard.php' ); }
function mya_render_users_list_page() { mya_load_view( 'users-list.php' ); }
function mya_render_leads_list_page() { mya_load_view( 'leads-list.php' ); }
function mya_render_commission_list_page() { mya_load_view( 'commission-list.php' ); }
function mya_render_business_list_page() { mya_load_view( 'business-list.php' ); }
function mya_render_directory_list_page() { mya_load_view( 'directory-list.php' ); }
function mya_render_messages_list_page() { mya_load_view( 'messages-list.php' ); }
function mya_render_edit_user_page() { mya_load_view( 'edit-user-profile.php' ); }

function mya_load_view( $filename ) {

    $base = MYA_PLUGIN_DIR . 'modules/admins/views/';
    $path = $base . $filename;

    $real_base = realpath( $base );
    $real_path = realpath( $path );

    if ( $real_path && $real_base && strpos( $real_path, $real_base ) === 0 && file_exists( $real_path ) ) {
        include $real_path;
    } else {
        echo "<div class='notice notice-error'><p>View file missing: " . esc_html( $filename ) . "</p></div>";
    }
}

/* ----------------------------------------------------------------
 * Save Admin-Edited User Profile
 * ---------------------------------------------------------------- */
add_action( 'admin_post_mya_save_user_profile', 'mya_handle_save_user_profile' );

function mya_handle_save_user_profile() {

    if ( ! current_user_can( 'edit_users' ) ) {
        wp_die( 'Permission denied' );
    }

    check_admin_referer( 'mya_save_profile_action' );

    $uid = isset( $_POST['user_id'] ) ? intval( wp_unslash( $_POST['user_id'] ) ) : 0;

    if ( $uid <= 0 ) {
        wp_safe_redirect( admin_url( 'admin.php?page=listed-users&updated=0' ) );
        exit;
    }

    $fields = array( 'mya_mobile', 'mya_city', 'mya_profession', 'mya_bio', 'mya_website', 'mya_upi' );

    foreach ( $fields as $key ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_user_meta( $uid, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
        }
    }

    wp_safe_redirect( admin_url( 'admin.php?page=listed-users&updated=1' ) );
    exit;
}
