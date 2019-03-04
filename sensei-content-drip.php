<?php
/*
 * Plugin Name: Sensei Content Drip
 * Version: 1.0.9
 * Plugin URI: https://woocommerce.com/products/sensei-content-drip/
 * Description:  Control access to Sensei lessons by scheduling them to become available after a determined time.
 * Author: Automattic
 * Author URI: https://automattic.com/
 * Requires at least: 3.9
 * Tested up to: 4.7.2
 * Domain path: /lang/
 * Woo: 543363:8ee2cdf89f55727f57733133ccbbfbb0
 *
 * @package WordPress
 * @author Automattic
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-sdc-ext-dependency-checker.php';

if ( ! Scd_Ext_Dependency_Checker::are_dependencies_met() ) {
	return;
}

require_once __DIR__ . '/includes/class-sensei-content-drip.php';

/**
 * Returns the main instance of Sensei_Content_Drip to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Sensei_Content_Drip
 */
function Sensei_Content_Drip() {
	return Sensei_Content_Drip::instance( __FILE__, '1.0.9' );
}

// load this plugin only after sensei becomes available globaly
add_action( 'plugins_loaded', 'Sensei_Content_Drip' ) ;

/**
 * Plugin Activation
 */
register_activation_hook( __FILE__, 'sensei_content_drip_activation' );

function sensei_content_drip_activation() {
	$hook = 'woo_scd_daily_cron_hook';

	if ( false !== wp_next_scheduled( $hook ) ) {
		wp_clear_scheduled_hook( $hook );
	}

	wp_schedule_event( time(), 'daily', $hook );
}


/**
 * Plugin Deactivation
 */
register_deactivation_hook( __FILE__, 'sensei_content_drip_deactivation' );

function sensei_content_drip_deactivation() {
	$hook = 'woo_scd_daily_cron_hook';
	wp_clear_scheduled_hook( $hook );

}

