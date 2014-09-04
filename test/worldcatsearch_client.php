#!/usr/bin/php -q
<?php

require_once 'simpletest/autorun.php';
SimpleTest :: prefer(new TextReporter());
set_include_path('../php' . PATH_SEPARATOR . get_include_path());
require_once 'WorldCatSearch/Request.php';
require_once 'WorldCatSearch/Request/Iterator.php';
require_once 'WorldCatSearch/Client.php';
require_once 'File/Set/DateSequence.php';

ini_set('memory_limit', '512M');

//error_reporting( E_STRICT );

class WorldCatSearchClientTest extends UnitTestCase
{
    public function __construct()
    {
        $this->wskey = 'your-wskey-here';
        $this->file_set = new File_Set_DateSequence(array(
            'directory' => getcwd() . '/downloads',
            'suffix' => '.xml',
        ));
    }

    public function test_new()
    {
        $this->max_records = 15;
        $this->records_per_download = 5;
        $this->query =  'srw.kw="civil war"';

        $ri = new WorldCatSearch_Request_Iterator(array(
            'wskey' => $this->wskey,
            'query' => $this->query,
            'max_records' => $this->max_records,
            'records_per_download' => $this->records_per_download,
        ));

        $c = new WorldCatSearch_Client(array(
            'file_set' => $this->file_set,
            'request_iterator' => $ri,
        ));
        $this->assertIsA( $c, 'WorldCatSearch_Client' );
        $this->client = $c;
    }

    public function test_client()
    {
        $c = $this->client;

        // Clean out any already-existing files, e.g. from previous test runs:
        $this->file_set->clear();
        $c->search();

        $file_names = $this->file_set->members();
        $this->assertTrue( count($file_names) == 3 );

        $download_count = 0;
        foreach ($file_names as $file_name) {
            $xml = simplexml_load_file( $file_name );
            $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
            $file_count = count( $xml->xpath('//marc:record') );
            // Note: This may fail if the records_per_download is not a factor of max_records.
            $this->assertTrue( $file_count == $this->records_per_download );
            $download_count += $file_count;
        }
        // Note: This may fail if the search matches fewer records than max_records.
        $this->assertTrue( $download_count == $this->max_records );

        // Cleanup:
        $this->file_set->clear();
    }

    public function test_count()
    {
        // As of 2009-10-19, this search returns 10 records, and the client
        // contained a bug that caused no records to be returned when
        // $records_per_download > $total_search_records.
        $records_per_download = 20; // TODO: No records downloaded!!!
        $ri = new WorldCatSearch_Request_Iterator(array(
            'wskey' => $this->wskey,
            'query' =>  'srw.bn=8885091016 or srw.bn=0545010225',
            'records_per_download' => $records_per_download,
        ));

        $c = new WorldCatSearch_Client(array(
            'file_set' => $this->file_set,
            'request_iterator' => $ri,
        ));

        // Clean out any already-existing files, e.g. from previous test runs:
        $this->file_set->clear();

        $c->search();
        $file_names = $this->file_set->members();

        $this->assertTrue( count($file_names) > 0 );
        foreach ($file_names as $file_name) {
            $xml = simplexml_load_file( $file_name );
            $xml->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');
            $file_count = count( $xml->xpath('//marc:record') );
            $this->assertTrue( $file_count <= $records_per_download );
        }

        // Cleanup:
        $this->file_set->clear();
    }

} // end class WorldCatSearchClientTest
