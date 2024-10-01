<?php
/**
 * Updates the releases files based on the current git diff.
 */

include __DIR__ . '/common.php';

exec(
	'git status plugins themes -z --porcelain=v1',
	$output,
	$return_code
);
if ( $return_code !== 0 ) {
	echo "Error: git status failed.\n";
}

$plugin_releases = fopen( __DIR__ . '/../plugin-releases.csv', 'a' );
$theme_releases  = fopen( __DIR__ . '/../theme-releases.csv', 'a' );

$count = 0;

foreach ( array_filter( explode( "\0", $output[0] ) ) as $line ) {
	$state    = substr( $line, 0, 2 );
	$filename = substr( $line, 3 );
	$type     = explode( '/', $filename )[0];

	if ( str_contains( $state, '?' ) ) {
		// No old version.
		$old_data = false;
	} elseif ( str_contains( $state, 'D' ) ) {
		// Deleted, nothing to log.
		continue;
	} else {
		$old_data = json_decode( `git show HEAD:{$filename}` );
	}

	$new_data = json_decode( file_get_contents( $filename ) );

	// Only record it if something has changed.
	if ( $new_data->version === ( $old_data->version ?? '' ) ) {
		continue;
	}

	// Stats for logs.
	$count++;

	fputcsv(
		$type === 'plugins' ? $plugin_releases : $theme_releases,
		[
			$new_data->slug,
			html_entity_decode( $new_data->name ),
			$new_data->version,
			$old_data->version ?? '',
			$new_data->download_link,
			$new_data->last_updated_time ?? $new_data->last_updated, // Themes: last_updated_time; plugins: last_updated.
			"https://wordpress.org/{$type}/{$new_data->slug}/",
			$new_data->requires ?? '',
			$new_data->requires_php ?? '',
		]
	);
}

echo "Recorded $count new releases.\n";
