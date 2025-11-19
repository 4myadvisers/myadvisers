<?php
// modules/auth/class-auth-process.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYA_Auth_Process {

    public static function init() {
        // AJAX handlers (both logged-in and non-logged-in where appropriate)
        add_action( 'wp_ajax_nopriv_mya_register', array( __CLASS__, 'ajax_register' ) );
        add_action( 'wp_ajax_mya_register', array( __CLASS__, 'ajax_register' ) );

        add_action( 'wp_ajax_nopriv_mya_login', array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_mya_login', array( __CLASS__, 'ajax_login' ) );

        add_action( 'wp_ajax_nopriv_mya_forgot', array( __CLASS__, 'ajax_forgot' ) );
        add_action( 'wp_ajax_mya_forgot', array( __CLASS__, 'ajax_forgot' ) );

        add_action( 'wp_ajax_nopriv_mya_reset', array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_mya_reset', array( __CLASS__, 'ajax_reset' ) );

        add_action( 'wp_ajax_nopriv_mya_logout', array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_mya_logout', array( __CLASS__, 'ajax_logout' ) );
    }

    /**
     * Helper: safe redirect with message (for non-AJAX fallback)
     */
    private static function redirect_with_msg( $url, $msg, $type = 'success' ) {
        $url = add_query_arg( 'mya_msg', urlencode( $msg ), $url );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * AJAX: Register
     * Expects:
     *  - nonce field 'ma_register_nonce' with action 'ma_user_register'
     *  - form fields: ma_username, ma_email, ma_mobile, ma_password, ma_confirm_password, ma_redirect_to (optional)
     */
    public static function ajax_register() {
        // Only accept POST
        if ( empty( $_POST ) ) {
            wp_send_json_error( array( 'msg' => 'Invalid request.' ), 400 );
        }

        // Verify nonce (templates use ma_register_nonce)
        $nonce = sanitize_text_field( wp_unslash( $_POST['ma_register_nonce'] ?? '' ) );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ma_user_register' ) ) {
            wp_send_json_error( array( 'msg' => 'Security check failed.' ), 403 );
        }

        $username = sanitize_user( wp_unslash( $_POST['ma_username'] ?? '' ), true );
        $email    = sanitize_email( wp_unslash( $_POST['ma_email'] ?? '' ) );
        $mobile   = sanitize_text_field( wp_unslash( $_POST['ma_mobile'] ?? '' ) );
        $password = wp_unslash( $_POST['ma_password'] ?? '' );
        $confirm  = wp_unslash( $_POST['ma_confirm_password'] ?? '' );
        $redirect = isset( $_POST['ma_redirect_to'] ) ? wp_validate_redirect( $_POST['ma_redirect_to'], home_url() ) : '';

        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            wp_send_json_error( array( 'msg' => 'Please fill all required fields.' ), 400 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'msg' => 'Please provide a valid email address.' ), 400 );
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            wp_send_json_error( array( 'msg' => 'Username or email already exists.' ), 409 );
        }

        if ( $password !== $confirm ) {
            wp_send_json_error( array( 'msg' => 'Passwords do not match.' ), 400 );
        }

        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => get_option( 'default_role', 'subscriber' ),
        );

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'msg' => $user_id->get_error_message() ?: 'Registration failed.' ), 500 );
        }

        // Optional: store mobile as user meta if provided
        if ( $mobile ) {
            update_user_meta( $user_id, 'mp_mobile', $mobile );
        }

        // Notify user (core)
        if ( function_exists( 'wp_new_user_notification' ) ) {
            try {
                wp_new_user_notification( $user_id, null, 'user' );
            } catch ( Exception $e ) {
                // Non-fatal
            }
        }

        // Auto login
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        $response = array(
            'msg' => 'Registration successful.',
            'redirect' => $redirect ?: site_url( '/dashboard' ),
        );
        wp_send_json_success( $response );
    }

    /**
     * AJAX: Login
     * Expects:
     *  - nonce 'ma_login_nonce' (action 'ma_user_login')
     *  - form fields: ma_login_user, ma_login_pass, rememberme, ma_redirect_to (optional)
     */
    public static function ajax_login() {
        if ( empty( $_POST ) ) {
            wp_send_json_error( array( 'msg' => 'Invalid request.' ), 400 );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['ma_login_nonce'] ?? '' ) );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ma_user_login' ) ) {
            wp_send_json_error( array( 'msg' => 'Security check failed.' ), 403 );
        }

        $identifier = sanitize_text_field( wp_unslash( $_POST['ma_login_user'] ?? '' ) );
        $password   = wp_unslash( $_POST['ma_login_pass'] ?? '' );
        $remember   = ! empty( $_POST['rememberme'] ) ? true : false;
        $redirect   = isset( $_POST['ma_redirect_to'] ) ? wp_validate_redirect( $_POST['ma_redirect_to'], home_url() ) : '';

        if ( empty( $identifier ) || empty( $password ) ) {
            wp_send_json_error( array( 'msg' => 'Please provide credentials.' ), 400 );
        }

        // allow email or username
        if ( is_email( $identifier ) ) {
            $user = get_user_by( 'email', $identifier );
            if ( $user ) $identifier = $user->user_login;
        }

        $creds = array(
            'user_login'    => $identifier,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $secure_cookie = is_ssl();
        $user = wp_signon( $creds, $secure_cookie );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'msg' => 'Login failed. Please check your credentials.' ), 401 );
        }

        $response = array(
            'msg' => 'Login successful.',
            'redirect' => $redirect ?: site_url( '/dashboard' ),
        );
        wp_send_json_success( $response );
    }

    /**
     * AJAX: Forgot password (send reset email)
     * Expects:
     *  - nonce 'ma_forgot_nonce' (action 'ma_forgot_password')
     *  - form field: ma_forgot_user (username or email), ma_redirect_to (optional)
     */
    public static function ajax_forgot() {
        if ( empty( $_POST ) ) {
            wp_send_json_error( array( 'msg' => 'Invalid request.' ), 400 );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['ma_forgot_nonce'] ?? '' ) );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ma_forgot_password' ) ) {
            wp_send_json_error( array( 'msg' => 'Security check failed.' ), 403 );
        }

        $identifier = sanitize_text_field( wp_unslash( $_POST['ma_forgot_user'] ?? '' ) );
        $redirect   = isset( $_POST['ma_redirect_to'] ) ? wp_validate_redirect( $_POST['ma_redirect_to'], home_url() ) : '';

        if ( empty( $identifier ) ) {
            wp_send_json_error( array( 'msg' => 'Please enter username or email.' ), 400 );
        }

        // Find user
        if ( is_email( $identifier ) ) {
            $user = get_user_by( 'email', $identifier );
        } else {
            $user = get_user_by( 'login', $identifier );
        }

        // Always return generic success message to avoid enumeration
        $generic = 'If an account exists for that address/username, a password reset email has been sent.';

        if ( ! $user ) {
            wp_send_json_success( array( 'msg' => $generic ) );
        }

        $retrieve = retrieve_password( $user->user_login );
        if ( is_wp_error( $retrieve ) ) {
            wp_send_json_error( array( 'msg' => $retrieve->get_error_message() ?: 'Unable to send reset email.' ), 500 );
        }

        wp_send_json_success( array( 'msg' => $generic, 'redirect' => $redirect ) );
    }

    /**
     * AJAX: Reset password (from link form)
     * Expects:
     *  - nonce 'ma_change_nonce' (action 'ma_change_password')
     *  - form fields: key, login, ma_new_password, ma_confirm_new_password, ma_redirect_to (optional)
     */
    public static function ajax_reset() {
        if ( empty( $_POST ) ) {
            wp_send_json_error( array( 'msg' => 'Invalid request.' ), 400 );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['ma_change_nonce'] ?? '' ) );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ma_change_password' ) ) {
            wp_send_json_error( array( 'msg' => 'Security check failed.' ), 403 );
        }

        $key    = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
        $login  = sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) );
        $pass   = wp_unslash( $_POST['ma_new_password'] ?? '' );
        $pass2  = wp_unslash( $_POST['ma_confirm_new_password'] ?? '' );
        $redirect = isset( $_POST['ma_redirect_to'] ) ? wp_validate_redirect( $_POST['ma_redirect_to'], home_url() ) : '';

        if ( empty( $key ) || empty( $login ) ) {
            wp_send_json_error( array( 'msg' => 'Invalid password reset request.' ), 400 );
        }

        if ( empty( $pass ) || empty( $pass2 ) ) {
            wp_send_json_error( array( 'msg' => 'Please enter and confirm your new password.' ), 400 );
        }

        if ( $pass !== $pass2 ) {
            wp_send_json_error( array( 'msg' => 'Passwords do not match.' ), 400 );
        }

        $check = check_password_reset_key( $key, $login );
        if ( is_wp_error( $check ) ) {
            wp_send_json_error( array( 'msg' => $check->get_error_message() ?: 'Invalid or expired key.' ), 400 );
        }

        // Normalize to WP_User
        if ( $check instanceof WP_User ) {
            $user = $check;
        } else {
            $user = get_user_by( 'login', $login );
            if ( ! $user ) {
                wp_send_json_error( array( 'msg' => 'Unable to find user.' ), 404 );
            }
        }

        try {
            reset_password( $user, $pass );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'msg' => 'Unable to reset password.' ), 500 );
        }

        // Log user in
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        wp_send_json_success( array( 'msg' => 'Password reset successful.', 'redirect' => $redirect ?: site_url( '/dashboard' ) ) );
    }

    /**
     * AJAX: Logout
     * Expects nonce 'ma_logout_nonce' (action 'ma_logout_action') OR referer allowed
     */
    public static function ajax_logout() {
        // Accept POST
        $nonce = sanitize_text_field( wp_unslash( $_POST['ma_logout_nonce'] ?? '' ) );
        $valid = false;
        if ( $nonce && wp_verify_nonce( $nonce, 'ma_logout_action' ) ) {
            $valid = true;
        } else {
            // fallback: same-host referer
            $referer = wp_get_referer();
            $valid = ( $referer && parse_url( $referer, PHP_URL_HOST ) === parse_url( home_url(), PHP_URL_HOST ) );
        }

        if ( ! $valid ) {
            wp_send_json_error( array( 'msg' => 'Invalid logout request.' ), 403 );
        }

        wp_logout();
        wp_send_json_success( array( 'msg' => 'Logged out.', 'redirect' => home_url() ) );
    }
}

// initialize hooks
MYA_Auth_Process::init();
