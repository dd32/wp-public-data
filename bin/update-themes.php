<?php
/**
 * Updates the theme .json files by refreshing the themes based on the last days worth of updated themes.
 */
include __DIR__ . '/common.php';

$page = 0;
do {
	$page++; // Pages start at 1.
	fwrite( STDERR, "Processing page $page\n" );

	$url = 'https://api.wordpress.org/themes/info/1.2/?action=query_themes&fields=last_updated&browse=updated&per_page=100&page=' . $page;

	$themes = json_decode( fetch( $url ) );
	if ( ! $themes || ! $themes->themes ) {
		break;
	}

	foreach ( $themes->themes as $theme_data ) {
		$updated_timestamp = strtotime( $theme_data->last_updated_time );
		if ( $updated_timestamp < time() - 60 * 60 * 12 ) {
			break 2; // Stop processing if we hit a theme that hasn't been updated in the 12hrs
		}

		$slug = $theme_data->slug;

		$mins_ago = intval( ( time() - $updated_timestamp ) / 60 );
		if ( intval( $mins_ago ) > 60 ) {
			$mins_ago = intval( $mins_ago / 60 ) . 'h ' . ( $mins_ago % 60 );
		}
		$mins_ago .= 'm';

		fwrite( STDERR, "\t$slug {$theme_data->version} (updated {$mins_ago} ago)\n" );

		// Fetch full theme data.
		$all_fields = 'description,downloaded,downloadlink,last_updated,creation_time,parent,rating,ratings,reviews_url,screenshot_count,screenshot_url,screenshots,sections,tags,template,versions,theme_url,homepage,extended_author,photon_screenshots,active_installs,requires,requires_php,trac_tickets,is_commercial,is_community,external_repository_url,external_support_url,upload_date';
		$data = json_decode( fetch( 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&fields=' . $all_fields . '&slug=' . $slug ), true );

		if ( ! $data ) {
			$data = $theme_data; // Partial. meh.
		}

		// Transform the 'trac_tickets' field into something useful.
		$data['trac_tickets'] = array_map( function( $id ) {
			return "https://themes.trac.wordpress.org/ticket/{$id}";
		}, $data['trac_tickets'] ?? [] );

		// Don't include Downloads, they're meaningless.
		unset( $data['downloaded'] );

		file_put_contents( dirname( __DIR__ ) . '/themes/' . $slug . '.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		usleep( 250000 ); // 250ms Slow we go.
	}

} while( true );

echo "Done.";
