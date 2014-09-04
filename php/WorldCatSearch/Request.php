<?php

require_once 'ArgValidator.php';

class WorldCatSearch_Request
{
    protected $api = 'sru';
    function set_api($api)
    {
        ArgValidator::validate(
            $api,
            array('is' => 'string', 'regex' => '/^(sru|opensearch)$/i')
        );
        $this->api = $api;
    }

    protected $base_uri = 'http://www.worldcat.org/webservices/catalog/search/';
    function set_base_uri($base_uri)
    {
        ArgValidator::validate( $base_uri, array('is' => 'string') );
        $this->base_uri = $base_uri;
    }

    // Constructed, in __construct(), from 'api' and 'base_uri' above:
    protected $uri;
    function set_uri($uri)
    {
        ArgValidator::validate( $uri, array('is' => 'string') );
        $this->uri = $uri;
    }

    protected $method = 'GET';
    function set_method($method)
    {
        ArgValidator::validate(
            $method,
            // TODO: This isn't all the methods, but I need to look up the others.
            array('is' => 'string', 'regex' => '/^(GET|DELETE|POST|PUT)$/i')
        );
        $this->method = $method;
    }

    protected $query;
    function set_query($query)
    {
        ArgValidator::validate( $query, array('is' => 'string') );
        $this->query = $query;
    }

    protected $wskey;
    function set_wskey($wskey)
    {
        ArgValidator::validate( $wskey, array('is' => 'string') );
        $this->wskey = $wskey;
    }

    protected $servicelevel = 'full';
    function set_servicelevel($servicelevel)
    {
        ArgValidator::validate(
            $servicelevel,
            array('is' => 'string', 'regex' => '/^(default|full)$/i')
        );
        $this->servicelevel = $servicelevel;
    }

    protected $schema = 'marcxml';
    function set_schema($schema)
    {
        ArgValidator::validate(
            $schema,
            array('is' => 'string', 'regex' => '/^(dc|marcxml)$/i')
        );
        $this->schema = $schema;
    }

    // Constructed, in __construct(), based on the value of 'schema' above:
    protected $recordSchema;
    function set_recordSchema($recordSchema)
    {
        ArgValidator::validate( $recordSchema, array('is' => 'string') );
        $this->recordSchema = $recordSchema;
    }

    protected $startRecord = 1;
    function set_startRecord($startRecord)
    {
        ArgValidator::validate( $startRecord, array('is' => 'int') );
        $this->startRecord = $startRecord;
    }

    // 50 is max-per-request allowed by WorldCat.
    protected $maximumRecords = 50;
    function set_maximumRecords($maximumRecords)
    {
        ArgValidator::validate( $maximumRecords, array('is' => 'int') );
        $this->maximumRecords = $maximumRecords;
    }

    // Constructed, in __construct(), from various other properties:
    protected $params = array();
    function set_params(Array $params)
    {
        // No need to use ArgValidator since PHP type-hinting supports arrays.
        // TODO: Fix ArgValidator so that it supports single array arguments?
        $this->params = $params;
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

    function __construct(Array $args)
    {
        $validated_args = ArgValidator::validate(
            $args,
            array('wskey' => array('required' => true), 'query' => array('required' => true),)
        );

        foreach ($validated_args as $property => $value) {
            $mutator = 'set_' . $property;
            $this->$mutator( $value );
        }

        $this->set_uri( $this->base_uri() . $this->api() );
        $this->set_recordSchema( 'info:srw/schema/1/' . $this->schema() );

        // Build a hash of all the request params:
        $params = array();
        // TODO: Don't like this repetition of so many property names.
        foreach (array('startRecord','maximumRecords','servicelevel','recordSchema','wskey','query') as $param) {
            $params[$param] = $this->$param();
        }
        $this->set_params( $params );
    }

} // end class WorldCatSearch_Request
