<?php

namespace UmnLib\Core\WorldCatSearch;

use UmnLib\Core\ArgValidator;
use Guzzle\Http\Url;
use Guzzle\Http\QueryString;

/*
require_once 'HTTP/Request.php';
require_once 'ArgValidator.php';
 */

// Called "UserAgent" because the API is inspired by Perl's LWP (i.e. libwww-perl).
class UserAgent
{
  protected $httpClient;
  function httpClient()
  {
    if (isset($this->httpClient)) return $this->httpClient;
    $httpClient = new \Guzzle\Http\Client();
    $this->httpClient = $httpClient;
    return $httpClient;
  }

  // This class just makes multiple attempts at sending the same request.
  protected $maxAttempts = 10;
  function setMaxAttempts($maxAttempts)
  {
    ArgValidator::validate( $maxAttempts, array('is' => 'int') );
    $this->maxAttempts = $maxAttempts;
  }

  // Add more as we need them.
  /*
  protected $methodConstantMap = array('GET' => HTTP_REQUEST_METHOD_GET,);
  function setMethodConstantMap($methodConstantMap)
  {
    ArgValidator::validate(
      $methodConstantMap,
      array('is' => 'array')
    );
    $this->methodConstantMap = $methodConstantMap;
  }
   */

  function __construct()
  {
    $validatedArgs = ArgValidator::validate(
      func_get_args(),
      array(
        'maxAttempts' => array('required' => false),
        // Maybe add httpClient here instead?
        //'methodConstantMap' => array('required' => false),
      )
    );

    foreach ($validatedArgs as $property => $value) {
      $mutator = 'set' . ucfirst($property);
      $this->$mutator($value);
    }
  }

  // LWP::UserAgent calls this method "request".
  public function send($request)
  {
    /*
    $http = new HTTP_Request($request->uri());
    $methodConstantMap = $this->methodConstantMap();
    $methodConstant = $methodConstantMap[$request->method()];
    $http->setMethod($methodConstant);

    $params = $request->params();
    foreach ($params as $k => $v) {
      $http->addQueryString($k, $v);
    }
    */

    $url = Url::factory($request->uri());
    $queryString = new QueryString();
    // Allow multiple instances of the same key (Do we really need this for WorldCat?):
    $queryString->setAggregator(new \Guzzle\Http\QueryAggregator\DuplicateAggregator());
    $params = $request->params();
    foreach ($params as $k => $v) {
      $queryString->add($k, $v);
    }
    $url->setQuery($queryString);
    $request = $this->httpClient()->get($url);

    $requestFailed = true;

    for ($i = 1; $i <= $this->maxAttempts(); $i++) {
      /*
      unset($status);
      $status = $http->sendRequest();
      // This certainly needs tests!
      if (PEAR::isError($status)) { 
        error_log("Attempt $i: " . $status->getMessage()); 
        continue;
      } 
      */

      unset($response);
      $response = $request->send();

      // TODO: Add parsing of response to look for errors,
      // and throw exceptions if necessary.
      //$code = (int) $http->getResponseCode();
      $statusCode = $response->getStatusCode();
      if ($statusCode > 499 and $statusCode < 600) {
        // Can't I get the "reason" message here, too? I can in HTTP_Request2.
        error_log("Attempt $i: WorldCat server response: $code");
        continue;
      }
      $requestFailed = false;
      break;
    }
    if ($requestFailed) {
      throw new \RuntimeException(
        // TODO: add a $request->as_string() method in here so we can identify the request better?
        "Giving up on request after " . $this->maxAttempts() . " attempts."
      );
    }

    return $response->getBody();
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
