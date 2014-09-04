<?php

namespace UmnLib\Core\WorldCatSearch;

use UmnLib\Core\ArgValidator;

class RequestIteratorOclcIdList extends RequestIterator
{
  // From WorldCatSearch\Request:
  protected $api = 'sru';
  protected function setApi($api)
  {
    ArgValidator::validate(
      $api,
      array('is' => 'string', 'regex' => '/^(sru|opensearch)$/i')
    );
    $this->api = $api;
  }

  // Queries will be generated based on this:
  protected $oclcIdList;
  protected function setOclcIdList(array $oclcIdList)
  {
    // TODO: Add array[int] validation?
    $this->oclcIdList = $oclcIdList;
  }

  protected $wskey;
  protected function setWskey($wskey)
  {
    ArgValidator::validate($wskey, array('is' => 'string'));
    $this->wskey = $wskey;
  }

  protected $servicelevel = 'full';
  protected function setServicelevel($servicelevel)
  {
    ArgValidator::validate(
      $servicelevel,
      array('is' => 'string', 'regex' => '/^(default|full)$/i')
    );
    $this->servicelevel = $servicelevel;
  }

  protected $schema = 'marcxml';
  protected function setSchema($schema)
  {
    ArgValidator::validate(
      $schema,
      array('is' => 'string', 'regex' => '/^(dc|marcxml)$/i')
    );
    $this->schema = $schema;
  }

  // This class should generate these automatically...
  protected $startRecord = 1;
  protected function setStartRecord($startRecord)
  {
    ArgValidator::validate($startRecord, array('is' => 'int'));
    $this->startRecord = $startRecord;
  }

  //...based on these (from WorldCatSearch\Client):

  // TODO: WorldCat seems to have a maximum of 50 records per download. Investigate...
  //self::has('maximumRecords', array('is' => 'protected', 'default' => 50,)); // 50 is max-per-request allowed by WorldCat.
  protected $recordsPerDownload = 50;
  protected function setRecordsPerDownload($recordsPerDownload)
  {
    ArgValidator::validate($recordsPerDownload, array('is' => 'int'));
    $this->recordsPerDownload = $recordsPerDownload;
  }

  // This will be calulated in __construct():
  protected $maxPossibleDesiredRecord = 0;
  protected function setMaxPossibleDesiredRecord($maxPossibleDesiredRecord)
  {
    ArgValidator::validate($maxPossibleDesiredRecord, array('is' => 'int'));
    $this->maxPossibleDesiredRecord = $maxPossibleDesiredRecord;
  }

  protected $userAgent;
  protected function setUserAgent($userAgent)
  {
        /* TODO: Implement a UserAgent base class to make this validation possible.
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => UserAgent))
        );
         */
    $this->userAgent = $userAgent;
  }

  protected $currentIteration = 0;
  protected function setCurrentIteration($currentIteration)
  {
    ArgValidator::validate($currentIteration, array('is' => 'int'));
    $this->currentIteration = $currentIteration;
  }

  function __construct(Array $args)
  {
    $validatedArgs = ArgValidator::validate(
      $args,
      array(
        'wskey' => array('required' => true),
        'oclcIdList' => array('required' => true),
      )
    );

    foreach ($validatedArgs as $property => $value) {
      $mutator = 'set' . ucfirst($property);
      $this->$mutator($value);
    }

    if ($this->userAgent() === null) {
      $this->setUserAgent(new UserAgent());
    }

    $this->setMaxPossibleDesiredRecord(count($this->oclcIdList()));
  }

  // Iterator interface implementation:

  public function rewind()
  {
    // Make sure the iterator is never valid if there are no records:
    $this->valid = count($this->oclcIdList()) > 0 ? true : false;

    // TODO: Add this to the "parent" class!!!
    $this->setCurrentIteration(0);

    $this->bootstrapped = false;
  }

  function current()
  {
    // Given Iterator's goofy method call order,
    // in which it calls current() before next(),
    // we have to bootstrap current so that it 
    // will have a value for the first loop iteration.
    if (!$this->bootstrapped) {
      $this->bootstrapped = true;
      $this->nextRequest();

    }
    return $this->current;
  }

  protected function setKey($key)
  {
    $this->currentKey = $key;
  }

  function key()
  {
    return $this->currentKey;
  }

  function next()
  {
    $this->nextRequest();
  }

  function nextRequest()
  {
    $recordsPerDownload = $this->recordsPerDownload();
    $startRecord = $this->startRecord() + ($recordsPerDownload * $this->currentIteration());

    $maxPossibleDesiredRecord = $this->maxPossibleDesiredRecord();

    if ($startRecord > $maxPossibleDesiredRecord) {
      $this->valid = false;
      unset($this->current);
      return false;
    }

    // Calculate maximumRecords:
    $maximumRecords = $recordsPerDownload;
    $endRecord = $startRecord + $recordsPerDownload -1;
    if ($endRecord > $maxPossibleDesiredRecord) {
      $maximumRecords = $maxPossibleDesiredRecord - $startRecord + 1;
    }

    // Generate the query:
    $oclcIds = array_slice($this->oclcIdList(), $startRecord - 1, $maximumRecords);
    $queryExpressions = array_map(create_function('$id', 'return "srw.no all \"$id\"";'), $oclcIds);
    $query = join(' or ', $queryExpressions);

    $request = new Request(array(
      'api' => $this->api(),
      'query' => $query,
      'wskey' => $this->wskey(),
      //'startRecord' => $startRecord,
      'startRecord' => 1,
      'maximumRecords' => $maximumRecords,
    ));
    $this->current = $request;

    // Increment for the next iteration:    
    $this->setCurrentIteration($this->currentIteration() + 1);

    return true;
  }

  function valid()
  {
    return $this->valid;
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
