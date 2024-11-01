<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class SHIPANY_API {

	protected $shipany_label = null;
	protected $shipany_finder = null;

	protected $country_code;

	// abstract public function set_shipany_auth( $client_id, $client_secret );
	
	public function is_shipany_ecs( ) {
		return false;
	}

	public function is_shipany_ecs_asia( ) {
		return false;
	}

	public function is_shipany_ecomm( ) {
		return false;
	}

	public function get_shipany_label( $args ) {
		return $this->shipany_label->get_shipany_label( $args );
	}

	public function delete_shipany_label( $label_url ) {
		return $this->shipany_label->delete_shipany_label( $label_url );
	}

	abstract public function get_shipany_courier();

	abstract public function get_shipany_products_domestic();

	public function get_shipany_content_indicator( ) {
		return array();
	}

	public function shipany_test_connection( $client_id, $client_secret ) {
		return $this->shipany_label->shipany_test_connection( $client_id, $client_secret );
	}

	public function shipany_validate_field( $key, $value ) {
		return $this->shipany_label->shipany_validate_field( $key, $value );
	}

	public function shipany_reset_connection( ) {
		return;
	}

}
