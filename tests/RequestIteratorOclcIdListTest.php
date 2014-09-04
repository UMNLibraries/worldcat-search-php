<?php

namespace UmnLib\Core\Tests\WorldCatSearch;

use \UmnLib\Core\WorldCatSearch\RequestIteratorOclcIdList;

class RequestIteratorOclcIdListTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->wskey = getenv('WSKEY');
  }

  function testQueryReturnsGreaterThanWorldCatMax()
  {
    $iterator = new RequestIteratorOclcIdList(array(
      'wskey' => $this->wskey,
      'oclcIdList' => array('35007366','37175264','48536081',),
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\RequestIteratorOclcIdList', $iterator);

    $this->verifyCounts($iterator, array(1), array(3));
  }

  protected function verifyCounts($iterator, $expectedStartRecords, $expectedMaxRecords)
  {
    $iterator->rewind();

    $startRecords = array();
    $maximumRecords = array();

    while ($iterator->valid()) {
      $request = $iterator->current();

      // Sanity check that we're getting a _Request object:
      // PHP array was successful:
      $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\Request', $request);

      //print_r( $request );
      $startRecords[] = $request->startRecord();
      $maximumRecords[] = $request->maximumRecords();

      $iterator->next();
    }

    $this->assertEquals($expectedStartRecords, $startRecords);
    $this->assertEquals($expectedMaxRecords, $maximumRecords);
  }
}
