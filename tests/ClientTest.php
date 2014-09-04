<?php

namespace UmnLib\Core\Tests\WorldCatSearch;

use \UmnLib\Core\WorldCatSearch\Client;
use \UmnLib\Core\WorldCatSearch\Request;
use \UmnLib\Core\WorldCatSearch\RequestIterator;
use \UmnLib\Core\File\Set\DateSequence;

class ClientTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->wskey = getenv('WSKEY');
    $this->fileSet = new DateSequence(array(
      'directory' => dirname(__FILE__) . '/downloads',
      'suffix' => '.xml',
    ));
  }

  function testNew()
  {
    $maxRecords = 15;
    $recordsPerDownload = 5;

    $ri = new RequestIterator(array(
      'wskey' => $this->wskey,
      'query' => 'srw.kw="civil war"',
      'maxRecords' => $maxRecords,
      'recordsPerDownload' => $recordsPerDownload,
    ));

    $c = new Client(array(
      'fileSet' => $this->fileSet,
      'requestIterator' => $ri,
    ));
    $this->assertInstanceOf('\UmnLib\Core\WorldCatSearch\Client', $c);

    // Clean out any already-existing files, e.g. from previous test runs:
    $this->fileSet->clear();
    $c->search();

    $filenames = $this->fileSet->members();
    $this->assertEquals(3, count($filenames));

    $downloadCount = 0;
    foreach ($filenames as $filename) {
      $xml = simplexml_load_file( $filename );
      $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
      $fileCount = count($xml->xpath('//marc:record'));
      // Note: This may fail if the recordsPerDownload is not a factor of maxRecords.
      $this->assertEquals($recordsPerDownload, $fileCount);
      $downloadCount += $fileCount;
    }
    // Note: This may fail if the search matches fewer records than maxRecords.
    $this->assertEquals($maxRecords, $downloadCount);

    // Cleanup:
    $this->fileSet->clear();
  }

  function testCount()
  {
    // As of 2009-10-19, this search returns 10 records, and the client
    // contained a bug that caused no records to be returned when
    // $recordsPerDownload > $total_search_records.
    $recordsPerDownload = 20; // TODO: No records downloaded!!!
    $ri = new RequestIterator(array(
      'wskey' => $this->wskey,
      'query' =>  'srw.bn=8885091016 or srw.bn=0545010225',
      'recordsPerDownload' => $recordsPerDownload,
    ));

    $c = new Client(array(
      'fileSet' => $this->fileSet,
      'requestIterator' => $ri,
    ));

    // Clean out any already-existing files, e.g. from previous test runs:
    $this->fileSet->clear();

    $c->search();
    $filenames = $this->fileSet->members();

    $this->assertGreaterThan(0, count($filenames));
    foreach ($filenames as $filename) {
      $xml = simplexml_load_file($filename);
      $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
      $fileCount = count($xml->xpath('//marc:record'));
      $this->assertLessThanOrEqual($recordsPerDownload, $fileCount);
    }

    // Cleanup:
    $this->fileSet->clear();
  }
}
