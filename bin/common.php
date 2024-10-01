<?php

function fetch( $url ) {
	$context = stream_context_create( [
		'http' => [
			'user_agent'    => 'WordPress.org public data Sluper; https://github.com/dd32/wp-public-data',
			'timeout'       => 30,
			'ignore_errors' => true,
			'header'        => [
				'Host: ' . parse_url( $url, PHP_URL_HOST ),
			]
		]
	] );
	return file_get_contents( $url, false, $context );
}

function fetch_and_save_plugin( $slug, $fallback_data = false ) {
	// Fetch full plugin data.
	$data = json_decode( fetch( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&fields=language_packs,compatibility,short_description,description,icons,blocks,block_assets,author_block_count,author_block_rating,blueprints,stable_tag,downloaded&slug=' . $slug ), true );

	if ( ! $data ) {
		$data = $fallback_data; // Partial. meh.
	}

	if ( empty( $data['slug'] ) ) {
		return;
	}

	// Cleanup.. We're not storing reviews or duplicated screenshot HTML here.
	unset( $data['sections']['reviews'], $data['sections']['screenshots'] );
	// Don't include Downloads, they're meaningless.
	unset( $data['downloaded'] );

	// Ensure screenshots are always an array.
	$data['screenshots'] = array_values( $data['screenshots'] );

	// Store the Last updated value in a more sane manner.
	$data['last_updated'] = gmdate( 'Y-m-d H:i:s', strtotime( $data['last_updated'] ) );

	file_put_contents( dirname( __DIR__ ) . '/plugins/' . $slug . '.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

	return (object) $data;
}

function fetch_and_save_theme( $slug, $fallback_data = false ) {
	// Fetch full theme data.
	$all_fields = 'description,downloaded,downloadlink,last_updated,creation_time,parent,rating,ratings,reviews_url,screenshot_count,screenshot_url,screenshots,sections,tags,template,versions,theme_url,homepage,extended_author,photon_screenshots,active_installs,requires,requires_php,trac_tickets,is_commercial,is_community,external_repository_url,external_support_url,upload_date';
	$data = json_decode( fetch( 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&fields=' . $all_fields . '&slug=' . $slug ), true );

	if ( ! $data || ! empty( $data['error'] ) ) {
		$data = $fallback_data; // Partial. meh.
	}

	if ( empty( $data['slug'] ) ) {
		return;
	}

	// Transform the 'trac_tickets' field into something useful.
	$data['trac_tickets'] = array_map( function( $id ) {
		return "https://themes.trac.wordpress.org/ticket/{$id}";
	}, $data['trac_tickets'] ?? [] );

	// Don't include Downloads, they're meaningless.
	unset( $data['downloaded'] );

	file_put_contents( dirname( __DIR__ ) . '/themes/' . $slug . '.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

	return (object) $data;
}