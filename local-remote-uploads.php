<?php
/**
 * Prefer a remote uploads base URL; fall back to local when remote misses a file.
 *
 * Inert until configured in wp-config.php:
 *
 * define( 'REMOTE_UPLOADS_BASE', 'https://example.com/wp-content/uploads' );
 */

/**
 * Configured remote uploads base URL, or null when not set.
 */
function remote_uploads_base(): ?string {
	if ( ! defined( 'REMOTE_UPLOADS_BASE' ) ) {
		return null;
	}

	$base = REMOTE_UPLOADS_BASE;

	if ( ! is_string( $base ) || $base === '' ) {
		return null;
	}

	return untrailingslashit( $base );
}

/**
 * Admin notice when the mu-plugin is present but not configured.
 */
function remote_uploads_config_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html(
		'Remote uploads mu-plugin: add REMOTE_UPLOADS_BASE to wp-config.php (for example https://example.com/wp-content/uploads). Until then, upload URLs are unchanged.'
	);
	echo '</p></div>';
}

if ( remote_uploads_base() === null ) {
	add_action( 'admin_notices', 'remote_uploads_config_notice' );

	return;
}

/**
 * Normalise a path relative to wp-content/uploads (no leading slash).
 */
function remote_uploads_relative_path( string $path ): string {
	$path = wp_normalize_path( $path );
	$path = ltrim( $path, '/' );

	if ( str_starts_with( $path, 'wp-content/uploads/' ) ) {
		$path = substr( $path, strlen( 'wp-content/uploads/' ) );
	} elseif ( str_starts_with( $path, 'uploads/' ) ) {
		$path = substr( $path, strlen( 'uploads/' ) );
	}

	return $path;
}

/**
 * Extract uploads-relative path from a local or remote upload URL.
 */
function remote_uploads_relative_from_url( string $url ): string {
	$path = wp_parse_url( $url, PHP_URL_PATH );

	if ( ! is_string( $path ) || $path === '' ) {
		return '';
	}

	return remote_uploads_relative_path( $path );
}

/**
 * Cached HEAD check against the remote base (only when a local copy exists).
 */
function remote_uploads_exists_on_remote( string $remote_url ): bool {
	$cache_key = 'remote_uploads_' . md5( $remote_url );
	$cached    = get_transient( $cache_key );

	if ( $cached === 'yes' ) {
		return true;
	}

	if ( $cached === 'no' ) {
		return false;
	}

	$response = wp_remote_head(
		$remote_url,
		array(
			'timeout'     => 2,
			'redirection' => 2,
		)
	);

	$exists = ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 200;

	set_transient( $cache_key, $exists ? 'yes' : 'no', DAY_IN_SECONDS );

	return $exists;
}

/**
 * Prefer remote uploads; use local URL when remote returns 404 and a local file exists.
 */
function remote_uploads_resolve_url( string $relative_path ): string {
	$relative_path = remote_uploads_relative_path( $relative_path );
	$remote_base   = remote_uploads_base();

	if ( $remote_base === null ) {
		return home_url( '/wp-content/uploads/' . ltrim( $relative_path, '/' ) );
	}

	if ( $relative_path === '' ) {
		return $remote_base;
	}

	$remote_url = $remote_base . '/' . $relative_path;
	$local_path = wp_normalize_path( WP_CONTENT_DIR . '/uploads/' . $relative_path );
	$local_url  = home_url( '/wp-content/uploads/' . $relative_path );

	if ( ! file_exists( $local_path ) ) {
		return $remote_url;
	}

	if ( remote_uploads_exists_on_remote( $remote_url ) ) {
		return $remote_url;
	}

	return $local_url;
}

/**
 * Resolve an existing upload URL (any domain) to the best local/remote target.
 */
function remote_uploads_resolve_url_from_url( string $url ): string {
	$relative = remote_uploads_relative_from_url( $url );

	if ( $relative === '' ) {
		return $url;
	}

	return remote_uploads_resolve_url( $relative );
}

/**
 * @param string $url          Attachment URL.
 * @param int    $attachment_id Attachment post ID.
 */
function remote_uploads_attachment_url( string $url, int $attachment_id ): string {
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

	if ( is_string( $file ) && $file !== '' ) {
		if ( str_starts_with( $file, 'http' ) ) {
			return remote_uploads_resolve_url_from_url( $file );
		}

		return remote_uploads_resolve_url( $file );
	}

	return remote_uploads_resolve_url_from_url( $url );
}
add_filter( 'wp_get_attachment_url', 'remote_uploads_attachment_url', 10, 2 );

/**
 * @param array{0: string, 1: int, 2: int, 3?: bool}|false $image         Image data.
 * @param int|string                                       $attachment_id Attachment post ID.
 * @param string|int[]                                     $size          Requested size.
 * @param bool                                             $icon          Whether an icon was requested.
 * @return array{0: string, 1: int, 2: int, 3?: bool}|false
 */
function remote_uploads_attachment_image_src( $image, $attachment_id, $size, bool $icon ) {
	if ( ! is_array( $image ) || empty( $image[0] ) || ! is_string( $image[0] ) ) {
		return $image;
	}

	$image[0] = remote_uploads_resolve_url_from_url( $image[0] );

	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'remote_uploads_attachment_image_src', 10, 4 );

/**
 * @param array<int, array{url: string, descriptor: string, value: int}> $sources       Srcset sources.
 * @param int[]                                                          $size_array    Width and height.
 * @param string                                                         $image_src     Main image URL.
 * @param array<string, mixed>                                           $image_meta    Attachment metadata.
 * @param int                                                            $attachment_id Attachment post ID.
 * @return array<int, array{url: string, descriptor: string, value: int}>
 */
function remote_uploads_attachment_image_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array {
	foreach ( $sources as $width => $source ) {
		if ( ! empty( $source['url'] ) && is_string( $source['url'] ) ) {
			$sources[ $width ]['url'] = remote_uploads_resolve_url_from_url( $source['url'] );
		}
	}

	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'remote_uploads_attachment_image_srcset', 10, 5 );

/**
 * @param string $url  Content URL.
 * @param string $path Path relative to wp-content.
 */
function remote_uploads_content_url( string $url, string $path ): string {
	if ( ! $path || ! str_starts_with( ltrim( $path, '/' ), 'uploads/' ) ) {
		return $url;
	}

	return remote_uploads_resolve_url( substr( ltrim( $path, '/' ), strlen( 'uploads/' ) ) );
}
add_filter( 'content_url', 'remote_uploads_content_url', 10, 2 );
