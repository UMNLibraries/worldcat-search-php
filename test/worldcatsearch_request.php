#!/usr/bin/php -q
<?php

require_once 'simpletest/autorun.php';
SimpleTest :: prefer(new TextReporter());
set_include_path('../php' . PATH_SEPARATOR . get_include_path());
require_once 'WorldCatSearch/Request.php';

ini_set('memory_limit', '512M');

//error_reporting( E_STRICT );

class WorldCatSearchRequestTest extends UnitTestCase
{
    public $wskey = 'your-wskey-here';

    public function test_new()
    {
        $r = new WorldCatSearch_Request(array(
            'wskey' => $this->wskey,
            'query' => 'srw.kw="civil war"',
        ));
        $this->assertIsA($r, 'WorldCatSearch_Request');

        // Test that the defaults were set correctly:
        $this->assertEqual($r->api(), 'sru');
        $this->assertEqual($r->base_uri(), 'http://www.worldcat.org/webservices/catalog/search/');
        $this->assertEqual($r->uri(), 'http://www.worldcat.org/webservices/catalog/search/sru');
        $this->assertEqual($r->method(), 'GET');
        $this->assertEqual($r->servicelevel(), 'full');
        $this->assertEqual($r->schema(), 'marcxml');
        $this->assertEqual($r->recordSchema(), 'info:srw/schema/1/marcxml');
        $this->assertEqual($r->startRecord(), 1);
        $this->assertEqual($r->maximumRecords(), 50);
        $this->assertEqual(
            $r->params(),
            array (
              'startRecord' => 1,
              'maximumRecords' => 50,
              'servicelevel' => 'full',
              'recordSchema' => 'info:srw/schema/1/marcxml',
              'wskey' => $this->wskey,
              'query' => 'srw.kw="civil war"',
            )
        );

        // Test overriding of defaults:
        $r = new WorldCatSearch_Request(array(
            'wskey' => $this->wskey,
            'query' => 'srw.kw="civil war"',
            'api' => 'opensearch',
            'servicelevel' => 'default',
            'schema' => 'dc',
            'startRecord' => 33,
            'maximumRecords' => 10,
        ));
        $this->assertEqual($r->api(), 'opensearch');
        $this->assertEqual($r->servicelevel(), 'default');
        $this->assertEqual($r->schema(), 'dc');
        $this->assertEqual($r->startRecord(), 33);
        $this->assertEqual($r->maximumRecords(), 10);
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
} // end class WorldCatSearchRequestTest
