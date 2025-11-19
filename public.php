<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MyA_Auth_Public {
    public static function init() {
        add_shortcode( 'myadvisers_register', array( __CLASS__, 'render_register' ) );
        add_shortcode( 'myadvisers_login', array( __CLASS__, 'render_login' ) );
        add_shortcode( 'myadvisers_forgot', array( __CLASS__, 'render_forgot' ) );

        add_action( 'admin_post_nopriv_mya_register', array( __CLASS__, 'handle_register' ) );
        add_action( 'admin_post_mya_register', array( __CLASS__, 'handle_register' ) );

        add_action( 'admin_post_nopriv_mya_login', array( __CLASS__, 'handle_login' ) );
        add_action( 'admin_post_mya_login', array( __CLASS__, 'handle_login' ) );

        add_action( 'admin_post_nopriv_mya_forgot', array( __CLASS__, 'handle_forgot' ) );
        add_action( 'admin_post_mya_forgot', array( __CLASS__, 'handle_forgot' ) );

        add_action( 'wp_logout', array( __CLASS__, 'on_logout' ) );
    }

    public static function render_register( $atts = array() ) {
        ob_start();
        include MYADVISERS_DIR . 'modules/auth/templates/form-register.php';
        return ob_get_clean();
    }

    public static function render_login( $atts = array() ) {
        ob_start();
        include MYADVISERS_DIR . 'modules/auth/templates/form-login.php';
        return ob_get_clean();
    }

    public static function render_forgot( $atts = array() ) {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <?php wp_nonce_field('mya_forgot_nonce','mya_forgot_nonce'); ?>
            <input type="hidden" name="action" value="mya_forgot" />
            <p><label>Username or Email<br><input type="text" name="user" required></label></p>
            <p><input type="submit" value="Request Password Change"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_register() {
        if ( ! isset( $_POST['mya_register_nonce'] ) || ! wp_verify_nonce( $_POST['mya_register_nonce'], 'mya_register_nonce' ) ) {
            wp_die('Invalid request');
        }
        $username = sanitize_user( $_POST['username'] );
        $email    = sanitize_email( $_POST['email'] );
        $mobile   = sanitize_text_field( $_POST['mobile'] );
        $pass     = $_POST['password'];
        $pass2    = $_POST['confirm_password'];

        if ( empty( $username ) || empty( $email ) || empty( $pass ) ) {
            wp_die('Please fill required fields');
        }
        if ( $pass !== $pass2 ) wp_die('Passwords do not match');

        if ( username_exists( $username ) || email_exists( $email ) ) {
            wp_die('User or email already exists');
        }

        $user_id = wp_create_user( $username, $pass, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_die( $user_id->get_error_message() );
        }

        // Save mobile as user meta
        update_user_meta( $user_id, 'mya_mobile', $mobile );

        // redirect to login or dashboard
        wp_redirect( home_url() );
        exit;
    }

    public static function handle_login() {
        if ( ! isset( $_POST['mya_login_nonce'] ) || ! wp_verify_nonce( $_POST['mya_login_nonce'], 'mya_login_nonce' ) ) {
            wp_die('Invalid request');
        }
        $user     = sanitize_text_field( $_POST['user'] );
        $password = $_POST['password'];

        // Allow login by email or username
        if ( is_email( $user ) ) {
            $user_obj = get_user_by( 'email', $user );
            if ( $user_obj ) $user = $user_obj->user_login;
        }

        $creds = array(
            'user_login'    => $user,
            'user_password' => $password,
            'remember'      => isset( $_POST['remember'] ),
        );
        $signin = wp_signon( $creds, is_ssl() );
        if ( is_wp_error( $signin ) ) {
            wp_die( $signin->get_error_message() );
        }

        wp_redirect( home_url() );
        exit;
    }

    public static function handle_forgot() {
        if ( ! isset( $_POST['mya_forgot_nonce'] ) || ! wp_verify_nonce( $_POST['mya_forgot_nonce'], 'mya_forgot_nonce' ) ) {
            wp_die('Invalid request');
        }
        $user = sanitize_text_field( $_POST['user'] );
        $userdata = null;
        if ( is_email( $user ) ) $userdata = get_user_by( 'email', $user );
        if ( ! $userdata ) $userdata = get_user_by( 'login', $user );
        if ( ! $userdata ) wp_die('No user found');

        // Store request in option (simple queue). Module admin will approve and process.
        $requests = get_option( 'mya_password_requests', array() );
        $requests[] = array(
            'user_id' => $userdata->ID,
            'new_pass' => wp_generate_password( 12 ), // placeholder; or capture desired pass
            'requested_at' => current_time( 'mysql' ),
            'status' => 'pending',
        );
        update_option( 'mya_password_requests', $requests );

        // notify admin
        $admin_email = get_option( 'admin_email' );
        wp_mail( $admin_email, 'Password change request', 'A password change request is waiting in MyAdvisers admin.' );

        wp_redirect( home_url() );
        exit;
    }

    public static function on_logout() {
        // clean up or redirect if needed
    }
}
