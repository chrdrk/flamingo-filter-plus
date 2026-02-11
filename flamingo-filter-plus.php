<?php
/**
 * Plugin Name:       Flamingo Filter Plus
 * Plugin URI:        https://github.com/chrdrk/flamingo-filter-plus
 * Description:       Adds TLD and email domain filter dropdowns to Flamingo's Inbound Messages and Address Book admin pages, to facilitate bulk operations.
 * Version:           1.0.0
 * Author:            Chrdrk & Claude
 * Author URI:        https://github.com/chrdrk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flamingo-filter-plus
 * Domain Path:       /languages
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  flamingo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FMC_VERSION', '1.0.0' );

/**
 * Show admin notice and deactivate if Flamingo is not active.
 */
add_action( 'admin_init', function () {
	if ( defined( 'FLAMINGO_VERSION' ) ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Flamingo Filter Plus requires the Flamingo plugin to be installed and active.', 'flamingo-filter-plus' );
		echo '</p></div>';
	} );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
} );

/**
 * Load plugin text domain for translations.
 */
add_action( 'init', function () {
	load_plugin_textdomain(
		'flamingo-filter-plus',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
} );

/**
 * Get TLD counts (e.g. com, ru, it) from email addresses.
 *
 * @param string $post_type   'flamingo_inbound' or 'flamingo_contact'.
 * @param string $meta_key    '_from_email' or '_email'.
 * @param string $post_status Post status to filter by (e.g. 'publish', 'flamingo-spam', 'trash'). Empty = all except trash.
 */
function fmc_get_tld_counts( $post_type, $meta_key, $post_status = '' ) {
	$cache_key = 'fmc_tld_' . $post_type . '_' . $meta_key . '_' . ( $post_status ?: 'all' );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;

	if ( $post_status ) {
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUBSTRING_INDEX(pm.meta_value, '.', -1) AS tld, COUNT(*) AS cnt
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type = %s
			  AND p.post_status = %s
			  AND pm.meta_value LIKE '%%@%%.%%'
			GROUP BY tld
			ORDER BY cnt DESC
			LIMIT 500",
			$meta_key,
			$post_type,
			$post_status
		), ARRAY_A );
	} else {
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUBSTRING_INDEX(pm.meta_value, '.', -1) AS tld, COUNT(*) AS cnt
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type = %s
			  AND p.post_status != 'trash'
			  AND pm.meta_value LIKE '%%@%%.%%'
			GROUP BY tld
			ORDER BY cnt DESC
			LIMIT 500",
			$meta_key,
			$post_type
		), ARRAY_A );
	}

	$counts = array();

	foreach ( $results as $row ) {
		$counts[ strtolower( $row['tld'] ) ] = (int) $row['cnt'];
	}

	set_transient( $cache_key, $counts, HOUR_IN_SECONDS );

	return $counts;
}

/**
 * Get domain counts (e.g. gmail.com, yahoo.it) from email addresses.
 *
 * @param string $post_type   'flamingo_inbound' or 'flamingo_contact'.
 * @param string $meta_key    '_from_email' or '_email'.
 * @param string $post_status Post status to filter by. Empty = all except trash.
 */
function fmc_get_domain_counts( $post_type, $meta_key, $post_status = '' ) {
	$cache_key = 'fmc_dom_' . $post_type . '_' . $meta_key . '_' . ( $post_status ?: 'all' );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;

	if ( $post_status ) {
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUBSTRING_INDEX(pm.meta_value, '@', -1) AS domain, COUNT(*) AS cnt
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type = %s
			  AND p.post_status = %s
			  AND pm.meta_value LIKE '%%@%%'
			GROUP BY domain
			ORDER BY cnt DESC
			LIMIT 500",
			$meta_key,
			$post_type,
			$post_status
		), ARRAY_A );
	} else {
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT SUBSTRING_INDEX(pm.meta_value, '@', -1) AS domain, COUNT(*) AS cnt
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			  AND p.post_type = %s
			  AND p.post_status != 'trash'
			  AND pm.meta_value LIKE '%%@%%'
			GROUP BY domain
			ORDER BY cnt DESC
			LIMIT 500",
			$meta_key,
			$post_type
		), ARRAY_A );
	}

	$counts = array();

	foreach ( $results as $row ) {
		$counts[ strtolower( $row['domain'] ) ] = (int) $row['cnt'];
	}

	set_transient( $cache_key, $counts, HOUR_IN_SECONDS );

	return $counts;
}

/**
 * Invalidate TLD/domain count caches when a Flamingo post is saved or deleted.
 *
 * Uses a static flag to debounce: only runs once per request even during bulk operations.
 * Scoped to Flamingo post types only when called via delete_post.
 *
 * @param int $post_id Post ID being saved or deleted.
 */
function fmc_invalidate_cache( $post_id = 0 ) {
	static $invalidated = false;

	if ( $invalidated ) {
		return;
	}

	// When called from delete_post, check that it is a Flamingo post type.
	if ( $post_id && doing_action( 'delete_post' ) ) {
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( 'flamingo_inbound', 'flamingo_contact' ), true ) ) {
			return;
		}
	}

	$invalidated = true;

	global $wpdb;

	// Delete all plugin transients (keys prefixed with fmc_tld_ or fmc_dom_).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fmc_tld_%'
		   OR option_name LIKE '_transient_timeout_fmc_tld_%'
		   OR option_name LIKE '_transient_fmc_dom_%'
		   OR option_name LIKE '_transient_timeout_fmc_dom_%'"
	);
}

add_action( 'save_post_flamingo_inbound', 'fmc_invalidate_cache' );
add_action( 'save_post_flamingo_contact', 'fmc_invalidate_cache' );
add_action( 'delete_post', 'fmc_invalidate_cache' );

/**
 * Enqueue the admin JS on relevant Flamingo pages.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function fmc_enqueue_admin_scripts( $hook_suffix ) {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$valid_screens = array( 'flamingo_page_flamingo_inbound', 'toplevel_page_flamingo' );

	if ( ! in_array( $screen->id, $valid_screens, true ) ) {
		return;
	}

	wp_enqueue_script(
		'fmc-admin',
		plugins_url( 'assets/js/fmc-admin.js', __FILE__ ),
		array(),
		FMC_VERSION,
		true
	);
}

add_action( 'admin_enqueue_scripts', 'fmc_enqueue_admin_scripts' );

/**
 * Render TLD and domain filter dropdowns and pass data to the enqueued JS.
 *
 * @param array  $tlds             Associative array tld => count.
 * @param array  $domains          Associative array domain => count.
 * @param string $selected_tld     Currently selected TLD.
 * @param string $selected_domain  Currently selected domain.
 * @param string $suffix           Unique suffix for DOM element IDs.
 */
function fmc_render_filter_dropdowns( $tlds, $domains, $selected_tld, $selected_domain, $suffix ) {
	wp_localize_script( 'fmc-admin', 'fmcFilterData', array(
		'suffix'      => $suffix,
		'filterLabel' => __( 'Filter', 'flamingo-filter-plus' ),
	) );
	?>
	<div id="fmc-filters-source-<?php echo esc_attr( $suffix ); ?>" style="display:none;">
		<select name="fmc_tld" id="fmc-tld-filter-<?php echo esc_attr( $suffix ); ?>">
			<option value=""><?php esc_html_e( 'All TLDs', 'flamingo-filter-plus' ); ?></option>
			<?php foreach ( $tlds as $tld => $count ) : ?>
				<option value="<?php echo esc_attr( $tld ); ?>" <?php selected( $selected_tld, $tld ); ?>>
					.<?php echo esc_html( $tld ); ?> (<?php echo esc_html( number_format_i18n( $count ) ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
		<select name="fmc_domain" id="fmc-domain-filter-<?php echo esc_attr( $suffix ); ?>">
			<option value=""><?php esc_html_e( 'All domains', 'flamingo-filter-plus' ); ?></option>
			<?php foreach ( $domains as $domain => $count ) : ?>
				<option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $selected_domain, $domain ); ?>>
					<?php echo esc_html( $domain ); ?> (<?php echo esc_html( number_format_i18n( $count ) ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
}

/**
 * Inject filter dropdowns on the Inbound Messages page.
 */
function fmc_inject_inbound_filters() {
	$screen = get_current_screen();

	if ( ! $screen || 'flamingo_page_flamingo_inbound' !== $screen->id ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {
		return;
	}

	// Determine post_status from the current view (Inbox, Spam, Trash).
	$status_map  = array(
		'spam'  => 'flamingo-spam',
		'trash' => 'trash',
	);
	$post_status = 'publish';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['post_status'] ) && isset( $status_map[ $_REQUEST['post_status'] ] ) ) {
		$post_status = $status_map[ $_REQUEST['post_status'] ];
	}

	$tlds    = fmc_get_tld_counts( 'flamingo_inbound', '_from_email', $post_status );
	$domains = fmc_get_domain_counts( 'flamingo_inbound', '_from_email', $post_status );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_tld    = isset( $_REQUEST['fmc_tld'] ) ? sanitize_text_field( $_REQUEST['fmc_tld'] ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_domain = isset( $_REQUEST['fmc_domain'] ) ? sanitize_text_field( $_REQUEST['fmc_domain'] ) : '';

	fmc_render_filter_dropdowns( $tlds, $domains, $selected_tld, $selected_domain, 'inbound' );
}

add_action( 'admin_footer', 'fmc_inject_inbound_filters' );

/**
 * Inject filter dropdowns on the Address Book page.
 */
function fmc_inject_contact_filters() {
	$screen = get_current_screen();

	if ( ! $screen || 'toplevel_page_flamingo' !== $screen->id ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {
		return;
	}

	$tlds    = fmc_get_tld_counts( 'flamingo_contact', '_email' );
	$domains = fmc_get_domain_counts( 'flamingo_contact', '_email' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_tld    = isset( $_REQUEST['fmc_tld'] ) ? sanitize_text_field( $_REQUEST['fmc_tld'] ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_domain = isset( $_REQUEST['fmc_domain'] ) ? sanitize_text_field( $_REQUEST['fmc_domain'] ) : '';

	fmc_render_filter_dropdowns( $tlds, $domains, $selected_tld, $selected_domain, 'contact' );
}

add_action( 'admin_footer', 'fmc_inject_contact_filters' );

/**
 * Filter the inbound messages WP_Query by fmc_tld and fmc_domain.
 *
 * Uses a static flag to avoid filtering get_views() count queries.
 *
 * @param WP_Query $query The current query object.
 */
function fmc_filter_inbound_by_domain( $query ) {
	static $done = false;

	if ( $done ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'flamingo_page_flamingo_inbound' !== $screen->id ) {
		return;
	}

	if ( 'flamingo_inbound' !== $query->get( 'post_type' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_fmc_tld    = ! empty( $_REQUEST['fmc_tld'] );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_fmc_domain = ! empty( $_REQUEST['fmc_domain'] );

	if ( ! $has_fmc_tld && ! $has_fmc_domain ) {
		return;
	}

	global $wpdb;

	$meta_query = $query->get( 'meta_query' );

	if ( ! is_array( $meta_query ) ) {
		$meta_query = array();
	}

	$meta_query['relation'] = 'AND';

	if ( $has_fmc_tld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tld = sanitize_text_field( $_REQUEST['fmc_tld'] );

		if ( preg_match( '/^[a-z0-9.-]+$/i', $tld ) ) {
			$meta_query[] = array(
				'key'     => '_from_email',
				'value'   => '%.' . $wpdb->esc_like( $tld ),
				'compare' => 'LIKE',
			);
		}
	}

	if ( $has_fmc_domain ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$domain = sanitize_text_field( $_REQUEST['fmc_domain'] );

		if ( preg_match( '/^[a-z0-9.-]+$/i', $domain ) ) {
			$meta_query[] = array(
				'key'     => '_from_email',
				'value'   => '%@' . $wpdb->esc_like( $domain ),
				'compare' => 'LIKE',
			);
		}
	}

	$query->set( 'meta_query', $meta_query );

	$done = true;
}

add_action( 'pre_get_posts', 'fmc_filter_inbound_by_domain' );

/**
 * Filter the contacts WP_Query by fmc_tld and fmc_domain.
 *
 * No static flag needed as the contacts page does not have get_views().
 *
 * @param WP_Query $query The current query object.
 */
function fmc_filter_contacts_by_domain( $query ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'toplevel_page_flamingo' !== $screen->id ) {
		return;
	}

	if ( 'flamingo_contact' !== $query->get( 'post_type' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_fmc_tld    = ! empty( $_REQUEST['fmc_tld'] );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_fmc_domain = ! empty( $_REQUEST['fmc_domain'] );

	if ( ! $has_fmc_tld && ! $has_fmc_domain ) {
		return;
	}

	global $wpdb;

	$meta_query = $query->get( 'meta_query' );

	if ( ! is_array( $meta_query ) ) {
		$meta_query = array();
	}

	$meta_query['relation'] = 'AND';

	if ( $has_fmc_tld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tld = sanitize_text_field( $_REQUEST['fmc_tld'] );

		if ( preg_match( '/^[a-z0-9.-]+$/i', $tld ) ) {
			$meta_query[] = array(
				'key'     => '_email',
				'value'   => '%.' . $wpdb->esc_like( $tld ),
				'compare' => 'LIKE',
			);
		}
	}

	if ( $has_fmc_domain ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$domain = sanitize_text_field( $_REQUEST['fmc_domain'] );

		if ( preg_match( '/^[a-z0-9.-]+$/i', $domain ) ) {
			$meta_query[] = array(
				'key'     => '_email',
				'value'   => '%@' . $wpdb->esc_like( $domain ),
				'compare' => 'LIKE',
			);
		}
	}

	$query->set( 'meta_query', $meta_query );
}

add_action( 'pre_get_posts', 'fmc_filter_contacts_by_domain' );

/**
 * Preserve fmc_tld and fmc_domain parameters across Flamingo bulk action redirects.
 *
 * @param string $location Redirect URL.
 * @return string Modified redirect URL.
 */
function fmc_preserve_filters_on_redirect( $location ) {
	if ( false === strpos( $location, 'page=flamingo' ) ) {
		return $location;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['fmc_tld'] ) ) {
		$location = add_query_arg( 'fmc_tld', sanitize_text_field( $_REQUEST['fmc_tld'] ), $location );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['fmc_domain'] ) ) {
		$location = add_query_arg( 'fmc_domain', sanitize_text_field( $_REQUEST['fmc_domain'] ), $location );
	}

	return $location;
}

add_filter( 'wp_redirect', 'fmc_preserve_filters_on_redirect' );
