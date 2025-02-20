<?php

namespace PR\REST_API\SHIPANY_Asia;

use Exception;
use SHIPANY\Utils\Args_Parser;

/**
 * A class that represents a ShipAny item, which corresponds to a WooCommerce order.
 *
 * @since [*next-version*]
 */
class Item_Info {

	/**
	 * The order id
	 * 
	 * @since [*next-version*]
	 * 
	 * @var int
	 */
	public $order_id;

	/**
	 * The array of body information.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	public $body = array();

	/**
	 * The array of shipment information.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	public $shipment = array();

	/**
	 * The array of shipment pieces information.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	public $shipment_pieces;
	
	/**
	 * The array of order recipient information.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	public $recipient = array();

	/**
	 * The array of consignee information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $consignee = array();

	/**
	 * The array of shipper information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $shipper = array();

	/**
	 * The array of content item information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $contents = array();

	/**
	 * The units of measurement used for weights in the input args.
	 *
	 * @since [*next-version*]
	 *
	 * @var string
	 */
	protected $weightUom;

	/**
	 * Is the shipment cross-border or domestic
	 *
	 * @since [*next-version*]
	 *
	 * @var boolean
	 */
	public $isCrossBorder;

	/**
	 * Constructor.
	 *
	 * @since [*next-version*]
	 *
	 * @param array $args The arguments to parse.
	 * @param string $weightUom The units of measurement used for weights in the input args.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	public function __construct( $args, $uom, $isCrossBorder ) {
		//$this->parse_args( $args );
		$this->weightUom 	= $uom;
		$this->isCrossBorder = $isCrossBorder;

		$this->parse_args( $args, $uom );
		
	}

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @since [*next-version*]
	 *
	 * @param array $args The arguments to parse.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	protected function parse_args( $args ) {
        
		$settings = $args[ 'shipany_settings' ];
		$recipient_info = $args[ 'shipping_address' ] + $settings;
		$shipping_info = $args[ 'order_details' ] + $settings;
		$items_info = $args['items'];
		$this->body 			= Args_Parser::parse_args( $shipping_info, $this->get_body_info_schema() );
		$this->shipment 		= Args_Parser::parse_args( $shipping_info, $this->get_shipment_info_schema() );
		if (isset($args["order_details"]['storage_type'])){
			$this->shipment['storage_type'] = $args["order_details"]['storage_type'];
		}
		
		$this->consignee 		= Args_Parser::parse_args( $recipient_info, $this->get_recipient_info_schema() );

		$error_msg = '';
		$error_count = 0;
		if ($this->consignee["phone"] == '') {
			$error_count += 1;
			$error_msg .= 'Shipping "Phone" or Billing "Phone" field cannot be empty.';
		} 
		if ($this->consignee["address1"] == '') {
			$error_count += 1;
			if ($error_msg !=''){
				$error_msg .='<br>Shipping "Address line 1" field cannot be empty.';
			} else {
				$error_msg .='Shipping "Address line 1" field cannot be empty.';
			}
		}

		if (count($items_info) == 0) {
			$error_count += 1;
			if ($error_msg !=''){
				$error_msg .='<br>Please add item(s) to the order.';
			} else {
				$error_msg .='Please add item(s) to the order.';
			}
		}

		if ($error_msg != ''){
			if ($error_count >= 3) {
				throw new Exception( 'Please create WooCommerce order by clicking "Create" in Order actions below.' );
			} else {
				throw new Exception( $error_msg );
			}
		}

		if( $args['order_details']['shipany_product'] == 'SDP') {
		    $this->shipper 			= Args_Parser::parse_args( $settings, $this->get_shipper_info_schema() );
        }

		$this->contents 		= array();
		foreach ( $items_info as $item_info ) {
			$this->contents[] = Args_Parser::parse_args( $item_info, $this->get_content_item_info_schema() );
		}
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for header info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_body_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;
		
		return array(
			'label_format' => array(
				'default' => ''
			),
			'label_layout' => array(
				'default' => ''
			),
			'label_pagesize' => array(
				'default' => '400x600'
			)
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for base item info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_shipment_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'order_id'      => array(
				'error'  => __( 'Shipment "Order ID" is empty!', 'shipany' ),
			),
			'prefix' 		=> array(
				'default' => 'ShipAny'
			),
			'description' 	=> array(
			    'default'   => '',
				'validate' => function( $value ) {

					if( empty( $value ) && $this->isCrossBorder ) {
						throw new Exception( __( 'Shipment "Description" is empty!', 'shipany' ) );
					}
				},
			),
			'weight'     => array(
                'error'    => __( 'Order "Weight" is empty!', 'shipany' ),
                'validate' => function( $weight ) use ($self) {
                    if ( ! is_numeric( $weight ) || $weight < 0 ) {
                        throw new Exception( __( 'Order "Weight" must be a positive number', 'shipany' ) );
                    }
                },
                'sanitize' => function ( $weight ) use ($self) {

                    // $weight = $self->maybe_convert_to_grams( $weight, $self->weightUom );

                    return $weight;
                }
			),
			'weightUom'  => array(
				'sanitize' => function ( $uom ) use ($self) {

					return ( $uom != 'G' )? 'G' : $uom;
				}
			),
			'dimensionUom'     => array(
				'default' => 'CM'
			),
			'shipany_product' => array(
				'rename' 	=> 'product_code',
                'error'     => __( '"Product" is empty!', 'shipany' ),
			),
			'duties' => array(
				'rename' 	=> 'incoterm',
				'default' 	=> 'Duties',
			),
			'items_value' => array(
				'error'  => __( 'Shipment "Value" is empty!', 'shipany' ),
				'validate' => function( $value ) {
					if ( ! is_numeric( $value ) ) {
						throw new Exception( __( 'The order "value" must be a number', 'shipany' ) );
					}
				},
				'sanitize' => function( $value ) use ($self) {

					return $self->float_round_sanitization( $value, 2 );
				}
			),
			'currency' => array(
				'error' => __( 'Shop "Currency" is empty!', 'shipany' ),
			),
            'cod_value' => array(
                'default' => 0,
                'rename' => 'codValue',
                'sanitize' => function( $value, $args ) use ($self) {
                    if( isset( $args['is_cod'] ) && $args['is_cod'] == 'yes' ) {
                        $value = $self->float_round_sanitization( $value, 2 );
                    } else {
                        $value = 0;
                    }
                    return $value;
                }
			),
            'order_note' => array(
                'default' => '',
                'rename' => 'remarks'
            ),
			'insurance_value' => array(
				'default' => 0,
                'rename' => 'insuranceValue',
                'sanitize' => function( $value, $args ) use ($self) {
                    if( isset( $args['additional_insurance'] ) && $args['additional_insurance'] == 'yes' ) {

                        $value = $self->float_round_sanitization( $value, 2 );
					
					} else {
                        $value = 0;
                    }
                    return $value;
                }
			),
			'obox_service' => array(
                'default' => '',
                'sanitize' => function( $value ) use ($self) {

                    if ( isset( $value ) && $value == 'yes') {
                        $value = 'OBOX';
                    } else {
                        $value = '';
                    }

                    return $value;
                }
			),
			'dangerous_goods' => array(
				'default' => ''
			)
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order recipient info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_recipient_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;
		
		return array(
			'name'      => array(
				'error'  => __( 'Recipient is empty!', 'shipany' ),
				'sanitize' => function( $name ) use ($self) {

					return $self->string_length_sanitization( $name, 300 );
				}
			),
			'first_name'      => array(
				'error'  => __( 'Recipient is empty!', 'shipany' ),
				'sanitize' => function( $first_name ) use ($self) {

					return $self->string_length_sanitization( $first_name, 300 );
				}
			),
			'last_name'      => array(
				'error'  => __( 'Recipient is empty!', 'shipany' ),
				'sanitize' => function( $last_name ) use ($self) {

					return $self->string_length_sanitization( $last_name, 300 );
				}
			),
			'phone'     => array(
				'default' => '',
				'sanitize' => function( $phone ) use ($self) {

					return $self->string_length_sanitization( $phone, 50 );
				}
			),
			'email'     => array(
				'default' => '',
			),
			'address_1' => array(
				'rename' => 'address1',
				'error' => __( 'Shipping "Address 1" is empty!', 'shipany' ),
			),
			'address_2' => array(
				'rename' => 'address2',
				'default' => '',
			),
			'city'      => array(
			),
			'postcode'  => array(
				'rename' => 'postCode',
				'error' => __( 'Shipping "Postcode" is empty!', 'shipany' ),
			),
			'district' => array(
				'default' => ''
			),
			'state'     => array(
				'default' => '',
			),
			'country'   => array(
				'error' => __( 'Shipping "Country" is empty!', 'shipany' ),
			),
			'company'     => array(
				'default' => '',
			), 
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order pickup shipment info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_shipper_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'shipany_contact_name'      => array(
				'rename' => 'name',
				'error'  => __( '"Account Name" in settings is empty.', 'shipany' ),
				'sanitize' => function( $name ) use ($self) {

                    if (empty($name)) {
                        throw new Exception(
                            __( '"Account Name" in settings is empty.', 'shipany' )
                        );
                    }

					return $self->string_length_sanitization( $name, 300 );
				}
			),
			'shipany_phone'     => array(
				'rename' => 'phone',
				'default' => '',
			),
			'shipany_email'     => array(
				'rename' => 'email',
				'default' => '',
			),
			'shipany_address_1' => array(
				'rename' => 'address1',
				'error' => __( 'Base "Address 1" is empty!', 'shipany' ),
                'sanitize' => function( $name ) use ($self) {

                    if (empty($name)) {
                        throw new Exception(
                            __( 'Base "Address 1" is empty!', 'shipany' )
                        );
                    }

                    return $self->string_length_sanitization( $name, 500 );
                }
			),
			'shipany_address_2' => array(
				'rename' => 'address2',
				'default' => '',
			),
			'shipany_city'      => array(
				'rename' => 'city',
				'error' => __( 'Base "City" is empty!', 'shipany' ),
			),
			'shipany_district'     => array(
				'rename' => 'district',
				'default' => '',
			),
			'shipany_postcode'  => array(
				'rename' => 'postCode',
				'error' => __( 'Base "Postcode" is empty!', 'shipany' ),
			),
			'shipany_state'     => array(
				'rename' => 'state',
				'default' => '',
			),
			'shipany_country'   => array(
				'rename' => 'country',
				'error' => __( 'Base "Country" is empty!', 'shipany' ),
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order content item info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_content_item_info_schema()
	{
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'hs_code'     => array(
				'default'  => '',
				'validate' => function( $hs_code ) {
					$length = is_string( $hs_code ) ? strlen( $hs_code ) : 0;
					if (empty($length)) {
						return;
					}
				},
			),
			'item_description' => array(
				'rename' => 'description',
				'default' => '',
				'sanitize' => function( $description ) use ($self) {

					return $self->string_length_sanitization( $description, 500 );
				}
			),
			'item_attributes' 		=> array(
				'rename' => 'attributes',
				'default' => ''
			),
            'item_export' => array(
				'rename' => 'descriptionExport',
				'default' => '',
				'sanitize' => function( $description ) use ($self) {

					return $self->string_length_sanitization( $description, 500 );
				}
			),
			'product_id'  => array(
				'error' => __( 'Item "Product ID" is empty!', 'shipany' ),
			),
			'sku'         => array(
				'error' => __( 'Item "Product SKU" is empty!', 'shipany' ),
			),
			'item_value'       => array(
				'rename' => 'value',
				'default' => 0,
				'sanitize' => function( $value ) use ($self) {

					return $self->float_round_sanitization( $value, 2 );
				}
			),
			'origin'      => array(
				'default' => SHIPANY()->get_base_country(),
			),
			'length'      => array(),
			'width'      => array(),
			'height'      => array(),
			'qty'         => array(
				'validate' => function( $qty ) {

					if( !is_numeric( $qty ) || $qty < 1 ){

						throw new Exception(
							__( 'Item quantity must be more than 1', 'shipany' )
						);

					}
				},
			),
			'item_weight'      => array(
				'rename' => 'weight',
				'sanitize' => function ( $weight ) use ($self) {

					// $weight = $self->maybe_convert_to_grams( $weight, $self->weightUom );
					$weight = ( $weight != 0 && $weight != "")? $weight : 0.001;
					return $weight;
				}
			)
		);
	}

	/**
	 * Converts a given weight into grams, if necessary.
	 *
	 * @since [*next-version*]
	 *
	 * @param float $weight The weight amount.
	 * @param string $uom The unit of measurement of the $weight parameter..
	 *
	 * @return float The potentially converted weight.
	 */
	protected function maybe_convert_to_grams( $weight, $uom ) {
		$weight = floatval( $weight );

		switch ( $uom ) {
			case 'kg':
				$weight = $weight * 1000;
				break;
			case 'lb':
				$weight = $weight / 2.2;
				break;
			case 'oz':
				$weight = $weight / 35.274;
				break;
		}
		
		return round( $weight );
	}

	protected function float_round_sanitization( $float, $numcomma ) {

		$float = floatval( $float );

		return round( $float, $numcomma);
	}

	protected function string_length_sanitization( $string, $max ) {

		$max = intval( $max );

		if( strlen( $string ) <= $max ){

			return $string;
		}

		return substr( $string, 0, ( $max-1 ));
	}

}
