<?php
/**
 * Git Updater Install Stats uninstall.
 *
 * @package git-updater-install-stats
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}gu_install_stats" );
delete_site_option( 'gu_install_stats_db_version' );
delete_site_option( 'gu_install_stats_history' );
wp_clear_scheduled_hook( 'gu_install_stats_prune' );
