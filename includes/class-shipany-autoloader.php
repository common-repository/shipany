<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class SHIPANY_Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path = '';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		
		spl_autoload_register( array( $this, 'autoload' ) );

		$this->include_path = untrailingslashit( plugin_dir_path( SHIPANY_PLUGIN_FILE ) ) . '/includes/';
	}

	/**
	 * Take a class name and turn it into a file name.
	 *
	 * @param  string $class
	 * @return string
	 */
	private function get_file_name_from_class( $class ) {
		return 'class-' . str_replace( '_', '-', $class ) . '.php';
	}

	/**
	 * Include a class file.
	 *
	 * @param  string $path
	 * @return bool successful or not
	 */
	private function load_file( $path ) {
		if ( $path && is_readable( $path ) ) {
			include_once( $path );
			return true;
		}
		return false;
	}

	/**
	 * Auto-load WC classes on demand to reduce memory consumption.
	 *
	 * @param string $class
	 */
	public function autoload( $class ) {
		$_class = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
		$class = strtolower( $class );
		$file  = $this->get_file_name_from_class( $class );
		$path  = '';

		if ( strpos( $class, 'shipany_api' ) === 0 ) {
			$path = $this->include_path . 'shipany-api/';
		}

		if ( strpos( $class, 'front_end' ) !== false ) {
			$path = $this->include_path . 'front-end/';
		}

		// If the path is empty -> not API class, or class couldn't load, try parent dir load
		if ( empty( $path ) || ! $this->load_file( $path . $file ) ) {
			$this->load_file( $this->include_path . $file );
		}
		// Try load class without class- prefix
		if ( ! class_exists( $class ) ) {
			$this->load_file( $this->include_path . $_class . '.php' );
		}
	}
}

new SHIPANY_Autoloader();
