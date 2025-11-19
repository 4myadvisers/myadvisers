<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MyA_Module_Auth {
    public function register() {
        // public shortcodes
        require_once MYADVISERS_DIR . 'modules/auth/public.php';
        MyA_Auth_Public::init();

        // admin hooks for forgot password approval (store requests / notify admin)
        add_action( 'admin_post_myadvisers_forgot_approve', array( $this, 'handle_admin_approve' ) );
    }

    public function handle_admin_approve() {
        // implement admin approval flow in future; placeholder
    }
}
