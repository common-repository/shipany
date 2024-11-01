<?php

namespace PR\REST_API\Auths;

use Closure;
use PR\REST_API\Interfaces\API_Auth_Interface;
use PR\REST_API\Request;

class Function_Auth implements API_Auth_Interface {

    /**
     * @var Closure
     */
    private $callback;
    
    /**
     * Function_Auth constructor.
     *
     * @param Closure $callback
     */
    function __construct($callback){
        $this->callback = $callback;
    }

    /**
     * @param Request $request
     *
     * @return Request
     */
	public function authorize($request) {
        return call_user_func($this->callback, $request);
    }
    
}