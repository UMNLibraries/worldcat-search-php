<?php

namespace UmnLib\Core\WorldCatSearch;

use UmnLib\Core\ArgValidator;

class Request
{
  protected $api = 'sru';
  function setApi($api)
  {
    ArgValidator::validate(
      $api,
      array('is' => 'string', 'regex' => '/^(sru|opensearch)$/i')
    );
    $this->api = $api;
  }

  protected $baseUri = 'http://www.worldcat.org/webservices/catalog/search/';
  function setBaseUri($baseUri)
  {
    ArgValidator::validate($baseUri, array('is' => 'string'));
    $this->baseUri = $baseUri;
  }

  // Constructed, in __construct(), from 'api' and 'baseUri' above:
  protected $uri;
  function setUri($uri)
  {
    ArgValidator::validate($uri, array('is' => 'string'));
    $this->uri = $uri;
  }

  protected $method = 'GET';
  function setMethod($method)
  {
    ArgValidator::validate(
      $method,
      // TODO: This isn't all the methods, but I need to look up the others.
      array('is' => 'string', 'regex' => '/^(GET|DELETE|POST|PUT)$/i')
    );
    $this->method = $method;
  }

  protected $query;
  function setQuery($query)
  {
    ArgValidator::validate($query, array('is' => 'string'));
    $this->query = $query;
  }

  protected $wskey;
  function setWskey($wskey)
  {
    ArgValidator::validate($wskey, array('is' => 'string'));
    $this->wskey = $wskey;
  }

  protected $servicelevel = 'full';
  function setServicelevel($servicelevel)
  {
    ArgValidator::validate(
      $servicelevel,
      array('is' => 'string', 'regex' => '/^(default|full)$/i')
    );
    $this->servicelevel = $servicelevel;
  }

  protected $schema = 'marcxml';
  function setSchema($schema)
  {
    ArgValidator::validate(
      $schema,
      array('is' => 'string', 'regex' => '/^(dc|marcxml)$/i')
    );
    $this->schema = $schema;
  }

  // Constructed, in __construct(), based on the value of 'schema' above:
  protected $recordSchema;
  function setRecordSchema($recordSchema)
  {
    ArgValidator::validate($recordSchema, array('is' => 'string'));
    $this->recordSchema = $recordSchema;
  }

  protected $startRecord = 1;
  function setStartRecord($startRecord)
  {
    ArgValidator::validate($startRecord, array('is' => 'int'));
    $this->startRecord = $startRecord;
  }

  // 50 is max-per-request allowed by WorldCat.
  protected $maximumRecords = 50;
  function setMaximumRecords($maximumRecords)
  {
    ArgValidator::validate( $maximumRecords, array('is' => 'int') );
    $this->maximumRecords = $maximumRecords;
  }

  // Constructed, in __construct(), from various other properties:
  protected $params = array();
  function setParams(Array $params)
  {
    // No need to use ArgValidator since PHP type-hinting supports arrays.
    // TODO: Fix ArgValidator so that it supports single array arguments?
    $this->params = $params;
  }

  function __construct(Array $args)
  {
    $validatedArgs = ArgValidator::validate(
      $args,
      array(
        'wskey' => array('required' => true), 
        'query' => array('required' => true)
      )
    );

    foreach ($validatedArgs as $property => $value) {
      $mutator = 'set' . ucfirst($property);
      $this->$mutator($value);
    }

    $this->setUri($this->baseUri() . $this->api());
    $this->setRecordSchema('info:srw/schema/1/' . $this->schema());

    // Build a hash of all the request params:
    $params = array();
    // TODO: Don't like this repetition of so many property names.
    foreach (array('startRecord','maximumRecords','servicelevel','recordSchema','wskey','query') as $param) {
      $params[$param] = $this->$param();
    }
    $this->setParams($params);
  }

  /**
   * @internal
   *           
   * Implements accessor methods.
   *                     
   * @param string $function The function/method name must be the same as the name of the property being accessed.
   * @param array $args Ignored and optional, since we implement only accessors here.
   * @return mixed The value of the property named by $function.
   */
  function __call($function, $args)
  {
    // Since we're handling only accessors here, the function name should
    // be the same as the property name:
    $property = $function;
    $class = get_class($this);
    $refClass = new \ReflectionClass($class);
    if (!$refClass->hasProperty($property)) {
      throw new \Exception("Method '$function' does not exist in class '$class'.");
    }
    return $this->$property;
  }
}
