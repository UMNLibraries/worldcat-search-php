<?php

require_once 'HTTP/Request.php';
require_once 'ArgValidator.php';

// Called "UserAgent" because the API is inspired by Perl's LWP (i.e. libwww-perl).
class WorldCatSearch_UserAgent
{
    // This class just makes multiple attempts at sending the same request.
    protected $max_attempts = 10;
    function set_max_attempts($max_attempts)
    {
        ArgValidator::validate( $max_attempts, array('is' => 'int') );
        $this->max_attempts = $max_attempts;
    }

    // Add more as we need them.
    protected $method_constant_map = array('GET' => HTTP_REQUEST_METHOD_GET,);
    function set_method_constant_map($method_constant_map)
    {
        ArgValidator::validate(
            $method_constant_map,
            array('is' => 'array')
        );
        $this->method_constant_map = $method_constant_map;
    }

    // Accessors:
    public function __call($function, $args)
    {
        // Since we're handling only accessors here, the function name should
        // be the same as the property name:
        $property = $function;
        $class = get_class($this);
        $ref_class = new ReflectionClass( $class );
        if (!$ref_class->hasProperty($property)) {
            throw new Exception("Method '$function' does not exist in class '$class'.");
        }
        return $this->$property;
    }

    function __construct()
    {
        $validated_args = ArgValidator::validate(
            func_get_args(),
            array('max_attempts' => array('required' => false), 'method_constant_map' => array('required' => false),)
        );

        foreach ($validated_args as $property => $value) {
            $mutator = 'set_' . $property;
            $this->$mutator( $value );
        }
    }

    // LWP::UserAgent calls this method "request".
    public function send( $request )
    {
        $http = new HTTP_Request( $request->uri() );
        $method_constant_map = $this->method_constant_map();
        $method_constant = $method_constant_map[ $request->method() ];
        $http->setMethod( $method_constant );

        $params = $request->params();
        foreach ($params as $k => $v) {
            $http->addQueryString($k, $v);
        }

        $request_failed = true;
    
        for ($i = 1; $i <= $this->max_attempts(); $i++) {
            unset( $status );
            $status = $http->sendRequest();
            // This certainly needs tests!
            if (PEAR::isError($status)) { 
                error_log("Attempt $i: " . $status->getMessage()); 
                continue;
            } 
            // TODO: Add parsing of response to look for errors,
            // and throw exceptions if necessary.
            $code = (int) $http->getResponseCode();
            if ($code > 499 and $code < 600) {
                // Can't I get the "reason" message here, too? I can in HTTP_Request2.
                error_log("Attempt $i: server response: $code");
                continue;
            }
            $request_failed = false;
            break;
        }
        if ($request_failed) {
            throw new Exception(
                // TODO: add a $request->as_string() method in here so we can identify the request better?
                "Giving up on request after " . $this->max_attempts() . " attempts."
            );
        }
        
        return $http->getResponseBody();
    }
} // end class WorldCatSearch_UserAgent
