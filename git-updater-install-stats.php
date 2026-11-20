<?php
/**
 * Git Updater Install Stats
 *
 * An add-on for Git Updater by Andy Fragen: https://github.com/afragen/git-updater
 *
 * @author    helgatheviking
 * @license   GPL-3.0-or-later
 * @link      https://github.com/helgatheviking/git-updater-install-stats
 * @package   git-updater-install-stats
 */

/**
 * Plugin Name:       Git Updater Install Stats
 * Plugin URI:        https://github.com/helgatheviking/git-updater-install-stats
 * Update URI:        https://www.backcourt.io
 * Description:       An add-on for <a href="https://github.com/afragen/git-updater">Git Updater</a> by Andy Fragen. Loosely counts the sites that check this update server for each plugin or theme, and shows active installs in a dashboard widget.
 * Version:           0.1.0
 * Author:            helgatheviking
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Network:           true
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  git-updater
 * Text Domain:       git-updater-install-stats
 */

namespace HelgaTheViking\Git_Updater\Install_Stats;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add Git Updater Lite
 */
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';

	if ( class_exists( Vendor\Fragen\Git_Updater\Lite::class ) ) {
		( new Vendor\Fragen\Git_Updater\Lite( __FILE__ ) )->run();
	}
}

require_once __DIR__ . '/src/History.php';
require_once __DIR__ . '/src/Table.php';
require_once __DIR__ . '/src/Tracker.php';
require_once __DIR__ . '/src/Widget.php';

register_activation_hook( __FILE__, [ Table::class, 'install' ] );
register_deactivation_hook( __FILE__, [ Table::class, 'unschedule_prune' ] );

add_action(
	'plugins_loaded',
	static function () {
		Table::maybe_install();
		( new Tracker() )->load_hooks();
		( new Widget() )->load_hooks();
		add_action( Table::PRUNE_HOOK, [ History::class, 'snapshot' ], 5 );
		add_action( Table::PRUNE_HOOK, [ Table::class, 'prune' ] );
	}
);
