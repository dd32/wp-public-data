<?php
/**
 * Updates the core .json files by checking for core updates.
 */

include __DIR__ . '/common.php';

// $all_versions = array_keys( (array) json_decode( fetch( 'https://api.wordpress.org/core/stable-check/1.0/' ) ) );

$offers = json_decode( fetch( 'https://api.wordpress.org/core/version-check/1.7/' ) );
$offers = $offers->offers ?? [];
usort( $offers, function( $a, $b ) {
	return version_compare( $a->version, $b->version );
} );

$core_releases = fopen( __DIR__ . '/../core-releases.csv', 'a' );

$count = 0;
foreach ( $offers as $offering ) {
	$version = $offering->version;

	$filename = dirname( __DIR__ ) . "/core/{$version}.json";

	// If we already knew about it, nothing here.
	if ( file_exists( $filename ) ) {
		continue;
	}

	// Stats for logs.
	$count++;

	unset( $offering->response, $offering->current, $offering->partial_version );

	// Add release date.
	$offering->release_date = gmdate( 'Y-m-d H:i:s', strtotime( fetch_headers( $offering->download )['Last-Modified'] ?? 'now' ) );

	// Add checksums.
	$offering->checksums = json_decode( fetch( "https://api.wordpress.org/core/checksums/1.0/?locale={$offering->locale}&version={$version}" ) )->checksums ?? [];

	file_put_contents( $filename, json_encode( $offering, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

	fputcsv(
		$core_releases,
		[
			$offering->version,
			$offering->download,
			$offering->release_date,
			$offering->locale,
			$offering->php_version,
			$offering->mysql_version,
		]
	);
}

echo "Recorded $count new core releases.\n";