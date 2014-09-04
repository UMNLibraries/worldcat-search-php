#!/usr/bin/php -q
<?php

set_include_path('../php' . PATH_SEPARATOR . get_include_path());
require_once 'WorldCatSearch/Client.php';
require_once 'WorldCatSearch/UserAgent.php';
require_once 'WorldCatSearch/Request/Iterator/OCLCIdList.php';
require_once 'File/Set/DateSequence.php';

ini_set('memory_limit', '512M');

//error_reporting( E_STRICT );

$wskey = 'your-wskey-here';

$file_set = new File_Set_DateSequence(array(
    'directory' => getcwd() . '/multi_year',
    'suffix' => '.xml',
));

$ri = new WorldCatSearch_Request_Iterator_OCLCIdList(array(
    'wskey' => $wskey,
    'oclc_id_list' => array('43468778','42726577'),
));

$c = new WorldCatSearch_Client(array(
    'user_agent' => new WorldCatSearch_UserAgent(),
    'request_iterator' => $ri,
    'file_set' => $file_set,
));

$c->search();
