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
 * Class Widget
 *
 * Dashboard widget listing active installs per slug. Shown on the single-site
 * dashboard, or on multisite on the main site's dashboard and the network dashboard.
 */
class Widget {
	/**
	 * Widget ID.
	 *
	 * @var string
	 */
	private const ID = 'gu_install_stats';

	/**
	 * Load relevant action/filter hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'wp_dashboard_setup', [ $this, 'add_site_widget' ] );
		add_action( 'wp_network_dashboard_setup', [ $this, 'add_network_widget' ] );
	}

	/**
	 * Register the widget on a site dashboard.
	 *
	 * @return void
	 */
	public function add_site_widget() {
		if ( ! current_user_can( 'manage_options' ) || ( is_multisite() && ! is_main_site() ) ) {
			return;
		}
		$this->add_widget();
	}

	/**
	 * Register the widget on the network dashboard.
	 *
	 * @return void
	 */
	public function add_network_widget() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$this->add_widget();
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	private function add_widget() {
		wp_add_dashboard_widget(
			self::ID,
			esc_html__( 'Git Updater Active Installs', 'git-updater-install-stats' ),
			[ $this, 'render' ]
		);
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	public function render() {
		$counts = Table::get_active_counts();
		$days   = (int) ( Table::ACTIVE_WINDOW / DAY_IN_SECONDS );

		echo '<p class="description">';
		printf(
			/* translators: %d: number of days */
			esc_html__( 'Unique sites that checked this server for updates in the last %d days. Counts are approximate.', 'git-updater-install-stats' ),
			(int) $days
		);
		echo '</p>';

		if ( empty( $counts ) ) {
			echo '<p>' . esc_html__( 'No update checks recorded yet.', 'git-updater-install-stats' ) . '</p>';
			return;
		}

		$history = History::get();

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Slug', 'git-updater-install-stats' ) . '</th>';
		echo '<th>' . esc_html__( 'Trend', 'git-updater-install-stats' ) . '</th>';
		echo '<th style="text-align:right">' . esc_html__( 'Active installs', 'git-updater-install-stats' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $counts as $slug => $installs ) {
			echo '<tr><td><code>' . esc_html( $slug ) . '</code></td>';
			echo '<td>' . $this->sparkline( $history[ $slug ] ?? [] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in sparkline().
			echo '<td style="text-align:right">' . esc_html( number_format_i18n( $installs ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Build an inline SVG trend line from daily snapshots.
	 *
	 * Points are placed by date, so a missed cron day leaves a gap in spacing
	 * rather than squashing the line. The y axis spans the slug's min to max.
	 *
	 * @param array<string, int> $days Installs keyed by Y-m-d, ascending.
	 *
	 * @return string Escaped markup, or a dash if there are fewer than two snapshots.
	 */
	private function sparkline( $days ) {
		if ( count( $days ) < 2 ) {
			return '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'Not enough history yet', 'git-updater-install-stats' ) . '</span>';
		}

		$width  = 100;
		$height = 24;
		$pad    = 2;

		$first = strtotime( array_key_first( $days ) . ' UTC' );
		$last  = strtotime( array_key_last( $days ) . ' UTC' );
		$span  = max( 1, $last - $first );
		$min   = min( $days );
		$max   = max( $days );

		$points = [];
		foreach ( $days as $date => $installs ) {
			$x        = $pad + ( strtotime( $date . ' UTC' ) - $first ) / $span * ( $width - 2 * $pad );
			$y        = $max === $min ? $height / 2 : $height - $pad - ( $installs - $min ) / ( $max - $min ) * ( $height - 2 * $pad );
			$points[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$label = sprintf(
			/* translators: 1: start date, 2: end date, 3: lowest count, 4: highest count */
			__( '%1$s to %2$s: %3$s to %4$s active installs', 'git-updater-install-stats' ),
			wp_date( get_option( 'date_format' ), $first, new \DateTimeZone( 'UTC' ) ),
			wp_date( get_option( 'date_format' ), $last, new \DateTimeZone( 'UTC' ) ),
			number_format_i18n( $min ),
			number_format_i18n( $max )
		);

		return sprintf(
			'<svg width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s"><title>%3$s</title><polyline points="%4$s" fill="none" stroke="#2271b1" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" /></svg>',
			$width,
			$height,
			esc_attr( $label ),
			esc_attr( implode( ' ', $points ) )
		);
	}
}
