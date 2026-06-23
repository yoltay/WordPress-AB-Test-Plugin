<?php
/**
 * Plugin Name: A/B Test int
 * Description: Elementor ile tasarlanmış sayfalar için basit A/B test eklentisi. Sayfada varyasyonları CSS ID/Class ile işaretleyip ölçümlersiniz.
 * Version: 1.2.0
 * Author: Sedat Y
 * Text Domain: ab-test-int
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ABTI_VERSION', '1.1.1' );
define( 'ABTI_FILE', __FILE__ );
define( 'ABTI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABTI_URL', plugin_dir_url( __FILE__ ) );
define( 'ABTI_BASENAME', plugin_basename( __FILE__ ) );

require_once ABTI_DIR . 'includes/class-abti-database.php';
require_once ABTI_DIR . 'includes/class-abti-rest.php';
require_once ABTI_DIR . 'includes/class-abti-frontend.php';
require_once ABTI_DIR . 'includes/class-abti-admin.php';

register_activation_hook( __FILE__, array( 'ABTI_Database', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ABTI_Database', 'deactivate' ) );

/**
 * Uninstall: tabloları sil.
 */
function abti_uninstall_cleanup() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    ABTI_Database::uninstall();
}
register_uninstall_hook( __FILE__, 'abti_uninstall_cleanup' );

/**
 * Bootstrap.
 */
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'ab-test-int', false, dirname( ABTI_BASENAME ) . '/languages' );

    new ABTI_REST();
    new ABTI_Frontend();
    if ( is_admin() ) {
        new ABTI_Admin();
    }
} );

/**
 * "Settings" linkini eklenti listesinde göster.
 */
add_filter( 'plugin_action_links_' . ABTI_BASENAME, function ( $links ) {
    $url      = admin_url( 'admin.php?page=abti-tests' );
    $settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Testler', 'ab-test-int' ) . '</a>';
    array_unshift( $links, $settings );
    return $links;
} );
