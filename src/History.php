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
 * Class History
 *
 * Keeps a daily snapshot of each slug's active install count in a site option,
 * for the widget's trend lines.
 *
 * Shape: [ <slug> => [ <Y-m-d> => <installs> ] ], dates ascending.
 */
class History {
	/**
	 * Site option holding the snapshots.
	 *
	 * @var string
	 */
	public const OPTION = 'gu_install_stats_history';

	/**
	 * Days of snapshots to keep.
	 *
	 * @var int
	 */
	public const KEEP_DAYS = 90;

	/**
	 * Save today's active install counts and drop old snapshots.
	 *
	 * Slugs with history but no active installs today get a 0, so their line
	 * drops; a slug is removed once all of its kept snapshots are 0.
	 *
	 * @return void
	 */
	public static function snapshot() {
		$history = self::get();
		$counts  = Table::get_active_counts();
		$today   = gmdate( 'Y-m-d' );
		$cutoff  = gmdate( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS );

		foreach ( array_unique( array_merge( array_keys( $history ), array_keys( $counts ) ) ) as $slug ) {
			$days           = $history[ $slug ] ?? [];
			$days[ $today ] = $counts[ $slug ] ?? 0;

			$days = array_filter(
				$days,
				static function ( $date ) use ( $cutoff ) {
					return $date > $cutoff;
				},
				ARRAY_FILTER_USE_KEY
			);
			ksort( $days );

			if ( 0 === array_sum( $days ) ) {
				unset( $history[ $slug ] );
				continue;
			}
			$history[ $slug ] = $days;
		}

		update_site_option( self::OPTION, $history );
	}

	/**
	 * Get all snapshots.
	 *
	 * @return array<string, array<string, int>>
	 */
	public static function get() {
		return (array) get_site_option( self::OPTION, [] );
	}
}
