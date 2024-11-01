<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface SHIPANY_API_Label {

	public function get_shipany_label( $args );

	public function delete_shipany_label( $args );

	public function shipany_test_connection( $client_id, $client_secret );

	public function shipany_validate_field( $key, $value );
}
