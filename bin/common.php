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
