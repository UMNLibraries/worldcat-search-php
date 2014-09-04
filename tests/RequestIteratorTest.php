<?php

namespace UmnLib\Core\Tests\WorldCatSearch;

use \UmnLib\Core\WorldCatSearch\RequestIterator;

class RequestIteratorTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->wskey = getenv('WSKEY');
  }

  function testQueryReturnsGreaterThanWorldCatMax()
  {
    $iterator = new RequestIterator(array(
      'wskey' => $this->wskey,
      'query' => 'srw.kw="civil war"',
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $this->assertGreaterThan($iterator->worldCatMaxRecords(), $iterator->queryRecordCount());
    $this->assertEquals($iterator->worldCatMaxRecords(), $iterator->maxPossibleDesiredRecord());
  }

  /**
   * @expectedException \RuntimeException
   */
  function testInvalidQuery()
  {
    $iterator = new RequestIterator(array(
      'wskey' => $this->wskey,
      'query' => 'srw.bogus="civil war"',
    ));
  }

  function testEvenlyDivisibleMaxRecords()
  {
    $iterator = new RequestIterator(array(
      'api' => 'sru',
      'wskey' => $this->wskey,
      'query' => 'srw.ti="A People\'s History of the United States"',
      'recordsPerDownload' => 3,
      'maxRecords' => 15,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);
    $this->verifyCounts($iterator, array(1,4,7,10,13), array(3,3,3,3,3));
  }

  function testQueryReturnsLessThanRecordsPerDownload()
  {
    $iterator = new RequestIterator(array(
      'wskey' => $this->wskey,
      // This is a little fragile, because we are depending on this search returning < 50 records.
      // That may not always be true.
      'query' => 'srw.ti="Mathematical Cranks"',
      //'recordsPerDownload' => 50, // 50 == default
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $queryRecordCount = $iterator->queryRecordCount();

    // sanity check:
    $this->assertLessThan($iterator->recordsPerDownload(), $queryRecordCount);
    //echo "queryRecordCount = $queryRecordCount\n";

    $this->verifyCounts($iterator, array(1), array($queryRecordCount));
  }

  function testNonEvenlyDivisibleMaxRecords()
  {
    // Last request for 1 record:
    $iterator = new RequestIterator(array(
      'api' => 'sru',
      'wskey' => $this->wskey,
      'query' => 'srw.ti="A People\'s History of the United States"',
      'recordsPerDownload' => 3,
      'maxRecords' => 16,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $this->verifyCounts($iterator, array(1,4,7,10,13,16,), array(3,3,3,3,3,1,));

    // Last request for half of recordsPerDownload:
    unset($iterator);
    $iterator = new RequestIterator(array(
      'api' => 'sru',
      'wskey' => $this->wskey,
      'query' => 'srw.ti="A People\'s History of the United States"',
      'recordsPerDownload' => 10,
      'maxRecords' => 25,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $this->verifyCounts($iterator, array(1,11,21,), array(10,10,5,));

    // Last request for 1-less than recordsPerDownload:
    unset($iterator);
    $iterator = new RequestIterator(array(
      'api' => 'sru',
      'wskey' => $this->wskey,
      'query' => 'srw.ti="A People\'s History of the United States"',
      'recordsPerDownload' => 10,
      'maxRecords' => 29,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $this->verifyCounts($iterator, array(1,11,21,), array(10,10,9,));
  }

  function testZeroMaxRecords()
  {
    $iterator = new RequestIterator(array(
      'api' => 'sru',
      'wskey' => $this->wskey,
      'query' => 'srw.ti="A People\'s History of the United States"',
      'recordsPerDownload' => 3,
      'maxRecords' => 0,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIterator', $iterator);

    $this->verifyCounts($iterator, array(), array());
  }

  protected function verifyCounts($iterator, $expectedStartRecords, $expectedMaxRecords)
  {
    $iterator->rewind();

    $startRecords = array();
    $maximumRecords = array();

    while ($iterator->valid()) {
      $request = $iterator->current();

      // Sanity check that we're getting a Request object:
      // PHP array was successful:
      $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\Request', $request);

      //print_r($request);
      $startRecords[] = $request->startRecord();
      $maximumRecords[] = $request->maximumRecords();

      $iterator->next();
    }

    $this->assertEquals($expectedStartRecords, $startRecords);
    $this->assertEquals($expectedMaxRecords, $maximumRecords);
  }
}
