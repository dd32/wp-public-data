<?php
/**
 * Updates the plugin .json files by refreshing the plugins based on the last days worth of updated plugins.
 */

include __DIR__ . '/common.php';

$page = 0;
do {
	$page++; // Pages start at 1.
	fwrite( STDERR, "Processing page $page\n" );

	$url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&browse=updated&posts_per_page=100&page=' . $page;

	$plugins = json_decode( fetch( $url ) );
	if ( ! $plugins || ! $plugins->plugins ) {
		break;
	}

	foreach ( $plugins->plugins as $plugin_data ) {
		$updated_timestamp = strtotime( $plugin_data->last_updated );
		if ( $updated_timestamp < time() - 60 * 60 * 6 ) {
			break 2; // Stop processing if we hit a plugin that hasn't been updated in the 6hrs
		}

		$slug = $plugin_data->slug;

		$mins_ago = intval( ( time() - $updated_timestamp ) / 60 );
		if ( intval( $mins_ago ) > 60 ) {
			$mins_ago = intval( $mins_ago / 60 ) . 'h ' . ( $mins_ago % 60 );
		}
		$mins_ago .= 'm';

		fwrite( STDERR, "\t$slug {$plugin_data->version} (updated {$mins_ago} ago)\n" );

		fetch_and_save_plugin( $slug, $plugin_data );

		usleep( 250000 ); // 250ms Slow we go.
	}

} while( true );

echo "Done.";
