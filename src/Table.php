<?php
/**
 * Git Updater Install Stats
 *
 * @author    helgatheviking
 * @license   GPL-3.0-or-later
 * @link      https://github.com/helgatheviking/git-updater-install-stats
 * @package   git-updater-install-stats
 */

namespace HelgaTheViking\Git_Updater\Install_Stats;

/**
 * Class Table
 *
 * Owns the network-wide `gu_install_stats` table: one row per (slug, site hash)
 * with the time that site last checked for an update.
 */
class Table {
	/**
	 * Schema version; bump to re-run dbDelta().
	 *
	 * @var string
	 */
	public const DB_VERSION = '1';

	/**
	 * Site option holding the installed schema version.
	 *
	 * @var string
	 */
	public const DB_VERSION_OPTION = 'gu_install_stats_db_version';

	/**
	 * Cron hook that prunes stale rows.
	 *
	 * @var string
	 */
	public const PRUNE_HOOK = 'gu_install_stats_prune';

	/**
	 * A site counts as an active install if it checked within this many seconds.
	 *
	 * @var int
	 */
	public const ACTIVE_WINDOW = 30 * DAY_IN_SECONDS;

	/**
	 * Get the table name. Uses the base prefix so the table is shared across a network.
	 *
	 * @return string
	 */
	public static function name() {
		global $wpdb;

		return $wpdb->base_prefix . 'gu_install_stats';
	}

	/**
	 * Create or upgrade the table if the stored schema version is out of date.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION !== get_site_option( self::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Create the table and schedule pruning.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::name();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				slug varchar(191) NOT NULL,
				site_hash char(64) NOT NULL,
				last_seen int(10) unsigned NOT NULL,
				PRIMARY KEY  (slug,site_hash),
				KEY last_seen (last_seen)
			) {$charset};"
		);

		update_site_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Clear the prune event.
	 *
	 * @return void
	 */
	public static function unschedule_prune() {
		wp_clear_scheduled_hook( self::PRUNE_HOOK );
	}

	/**
	 * Record that a site checked for an update to a slug.
	 *
	 * @param string $slug      Plugin or theme slug.
	 * @param string $site_hash Hashed site identifier.
	 *
	 * @return void
	 */
	public static function record( $slug, $site_hash ) {
		global $wpdb;

		$table = self::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT INTO {$table} (slug, site_hash, last_seen) VALUES (%s, %s, %d)
				ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)",
				$slug,
				$site_hash,
				time()
			)
		);
	}

	/**
	 * Get active install counts per slug, highest first.
	 *
	 * @return array<string, int>
	 */
	public static function get_active_counts() {
		global $wpdb;

		$table = self::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT slug, COUNT(*) AS installs FROM {$table} WHERE last_seen >= %d GROUP BY slug ORDER BY installs DESC, slug ASC",
				time() - self::ACTIVE_WINDOW
			)
		);

		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ $row->slug ] = (int) $row->installs;
		}

		return $counts;
	}

	/**
	 * Delete rows for sites that have not checked within the active window.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;

		$table = self::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE last_seen < %d",
				time() - self::ACTIVE_WINDOW
			)
		);
	}
}
