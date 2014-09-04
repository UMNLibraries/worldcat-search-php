<?php

namespace UmnLib\Core\Tests\WorldCatSearch;

use \UmnLib\Core\WorldCatSearch\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->wskey = getenv('WSKEY');
  }

  function testNew()
  {
    $r = new Request(array(
      'wskey' => $this->wskey,
      'query' => 'srw.kw="civil war"',
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\Request', $r);

    // Test that the defaults were set correctly:
    $this->assertEquals('sru', $r->api());
    $this->assertEquals('http://www.worldcat.org/webservices/catalog/search/', $r->baseUri());
    $this->assertEquals('http://www.worldcat.org/webservices/catalog/search/sru', $r->uri());

    // Do I still need this?
    //$this->assertEquals('GET', $r->method());

    $this->assertEquals('full', $r->servicelevel());
    $this->assertEquals('marcxml', $r->schema());
    $this->assertEquals('info:srw/schema/1/marcxml', $r->recordSchema());
    $this->assertEquals(1, $r->startRecord());
    $this->assertEquals(50, $r->maximumRecords());
    $this->assertEquals(
      array (
        'startRecord' => 1,
        'maximumRecords' => 50,
        'servicelevel' => 'full',
        'recordSchema' => 'info:srw/schema/1/marcxml',
        'wskey' => $this->wskey,
        'query' => 'srw.kw="civil war"',
      ),
      $r->params()
    );
  }

  function testOverrides()
  {
    // Test overriding of defaults:
    $r = new Request(array(
      'wskey' => $this->wskey,
      'query' => 'srw.kw="civil war"',
      'api' => 'opensearch',
      'servicelevel' => 'default',
      'schema' => 'dc',
      'startRecord' => 33,
      'maximumRecords' => 10,
    ));
    $this->assertEquals('opensearch', $r->api());
    $this->assertEquals('default', $r->servicelevel());
    $this->assertEquals('dc', $r->schema());
    $this->assertEquals(33, $r->startRecord());
    $this->assertEquals(10, $r->maximumRecords());
  }

  /* TODO: The following are tests from the old, Moose-based API. If we still need them
   * they should go somewhere else:

   public function test_request()
   {
     echo "wskey = {$this->wskey}\n";

     $max_records = 3;
     $result = $this->r->send(array(
       'wskey' => $this->wskey,
       'query' => 'srw.kw="civil war"',
       'startRecord' => 1,
       'maximumRecords' => $max_records,
     ));
     $xml = simplexml_load_string( $result );

     // TODO: This will fail on PHP < 5.2.0! :(
     $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
     $download_count = count( $xml->xpath('//marc:record') );
     $this->assertTrue( $download_count == $max_records );

     $count = (int) $xml->numberOfRecords;
     $this->assertPattern('/^\d+$/', $count);
     $this->assertTrue( $count >= 1 );
}

public function test_isbn_request()
{
  $max_records = 50;
  $r = new WorldCatSearch_Request(array( 'api' => 'sru', ));
  $result = $r->send(array(
    'wskey' => $this->wskey,
    //'query' => 'srw.bn=8885091016', // bioethics book
    //'query' => 'srw.bn=0545010225', // Harry Potter
    'query' => 'srw.bn=8885091016 or srw.bn=0545010225',
    //'startRecord' => 1, // Default is 1.
    //'maximumRecords' => $max_records, // Default is 50, max allowed by WorldCat.
  ));

  $xml = simplexml_load_string( $result );

  // TODO: This will fail on PHP < 5.2.0! :(
  $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
  $download_count = count( $xml->xpath('//marc:record') );
  $this->assertTrue( $download_count <= $max_records );
}
   */
}
