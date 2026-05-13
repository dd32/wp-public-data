<?php
/**
 * Updates the releases files based on the current git diff.
 *
 * Releases are written to releases/{plugins,themes}/YYYY-MM.csv, bucketed by
 * the release date of each row. The top-level plugin-releases.csv and
 * theme-releases.csv are symlinks pointing at the most recent month.
 */

include __DIR__ . '/common.php';

$root = dirname( __DIR__ );

exec(
	'git status plugins themes -z --porcelain=v1',
	$output,
	$return_code
);
if ( $return_code !== 0 ) {
	echo "Error: git status failed.\n";
}

$header = [
	'Slug',
	'Name',
	'Version',
	'Previous Version',
	'Download Link',
	'Released',
	'WordPress.org URL',
	'Required WP',
	'Required PHP',
	'Active Installs',
];

$handles      = []; // "{type}/{YYYY-MM}" => file pointer
$latest_month = [ 'plugins' => '', 'themes' => '' ];

$get_handle = function ( $type, $month ) use ( $root, $header, &$handles, &$latest_month ) {
	$key = "{$type}/{$month}";
	if ( ! isset( $handles[ $key ] ) ) {
		$dir = "{$root}/releases/{$type}";
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		$path     = "{$dir}/{$month}.csv";
		$is_new   = ! file_exists( $path );
		$handles[ $key ] = fopen( $path, 'a' );
		if ( $is_new ) {
			fputcsv( $handles[ $key ], $header );
		}
	}
	if ( $month > $latest_month[ $type ] ) {
		$latest_month[ $type ] = $month;
	}
	return $handles[ $key ];
};

$count = 0;

foreach ( array_filter( explode( "\0", $output[0] ?? '' ) ) as $line ) {
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

	$released = $new_data->last_updated_time ?? $new_data->last_updated; // Themes: last_updated_time; plugins: last_updated.
	$month    = substr( $released, 0, 7 );

	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		echo "Skipping {$filename}: unparseable release date '{$released}'.\n";
		continue;
	}

	$count++;

	fputcsv(
		$get_handle( $type, $month ),
		[
			$new_data->slug,
			html_entity_decode( $new_data->name ),
			$new_data->version,
			$old_data->version ?? '',
			$new_data->download_link,
			$released,
			"https://wordpress.org/{$type}/{$new_data->slug}/",
			$new_data->requires ?? '',
			$new_data->requires_php ?? '',
			$new_data->active_installs ?? 0,
		]
	);
}

echo "Recorded $count new releases.\n";

foreach ( $handles as $h ) {
	fclose( $h );
}

// Refresh top-level symlinks to point at the most recent month present on disk.
foreach ( [ 'plugins' => 'plugin-releases.csv', 'themes' => 'theme-releases.csv' ] as $type => $symlink_name ) {
	$months = glob( "{$root}/releases/{$type}/*.csv" );
	if ( ! $months ) {
		continue;
	}
	sort( $months );
	$target  = 'releases/' . $type . '/' . basename( end( $months ) );
	$link    = "{$root}/{$symlink_name}";
	$current = is_link( $link ) ? readlink( $link ) : null;
	if ( $current !== $target ) {
		if ( file_exists( $link ) || is_link( $link ) ) {
			unlink( $link );
		}
		symlink( $target, $link );
		echo "Updated {$symlink_name} -> {$target}\n";
	}
}

echo "Generating latest-versions.json.gz\n";

// Create a compressed latest-versions file from all of the assets.
$latest_versions = [ 'plugins' => [], 'themes' => [] ];
foreach ( [ 'plugins', 'themes' ] as $type ) {
	foreach ( glob( __DIR__ . "/../{$type}/*/*.json" ) as $file ) {
		$data = json_decode( file_get_contents( $file ) );
		if ( empty( $data->slug ) || ! isset( $data->version ) ) {
			continue;
		}

		$latest_versions[ $type ][ $data->slug ] = $data->version;
	}
}
file_put_contents( 'compress.zlib://' . __DIR__ . '/../latest-versions.json.gz', json_encode( $latest_versions ) );
