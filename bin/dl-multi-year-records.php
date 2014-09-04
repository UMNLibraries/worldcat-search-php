#!/usr/bin/php -q
<?php

/* I probably included this file in the repo because it was
 * easier to do one-off tasks from within the repo directory when
 * I was using SimpleTest, before the days of Composer and
 * PHPUnit. Keeping it for now, because it serves as an example
 * of using this package. -- David Naughton
 */

require dirname(__FILE__) . '/../vendor/autoload.php';

use \UmnLib\Core\WorldCatSearch\Client;
use \UmnLib\Core\WorldCatSearch\UserAgent;
use \UmnLib\Core\WorldCatSearch\RequestIteratorOclcIdList;
use \UmnLib\Core\File\Set\DateSequence;

$env = getPhpUnitEnvVars(file_get_contents(dirname(__FILE__) . '/../phpunit.xml'));
$directory = (array_key_exists('1', $argv) && is_dir($argv[1])) ? $argv[1] : '.';

$ri = new RequestIteratorOclcIdList(array(
    'wskey' => $env['WSKEY'],
    'oclcIdList' => array('43468778','42726577'),
));

$fileSet = new DateSequence(array(
    'directory' => $directory,
    'suffix' => '.xml',
));

$c = new Client(array(
    'userAgent' => new UserAgent(),
    'requestIterator' => $ri,
    'fileSet' => $fileSet,
));

$c->search();

function getPhpUnitEnvVars($phpUnitXml)
{
  $xml = new \SimpleXMLElement($phpUnitXml);
  $env = array();
  foreach ($xml->php->env as $envElem) {
    unset($name, $value);
    foreach($envElem->attributes() as $k => $v) {
      $stringv = (string) $v;
      if ($k == 'name') {
        $name = $stringv;
      } else if ($k == 'value') {
        $value = $stringv;
      }
    }
    $env[$name] = $value;
  }
  return $env;
}
