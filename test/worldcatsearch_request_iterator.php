#!/usr/bin/php -q
<?php

require_once 'simpletest/autorun.php';
SimpleTest :: prefer(new TextReporter());
set_include_path('../php' . PATH_SEPARATOR . get_include_path());
require_once 'WorldCatSearch/Request/Iterator.php';

ini_set('memory_limit', '512M');

//error_reporting( E_STRICT );

class WorldCatSearchRequestIteratorTest extends UnitTestCase
{
    public $wskey = 'your-wskey-here';

    public function test_query_returns_greater_than_worldcat_max()
    {
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'wskey' => $this->wskey,
            'query' => 'srw.kw="civil war"',
        ));
        $this->assertIsA($iterator, 'WorldCatSearch_Request_Iterator');

        $this->assertTrue($iterator->query_record_count() > $iterator->worldcat_max_records());
        $this->assertEqual($iterator->max_possible_desired_record(), $iterator->worldcat_max_records());
    }

    public function test_invalid_query()
    {
        $this->expectException();
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'wskey' => $this->wskey,
            'query' => 'srw.bogus="civil war"',
        ));
    }

    public function test_evenly_divisible_max_records()
    {
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'api' => 'sru',
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            'records_per_download' => 3,
            'max_records' => 15,
        ));
        $this->assertIsA( $iterator, 'WorldCatSearch_Request_Iterator' );

        $this->verify_counts($iterator, array(1,4,7,10,13), array(3,3,3,3,3));
    }

    public function test_query_returns_less_than_records_per_download()
    {
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            //'records_per_download' => 50, // 50 == default
        ));
        $this->assertIsA( $iterator, 'WorldCatSearch_Request_Iterator' );

        $query_record_count = $iterator->query_record_count();

        // sanity check:
        $this->assertTrue($query_record_count < $iterator->records_per_download());
        echo "query_record_count = $query_record_count\n";

        $this->verify_counts($iterator, array(1), array($query_record_count));
    }

    public function test_non_evenly_divisible_max_records()
    {
        // Last request for 1 record:
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'api' => 'sru',
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            'records_per_download' => 3,
            'max_records' => 16,
        ));
        $this->assertIsA( $iterator, 'WorldCatSearch_Request_Iterator' );

        $this->verify_counts($iterator, array(1,4,7,10,13,16,), array(3,3,3,3,3,1,));

        // Last request for half of records_per_download:
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'api' => 'sru',
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            'records_per_download' => 10,
            'max_records' => 25,
        ));
        $this->assertIsA( $iterator, 'WorldCatSearch_Request_Iterator' );

        $this->verify_counts($iterator, array(1,11,21,), array(10,10,5,));

        // Last request for 1-less than records_per_download:
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'api' => 'sru',
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            'records_per_download' => 10,
            'max_records' => 29,
        ));
        $this->assertIsA( $iterator, 'WorldCatSearch_Request_Iterator' );

        $this->verify_counts($iterator, array(1,11,21,), array(10,10,9,));
    }

    public function test_zero_max_records()
    {
        $iterator = new WorldCatSearch_Request_Iterator(array(
            'api' => 'sru',
            'wskey' => $this->wskey,
            'query' => 'srw.ti="A People\'s History of the United States"',
            'records_per_download' => 3,
            'max_records' => 0,
        ));
        $this->assertIsA($iterator, 'WorldCatSearch_Request_Iterator');

        $this->verify_counts($iterator, array(), array());
    }

    public function verify_counts($iterator, $expected_start_records, $expected_max_records)
    {
        $iterator->rewind();

        $start_records = array();
        $maximum_records = array();

        while ($iterator->valid()) {
            $request = $iterator->current();

            // Sanity check that we're getting a _Request object:
            // PHP array was successful:
            $this->assertIsA( $request, 'WorldCatSearch_Request' );

            print_r( $request );
            $start_records[] = $request->startRecord();
            $maximum_records[] = $request->maximumRecords();

            $iterator->next();
        }

        $this->assertEqual($start_records, $expected_start_records);
        $this->assertEqual($maximum_records, $expected_max_records);
    }

} // end class WorldCatSearchRequestIteratorTest
