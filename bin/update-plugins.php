<?php
/**
 * Updates the plugin .json files by refreshing the plugins based on the last days worth of updated plugins.
 *
 * TODO:
 *  - Use a proper user agent
 */

$page = 0;
do {
	$page++; // Pages start at 1.
	fwrite( STDERR, "Processing page $page\n" );

	$url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&browse=updated&posts_per_page=100&page=' . $page;

	$plugins = json_decode( file_get_contents( $url ) );
	if ( ! $plugins || ! $plugins->plugins ) {
		break;
	}

	foreach ( $plugins->plugins as $plugin_data ) {
		$updated_timestamp = strtotime( $plugin_data->last_updated );
		if ( $updated_timestamp < time() - 60 * 60 * 12 ) {
			break 2; // Stop processing if we hit a plugin that hasn't been updated in the 12hrs
		}

		$slug = $plugin_data->slug;

		fwrite( STDERR, "\t$slug {$plugin_data->version} ({$plugin_data->last_updated})\n" );

		// Fetch full plugin data.
		$data = json_decode( file_get_contents( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&fields=language_packs,compatibility,short_description,description,icons,blocks,block_assets,author_block_count,author_block_rating,blueprints,stable_tag,downloaded&slug=' . $slug ), true );

		if ( ! $data ) {
			$data = $plugin_data; // Partial. meh.
		}

		// Cleanup.. We're not storing reviews or duplicated screenshot HTML here.
		unset( $data['sections']['reviews'], $data['sections']['screenshots'] );

		// Ensure screenshots are always an array.
		$data['screenshots'] = array_values( $data['screenshots'] );

		file_put_contents( dirname( __DIR__ ) . '/plugins/' . $slug . '.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		usleep( 250000 ); // 250ms Slow we go.
	}

} while( true );

echo "Done.";
