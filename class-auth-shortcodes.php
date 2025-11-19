<?php
/**
 * class-auth-shortcodes.php
 * Path: modules/auth/class-auth-shortcodes.php
 *
 * Registers shortcodes for auth templates and renders templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYA_Auth_Shortcodes {

    public static function init() {
        add_shortcode( 'mya_register', [ __CLASS__, 'render_register' ] );
        add_shortcode( 'mya_login', [ __CLASS__, 'render_login' ] );
        add_shortcode( 'mya_forgot_password', [ __CLASS__, 'render_forgot' ] );
        add_shortcode( 'mya_change_password', [ __CLASS__, 'render_change' ] );
        add_shortcode( 'mya_logout', [ __CLASS__, 'render_logout' ] );
    }

    private static function template_path( $file ) {
        return MYA_PLUGIN_DIR . 'modules/auth/templates/' . $file;
    }

    private static function render_template( $file, $vars = [] ) {
        $path = self::template_path( $file );
        if ( ! file_exists( $path ) ) {
            return '<div class="notice notice-error">Template missing: ' . esc_html( $file ) . '</div>';
        }
        ob_start();
        extract( $vars );
        include $path;
        return ob_get_clean();
    }

    public static function render_register() {
        if ( is_user_logged_in() ) {
            return '<p>You are already logged in.</p>';
        }
        return self::render_template( 'register.php', [
            'form_action' => esc_url( admin_url( 'admin-post.php' ) ),
            'action_name' => 'mya_register',
            'nonce_field' => wp_create_nonce( 'mya_register_nonce' ),
        ]);
    }

    public static function render_login() {
        if ( is_user_logged_in() ) {
            return '<p>You are already logged in.</p>';
        }
        return self::render_template( 'login.php', [
            'form_action' => esc_url( admin_url( 'admin-post.php' ) ),
            'action_name' => 'mya_login',
            'nonce_field' => wp_create_nonce( 'mya_login_nonce' ),
        ]);
    }

    public static function render_forgot() {
        if ( is_user_logged_in() ) {
            return '<p>You are already logged in.</p>';
        }
        return self::render_template( 'forgot-password.php', [
            'form_action' => esc_url( admin_url( 'admin-post.php' ) ),
            'action_name' => 'mya_forgot_password',
            'nonce_field' => wp_create_nonce( 'mya_forgot_password_nonce' ),
        ]);
    }

    public static function render_change() {
        if ( ! is_user_logged_in() ) {
            return '<p>Please login to change your password.</p>';
        }
        return self::render_template( 'change-password.php', [
            'form_action' => esc_url( admin_url( 'admin-post.php' ) ),
            'action_name' => 'mya_change_password',
            'nonce_field' => wp_create_nonce( 'mya_change_password_nonce' ),
        ]);
    }

    public static function render_logout() {
        if ( is_user_logged_in() ) {
            wp_logout();
            wp_safe_redirect( home_url() );
            exit;
        }
        return '<p>You are logged out.</p>';
    }
}

MYA_Auth_Shortcodes::init();
