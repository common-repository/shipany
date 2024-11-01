<?php

namespace PR\REST_API;

use PR\REST_API\Interfaces\API_Auth_Interface;
use PR\REST_API\Interfaces\API_Driver_Interface;

/**
 * A generic REST API client implementation.
 *
 * This class does not expose any public methods. It is intended to be extended to create real client classes,
 * alleviating most of the work involved in using drivers, auth handlers, creating request and response objects as
 * well as ensuring proper request URL integrity.
 *
 * This class provides the following functionality:
 * - protected methods for sending GET, POST and DELETE requests
 * - a base URL property that is prepended to routes before creating and sending requests
 * - implements usage of a REST API driver for sending requests
 * - implements optional usage of an auth handler to authorize requests before sending them via the driver
 *
 * Example usage:
 *  ```
 *  class My_API_Client extends API_Client {
 *
 *      public function do_foo_bar( ) {
 *          $response = $this->post( 'foo/bar, array('some' => 'data') );
 *
 *          if ($response->status === 200) {
 *              return $response->body;
 *          }
 *
 *          throw new Exception($response->body->error_msg);
 *      }
 *
 *  }
 *  ```
 *
 * @since [*next-version*]
 */
class API_Client {
	/**
	 * The base URL for the REST API.
	 *
	 * @since [*next-version*]
	 *
	 * @var string
	 */
	protected $base_url;
	/**
	 * The REST API driver to use.
	 *
	 * @since [*next-version*]
	 *
	 * @var API_Driver_Interface
	 */
	protected $driver;
	/**
	 * The authorization driver to use.
	 *
	 * @since [*next-version*]
	 *
	 * @var API_Auth_Interface|null
	 */
	protected $auth;

	/**
	 * Constructor.
	 *
	 * @since [*next-version*]
	 *
	 * @param string                  $base_url The base URL for the REST API.
	 * @param API_Driver_Interface    $driver   The REST API driver to use.
	 * @param API_Auth_Interface|null $auth     Optional authorization driver to use.
	 */
	public function __construct( $base_url, API_Driver_Interface $driver, API_Auth_Interface $auth = null ) {
		$this->base_url = $base_url;
		$this->driver = $driver;
		$this->auth = $auth;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get( $route, array $params = array(), array $headers = array(), array $cookies = array() ) {
		return $this->send_request( Request::TYPE_GET, $route, $params, '', $headers, $cookies );
	}

	// public function get_test_con( $route, $api_key_temp, array $params = array(), array $headers = array(), array $cookies = array() ) {
	// 	return $this->send_request_test_con( Request::TYPE_GET, $route, $params, $api_key_temp, $headers, $cookies );
	// }

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function post( $route, $body = '', array $headers = array(), array $cookies = array() ) {
		return $this->send_request( Request::TYPE_POST, $route, array(), $body, $headers, $cookies );
	}

	public function post_connect( $route, $body = '', $api_key = '', $api_endpoint = '', array $headers = array(), array $cookies = array() ) {
		return $this->send_request_connect( Request::TYPE_POST, $route, array(), $body, $headers, $cookies, $api_key, $api_endpoint);
	}

	protected function patch( $route, $body = '', array $headers = array(), array $cookies = array(), array $params = array() ) {
		return $this->send_request( Request::TYPE_PATCH, $route, $params, $body, $headers, $cookies );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	protected function delete( $route, $body = '', array $headers = array(), array $cookies = array() ) {
		return $this->send_request( Request::TYPE_DELETE, $route, array(), $body, $headers, $cookies );
	}

	/**
	 * Sends a request using the internal driver.
	 *
	 * @since [*next-version*]
	 *
	 * @param int        $type    The request type, either {@link TYPE_GET} or {@link TYPE_POST}.
	 * @param string     $route   The request route, relative to the REST API's base URL.
	 * @param array      $params  The GET params for this request.
	 * @param mixed|null $body    The body of the request.
	 * @param array      $headers The request headers to send.
	 * @param array      $cookies The request cookies to send.
	 *
	 * @return Response The response.
	 */
	protected function send_request(
		$type,
		$route,
		array $params = array(),
		$body = '',
		array $headers = array(),
		array $cookies = array()
	) {
        // assign plugin version to body payload
        if(is_array($body)){
            $body['plugin_version'] = SHIPANY_VERSION;
            $body['order_from'] = 'woocommerce';
        }
		// Generate the full URL and the request object
		$full_url = URL_Utils::merge_url_and_route($this->base_url, trim($route)) . '/';
		$request = new Request( $type, $full_url, $params, $body, $headers, $cookies );

		// If we have an authorization driver, authorize the request
		//if ($this->auth !== null) {
		//	$request = $this->auth->authorize($request);
		//}
		//shipany
		if (empty(SHIPANY()->get_shipping_shipany_settings()['shipany_api_key']))
		{
			$request->headers['api-tk'] = '';
		}
		else
		{
			$request->headers['api-tk'] = str_replace(array("SHIPANYSBX1","SHIPANYSBX2","SHIPANYDEV","SHIPANYDEMO"),array("","","",""),SHIPANY()->get_shipping_shipany_settings()['shipany_api_key']);
            $request->headers['order-from'] = "Woocommerce";
            $request->headers['order-from-ver'] = SHIPANY_VERSION;
        }
		// Send the request using the driver and obtain the response
		$response = $this->driver->send( $request );

		return $response;
	}

	protected function send_request_connect(
		$type,
		$route,
		array $params = array(),
		$body = '',
		array $headers = array(),
		array $cookies = array(),
		$api_key ='',
		$api_endpoint =''
	) {
		// Generate the full URL and the request object
		$full_url = URL_Utils::merge_url_and_route($api_endpoint, $route);
		$request = new Request( $type, $full_url, $params, $body, $headers, $cookies );

		// If we have an authorization driver, authorize the request
		//if ($this->auth !== null) {
		//	$request = $this->auth->authorize($request);
		//}
		//shipany
		// $request->headers['api-tk'] = 'd0de5e1e-e095-452d-8c07-db740bc71da7';
		$request->headers['api-tk'] = str_replace("SHIPANYDEV","",$api_key); //can remove
		// Send the request using the driver and obtain the response
		$response = $this->driver->send( $request );

		return $response;
	}	
}
