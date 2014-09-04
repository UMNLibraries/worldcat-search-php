<?php

namespace UmnLib\Core\WorldCatSearch;

use UmnLib\Core\ArgValidator;
use UmnLib\Core\File\Set\DateSequence;

class Client
{
  protected $requestIterator;
  function setRequestIterator($requestIterator)
  {
    // TODO: Fix inheritance of the *Iterator classes so that this will work:
        /*
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => WorldCatSearch_Request_Iterator))
        );
         */
    $this->requestIterator = $requestIterator;
  }

  protected $userAgent;
  function setUserAgent($userAgent)
  {
        /* TODO: Implement a UserAgent base class to make this validation possible.
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => UserAgent))
        );
         */
    $this->userAgent = $userAgent;
  }

  protected $fileSet;
  function setFileSet($fileSet)
  {
    // TODO: Why does the following fail? Probably a bug in ArgValidator.
    //ArgValidator::validate($fileSet, array('instanceof' => '\UmnLib\Core\Set'));
    ArgValidator::validate(array($fileSet), array(array('instanceof' => '\UmnLib\Core\Set')));
    $this->fileSet = $fileSet;
  }

  function __construct(Array $args)
  {
    $validatedArgs = ArgValidator::validate(
      $args,
      array('requestIterator' => array('required' => true))
    );

    foreach ($validatedArgs as $property => $value) {
      $mutator = 'set' . ucfirst($property);
      $this->$mutator($value);
    }

    if ($this->userAgent() === null) {
      $refClass = new \ReflectionClass(get_class($this->requestIterator()));
      if (!$refClass->hasProperty('userAgent')) {
        // TODO: Bad assumption here that this method will exist, due to use of magic methods:
        $userAgent = $this->requestIterator()->userAgent();
      } else {
        $userAgent = new UserAgent();
      }
      $this->setUserAgent($userAgent);
    }
  }

  public function search()
  {
    $ua = $this->userAgent();

    $ri = $this->requestIterator();
    $ri->rewind();

    while ($ri->valid()) {
      $request = $ri->current();
      try {
        // TODO: Make this a proper response object!!!
        $response = $ua->send($request);
      } catch (\Exception $e) {
        error_log($e->getMessage());
      }
      if (!isset($e)) {
        // TODO: Support for just printing to STDOUT?
        $filename = $this->fileSet()->add(); 
        file_put_contents($filename, $response);
        // TODO: What to do about this? What to return, if anything?
        //$filenames[] = $filename;
      }
      $ri->next();
    }
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
