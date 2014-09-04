<?php

namespace UmnLib\Core\WorldCatSearch;

use UmnLib\Core\ArgValidator;

class RequestIterator implements \Iterator
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

  protected $query;
  protected function setQuery($query)
  {
    ArgValidator::validate($query, array('is' => 'string'));
    $this->query = $query;
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

  //...based on these (from WorldCatSearch_Client):

  // 'maxRecords' is a limit on the total number of records to extract.
  // If not set, will default to worldCatMaxRecords.
  protected $maxRecords;
  protected function setMaxRecords($maxRecords)
  {
    ArgValidator::validate($maxRecords, array('is' => 'int'));
    $this->maxRecords = $maxRecords;
  }

  // The WorldCat API will return no more than this number of records for a single query:
  protected $worldCatMaxRecords = 10000;
  // TODO: Probably should change this to a constant, and shouldn't have a set_ method.
  protected function setWorldCatMaxRecords($worldCatMaxRecords)
  {
    ArgValidator::validate( $worldCatMaxRecords, array('is' => 'int') );
    $this->worldCatMaxRecords = $worldCatMaxRecords;
  }

  // TODO: WorldCat seems to have a maximum of 50 records per download. Investigate...
  //self::has('maximumRecords', array('is' => 'protected', 'default' => 50,)); // 50 is max-per-request allowed by WorldCat.
  protected $recordsPerDownload = 50;
  protected function setRecordsPerDownload($recordsPerDownload)
  {
    ArgValidator::validate($recordsPerDownload, array('is' => 'int'));
    $this->recordsPerDownload = $recordsPerDownload;
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

/* TODO: How did I get two queryRecordCount's?
    protected $queryRecordCount = 0;
    protected function setQueryRecordCount($queryRecordCount)
    {
        ArgValidator::validate(
            func_get_args(), array(array('is' => 'int'))
        );
        $this->queryRecordCount = $queryRecordCount;
    }
 */

  // This will be calulated in __construct():
  protected $queryRecordCount = 0;
  protected function setQueryRecordCount($queryRecordCount)
  {
    ArgValidator::validate($queryRecordCount, array('is' => 'int'));
    $this->queryRecordCount = $queryRecordCount;
  }

  // This will be calulated in __construct():
  protected $maxPossibleDesiredRecord = 0;
  protected function setMaxPossibleDesiredRecord($maxPossibleDesiredRecord)
  {
    ArgValidator::validate($maxPossibleDesiredRecord, array('is' => 'int'));
    $this->maxPossibleDesiredRecord = $maxPossibleDesiredRecord;
  }

  function __construct(Array $args)
  {
    $validatedArgs = ArgValidator::validate(
      $args,
      array(
        'wskey' => array('required' => true),
        'query' => array('required' => true),
      )
    );

    foreach ($validatedArgs as $property => $value) {
      $mutator = 'set' . ucfirst($property);
      $this->$mutator($value);
    }

    // TODO: WHY is this the only thing that causes worldCatMaxRecords
    // to get set, by throwing an exception?
    //$this->setWorldCatMaxRecords('bogus');

    $worldCatMaxRecords = $this->worldCatMaxRecords();
    if ($this->maxRecords() === null) {
      $this->setMaxRecords($worldCatMaxRecords);
    }

    if ($this->maxRecords() > $worldCatMaxRecords) {
      error_log("Since the WorldCat API will return no more than $worldCatMaxRecords records for a single query, resetting maxRecords to $worldCatMaxRecords.");
      $this->setMaxRecords($worldCatMaxRecords);
    }

    if ($this->userAgent() === null) {
      $this->setUserAgent(new UserAgent());
    }

    // Get the total count of records for this query:
    $query = $this->query();
    $request = new Request(array(
      'api' => $this->api(),
      'query' => $query,
      'wskey' => $this->wskey(),
      'startRecord' => 1,
      'maximumRecords' => 50, // Previously set this to 0, but that caused WorldCat to return an inflated count.
    ));
    try {
      $result = $this->userAgent()->send($request);
    } catch (\Exception $e) {
      throw new \RuntimeException("Failed to get record count: " . $e->getMessage());
    }

    $parsedResult = new \SimpleXMLElement($result);

    if ($parsedResult->diagnostics) {
      $message =  $parsedResult->diagnostics->diagnostic->message;
      $details =  $parsedResult->diagnostics->diagnostic->details;
      $errorMessage = "WorldCat API threw an exception: message: '$message'; details: '$details'\n";
      throw new \RuntimeException($errorMessage);
    }

    //$this->setQueryRecordCount((int) $parsedResult->numberOfRecords);
    $queryRecordCount = (int) $parsedResult->numberOfRecords;

    if ($queryRecordCount > $worldCatMaxRecords) {
      error_log("WorldCat contains $queryRecordCount records that match your query, '$query', but the WorldCat API will return no more than $worldCatMaxRecords records for a single query.");
    }

    // Calculate the effective last record index:
    $maxRecords = $this->maxRecords();
    $maxPossibleDesiredRecord = ($maxRecords > $queryRecordCount) ? $queryRecordCount : $maxRecords; 

    $this->setQueryRecordCount($queryRecordCount);
    $this->setMaxPossibleDesiredRecord($maxPossibleDesiredRecord);
  }

  // Iterator interface implementation:

  public function rewind()
  {
    // Make sure the iterator is never valid if there are no records:
    $this->valid = $this->maxPossibleDesiredRecord() > 0 ? true : false;

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

    $request = new Request(array(
      'api' => $this->api(),
      'query' => $this->query(),
      'wskey' => $this->wskey(),
      'startRecord' => $startRecord,
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
