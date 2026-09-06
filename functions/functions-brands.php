<?php
/**
 * Brand ↔ Domain registry
 * =============================================================================
 * One canonical map of brand → vanity domain, reused by:
 *   - menu link rewriting  (brand section links use the full vanity domain)
 *   - the brand canonical redirect (a brand page reached on the academy root is
 *     sent to its own domain — the part .htaccess can't do because it cannot read
 *     the brand_key)
 *   - (later) canonical tags + schema url/sameAs
 *
 * Keys are the slug form (matching megamenu brand-* classes and the URL slug).
 * Aliases (polys, rpg, ipn…) are normalised in normalize_brand_key().
 *
 * NOTE: RPG canonical is readyplayergolf.ORG (the .com is parked at the host).
 * The production .htaccess currently sends .org → .com for Ready Player Golf —
 * that rule should be flipped to .com → .org to match this registry.
 * =============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical brand → vanity domain map. Slug-form keys.
 * Academy is the root (academyimmersive.org) and is intentionally NOT here —
 * root pages never redirect out.
 *
 * @return array<string,string>
 */
function get_brand_domains() {
	return array(
		'the-polys'         => 'thepolys.com',
		'metatraversal'     => 'metatraversal.com',
		'ready-player-golf' => 'readyplayergolf.org', // .com is parked at the host
		'win'               => 'worldimmersive.net',
		'time-nexus'        => 'timenexus.world',
		'wiki3dia'          => 'wiki3dia.org',
	);
}

/**
 * Normalise a brand key (legacy aliases → slug form used by the registry).
 *
 * @param string $key
 * @return string
 */
function normalize_brand_key( $key ) {
	$key = sanitize_key( (string) $key );
	$aliases = array(
		'polys'           => 'the-polys',
		'thepolys'        => 'the-polys',
		'rpg'             => 'ready-player-golf',
		'readyplayergolf' => 'ready-player-golf',
		'ipn'             => 'win',
		'metatr'          => 'metatraversal',
	);
	return isset( $aliases[ $key ] ) ? $aliases[ $key ] : $key;
}

/**
 * Vanity domain for a brand key (any alias), or '' if none (academy/unknown).
 *
 * @param string $brand_key
 * @return string
 */
function get_brand_domain( $brand_key ) {
	$domains = get_brand_domains();
	$key     = normalize_brand_key( $brand_key );
	return isset( $domains[ $key ] ) ? $domains[ $key ] : '';
}

/**
 * Hosts this WordPress install serves as the academy root (never a vanity brand).
 *
 * @return string[]
 */
function get_academy_root_hosts() {
	$hosts = array( 'academyimmersive.org', 'www.academyimmersive.org' );
	$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( $home ) {
		$hosts[] = $home;
	}
	return array_unique( $hosts );
}

/**
 * Are we on a local dev host?
 *
 * @return bool
 */
function is_dev_host() {
	$host = strtolower( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );
	return ( '' === $host
		|| strpos( $host, 'obi-wan-v' ) !== false
		|| strpos( $host, 'localhost' ) !== false
		|| strpos( $host, '127.0.0.1' ) !== false );
}

/**
 * Should menu links be rewritten to vanity domains right now?
 *
 * Production: always. Dev: only when previewing with ?brand_urls=1, so normal
 * local navigation stays on the local site.
 *
 * @return bool
 */
function brand_url_rewriting_enabled() {
	if ( is_dev_host() ) {
		return ! empty( $_GET['brand_urls'] );
	}
	return true;
}

/**
 * Rewrite a same-site URL that points into a brand section to that brand's
 * vanity domain. Leaves non-brand URLs, already-external URLs, and anchors alone.
 *
 * e.g. https://academyimmersive.org/the-polys/ballot/ → https://thepolys.com/the-polys/ballot/
 *
 * @param string $url
 * @return string
 */
function rewrite_url_to_brand_domain( $url ) {
	if ( empty( $url ) || '#' === $url || 0 === strpos( $url, '#' ) ) {
		return $url;
	}
	if ( ! brand_url_rewriting_enabled() ) {
		return $url;
	}

	$parts = wp_parse_url( $url );
	$path  = isset( $parts['path'] ) ? $parts['path'] : '';
	if ( '' === $path ) {
		return $url;
	}

	// First path segment → brand slug.
	$segments = explode( '/', ltrim( $path, '/' ) );
	$seg      = strtolower( isset( $segments[0] ) ? $segments[0] : '' );
	$domains  = get_brand_domains();
	if ( empty( $domains[ $seg ] ) ) {
		return $url; // not a brand section
	}

	// Only rewrite our own (academy-root) links; leave already-branded/external ones.
	if ( ! empty( $parts['host'] ) ) {
		if ( ! in_array( strtolower( $parts['host'] ), get_academy_root_hosts(), true ) ) {
			return $url;
		}
	}

	$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
	$frag  = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

	return 'https://' . $domains[ $seg ] . $path . $query . $frag;
}

/**
 * Brand canonical redirect — the part .htaccess cannot do.
 *
 * When a brand page (resolved via brand_key / menu ancestry) is reached on the
 * academy ROOT domain, send it to its own vanity domain, preserving the path.
 * .htaccess already handles slug-path URLs before WP runs; this catches brand
 * pages whose URL isn't under a brand slug (e.g. red-carpet events).
 *
 * Production only. Default: 302, root-host trigger, full path preserved.
 */
add_action( 'template_redirect', 'brand_canonical_redirect' );
function brand_canonical_redirect() {
	if ( is_admin()
		|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| is_feed()
		|| is_preview()
		|| is_robots() ) {
		return;
	}
	if ( is_dev_host() ) {
		return; // never redirect off the local site
	}

	$host = strtolower( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );

	// Trigger only from the academy root (keeps behaviour predictable, no loops).
	if ( ! in_array( $host, get_academy_root_hosts(), true ) ) {
		return;
	}

	if ( ! function_exists( 'polys_get_active_brand_key' ) ) {
		return;
	}
	$brand  = polys_get_active_brand_key();
	$domain = get_brand_domain( $brand );
	if ( '' === $domain ) {
		return; // academy / unresolved → stay put
	}
	if ( false !== stripos( $host, $domain ) ) {
		return; // already there
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	wp_redirect( 'https://' . $domain . $request_uri, 302 );
	exit;
}
