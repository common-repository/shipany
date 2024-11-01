<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class SHIPANY_API_Factory {

	public static function init() {
		// Load abstract classes
		include_once( 'abstract-shipany-api-rest.php' );
		include_once( 'abstract-shipany-api.php' );

		// Load interfaces
		include_once( 'interface-shipany-api-label.php' );
	}

	public static function make_shipany( $country_code ) {
		static $cache = array();

		// If object exists in cache, simply return it
		if ( array_key_exists( $country_code, $cache ) ) {
			return $cache[ $country_code ];
		}

		SHIPANY_API_Factory::init();

		$shipany_obj = null;

		try {
			switch ($country_code) {
				case 'US':
				case 'GU':
				case 'AS':
				case 'PR':
				case 'UM':
				case 'VI':
				case 'CA':
				case 'SG':
				case 'HK':
				case 'TH':
				case 'CN':
				case 'MY':
				case 'VN':
				case 'AU':
				case 'IN':
				case 'DE':
                case 'AT':
				case 'AL':
				case 'AD':
				case 'AM':
				case 'AZ':
				case 'BY':
				case 'BE':
				case 'BA':
				case 'BG':
				case 'HR':
				case 'CY':
				case 'CZ':
				case 'DK':
				case 'EE':
				case 'FI':
				case 'FR':
				case 'GE':
				case 'GR':
				case 'HU':
				case 'IS':
				case 'IE':
				case 'IT':
				case 'KM':
				case 'LV':
				case 'LI':
				case 'LT':
				case 'LU':
				case 'MT':
				case 'MD':
				case 'MC':
				case 'ME':
				case 'NL':
				case 'MK':
				case 'NO':
				case 'PL':
				case 'PT':
				case 'RO':
				case 'RU':
				case 'SM':
				case 'RS':
				case 'SK':
				case 'SI':
				case 'ES':
				case 'SE':
				case 'CH':
				case 'TR':
				case 'UA':
				case 'GB':
				case 'VA':
				default:
					$shipany_obj = new SHIPANY_API_eCS_Asia( $country_code );
			}
		} catch (Exception $e) {
			throw $e;
		}

		// Cache the object to optimize later invocations of the factory
		$cache[ $country_code ] = $shipany_obj;

		return $shipany_obj;
	}
	public static function make_shipany_test_con( $country_code,$api_key_temp ) {
		static $cache = array();

		// If object exists in cache, simply return it
		if ( array_key_exists( $country_code, $cache ) ) {
			return $cache[ $country_code ];
		}

		SHIPANY_API_Factory::init();

		$shipany_obj = null;

		try {
			switch ($country_code) {
				case 'US':
				case 'GU':
				case 'AS':
				case 'PR':
				case 'UM':
				case 'VI':
				case 'CA':
				case 'SG':
				case 'HK':
				case 'TH':
				case 'CN':
				case 'MY':
				case 'VN':
				case 'AU':
				case 'IN':
				case 'DE':
                case 'AT':
				case 'AL':
				case 'AD':
				case 'AM':
				case 'AZ':
				case 'BY':
				case 'BE':
				case 'BA':
				case 'BG':
				case 'HR':
				case 'CY':
				case 'CZ':
				case 'DK':
				case 'EE':
				case 'FI':
				case 'FR':
				case 'GE':
				case 'GR':
				case 'HU':
				case 'IS':
				case 'IE':
				case 'IT':
				case 'KM':
				case 'LV':
				case 'LI':
				case 'LT':
				case 'LU':
				case 'MT':
				case 'MD':
				case 'MC':
				case 'ME':
				case 'NL':
				case 'MK':
				case 'NO':
				case 'PL':
				case 'PT':
				case 'RO':
				case 'RU':
				case 'SM':
				case 'RS':
				case 'SK':
				case 'SI':
				case 'ES':
				case 'SE':
				case 'CH':
				case 'TR':
				case 'UA':
				case 'GB':
				case 'VA':
				default:
					$shipany_obj = new SHIPANY_API_eCS_Asia( $country_code,$api_key_temp );
			}
		} catch (Exception $e) {
			throw $e;
		}

		// Cache the object to optimize later invocations of the factory
		$cache[ $country_code ] = $shipany_obj;

		return $shipany_obj;
	}
}
