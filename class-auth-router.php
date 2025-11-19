<?php
// modules/auth/class-auth-router.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MYA_Auth_Router
 *
 * - Registers rewrite rules and query vars for auth endpoints
 * - Loads the plugin templates for login/register/forgot/reset/logout
 * - When receiving POST on those endpoints, includes the corresponding process file under modules/account/process/
 *
 * Installation:
 * - include/require this file in your module loader or module.php
 * - call MYA_Auth_Router::init();
 * - register activation/deactivation hooks to flush rules (snippets below)
 */
class MYA_Auth_Router {

    // Base slug for all auth pages
    public static $base_slug = 'account';

    // Map endpoints to template filenames and process files
    public static $routes = array(
        'register' => array(
            'template' => 'modules/auth/templates/register.php',
            'process'  => 'modules/account/process/process-register.php',
        ),
        'login' => array(
            'template' => 'modules/auth/templates/login.php',
            'process'  => 'modules/account/process/process-login.php',
        ),
        'forgot' => array(
            'template' => 'modules/auth/templates/forgot-password.php',
            'process'  => 'modules/account/process/process-forgot.php',
        ),
        'reset' => array(
            'template' => 'modules/auth/templates/change-password.php',
            'process'  => 'modules/account/process/process-reset.php',
        ),
        'logout' => array(
            // optional template (if you have one). You can keep this blank.
            'template' => '',
            'process'  => 'modules/account/process/process-logout.php',
        ),
    );

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'template_router' ), 1 );
    }

    /**
     * Add rewrite rules like:
     *  /account/login   -> index.php?mya_route=login
     */
    public static function add_rewrite_rules() {
        $base = self::$base_slug;

        // Register each rule
        foreach ( array_keys( self::$routes ) as $endpoint ) {
            $slug = trailingslashit( $base ) . $endpoint;
            add_rewrite_rule(
                '^' . preg_quote( $slug, '#' ) . '/?$',
                'index.php?mya_route=' . $endpoint,
                'top'
            );
        }
    }

    /**
     * Add query var to capture our route
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'mya_route';
        $vars[] = 'mya_msg';      // for message passthrough if you want direct query usage
        return $vars;
    }

    /**
     * Router: intercept the route and load template or process file
     */
    public static function template_router() {
        $route = get_query_var( 'mya_route', '' );
        if ( ! $route ) {
            return; // nothing to do
        }

        if ( ! array_key_exists( $route, self::$routes ) ) {
            return; // unknown route
        }

        // If POST, include the appropriate process file so it handles the request
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $process_path = self::locate_plugin_file( self::$routes[ $route ]['process'] );
            if ( $process_path && file_exists( $process_path ) ) {
                // Make sure process files see ABSPATH
                include_once $process_path;
                // Process files should exit after handling (they should redirect). If they don't, continue to rendering template.
            } else {
                // Process file missing — show an error or continue to template
                error_log( 'MYA: missing process file for route: ' . $route . ' expected at: ' . ( self::$routes[ $route ]['process'] ) );
            }
        }

        // Determine template to load (if any)
        $template_rel = self::$routes[ $route ]['template'] ?? '';
        $template_path = $template_rel ? self::locate_plugin_file( $template_rel ) : '';

        if ( $template_path && file_exists( $template_path ) ) {
            // Allow theme override: look in theme first for 'myadvisers/auth/{route}.php' or plugin template fallback
            $theme_override = locate_template( array( 'myadvisers/auth/' . $route . '.php', 'myadvisers/' . $route . '.php' ), false, false );
            if ( $theme_override ) {
                include $theme_override;
            } else {
                include $template_path;
            }
            exit;
        } else {
            // No template found: fallback to a WP page-not-found behaviour -> show 404
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            include get_query_template( '404' );
            exit;
        }
    }

    /**
     * Helper: locate plugin file path
     */
    public static function locate_plugin_file( $relative_path ) {
        // Assume this class file is in modules/auth/ so plugin root is two directories up
        $class_dir = dirname( __FILE__ ); // modules/auth
        $plugin_root = realpath( $class_dir . '/../../' );
        if ( ! $plugin_root ) {
            // Fallback to plugin_dir_path
            $plugin_root = realpath( plugin_dir_path( $class_dir ) );
        }
        $full = realpath( $plugin_root . '/' . ltrim( $relative_path, '/' ) );
        return $full ?: $plugin_root . '/' . ltrim( $relative_path, '/' );
    }

    /**
     * Activation helper: flush rewrite rules
     * Call this from plugin activation hook
     */
    public static function activate() {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Deactivation helper: flush rewrite rules
     * Call this from plugin deactivation hook
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Redirect helper to add mya_msg query param (sessionless)
     */
    public static function redirect_with_msg( $url, $message = '', $status = 302 ) {
        if ( ! $url ) $url = home_url();
        if ( $message ) {
            $url = add_query_arg( 'mya_msg', rawurlencode( $message ), $url );
        }
        wp_safe_redirect( $url, $status );
        exit;
    }

} // class

// auto-init if file is loaded directly (module loader should include file and call init)
