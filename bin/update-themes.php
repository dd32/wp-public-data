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
		if ( $updated_timestamp < time() - 60 * 60 * 3 ) {
			break 2; // Stop processing if we hit a theme that hasn't been updated in the 3hrs
		}

		$slug = $theme_data->slug;

		$mins_ago = intval( ( time() - $updated_timestamp ) / 60 );
		if ( intval( $mins_ago ) > 60 ) {
			$mins_ago = intval( $mins_ago / 60 ) . 'h ' . ( $mins_ago % 60 );
		}
		$mins_ago .= 'm';

		fwrite( STDERR, "\t$slug {$theme_data->version} (updated {$mins_ago} ago)\n" );

		fetch_and_save_theme( $slug, $theme_data );

		usleep( 250000 ); // 250ms Slow we go.
	}

} while( true );

echo "Done.";
