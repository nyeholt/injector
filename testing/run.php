<?php
/**
 * This file belongs to the November framework, an extension of the
 * Zend Framework, written by Marcus Nyeholt <marcus@mikenovember.com>
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to marcus@mikenovember.com so I can send you a copy immediately.
 *
 * @package   November
 * @copyright  Copyright (c) 2006-2007 Marcus Nyeholt (http://mikenovember.com)
 * @version    $Id$
 * @license    New BSD License
 */

ini_set('display_errors', 'On');
ini_set('error_reporting', E_ALL | E_NOTICE);
ini_set('error_log', dirname(__FILE__).'/error.log');

// This is where the common library of helpers is located for Zend Framework things.
// At the moment it's a global path just so it's available for all ZF apps
// $globalLib = 'd:/www/common-php-lib';

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');
require_once('simpletest/mock_objects.php');

function __autoload($class)
{
    
}

if (!function_exists('ifset')) {
	function ifset(&$array, $key, $default = null)
	{
	    if (!is_array($array) && !($array instanceof ArrayAccess)) throw new Exception("Must use an array!");
	    return isset($array[$key]) ? $array[$key] : $default;
	}
}

$groups = array();
$test_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'testcases';

$testcase = ifset($_SERVER['argv'], 1);

if (!$testcase) {
    $testcase = ifset($_GET, 'testcase');
}

$group = new GroupTest('Misc');
create_groups($test_dir, $group);

$reporter = php_sapi_name() == 'cli' ? 'TextReporter' : 'HtmlReporter';
// so we iterate over the directory, creating test groups as we go
// down the list

foreach ($groups as $testGroup) {
    $rp = new $reporter();
    /* @var $testGroup GroupTest */
	$testGroup->run($rp);
}

/**
 * Enter description here...
 *
 * @param unknown_type $path
 * @param GroupTest $toGroup
 */
function create_groups($path, $toGroup) 
{
	global $groups, $testcase;
	
	$over = new DirectoryIterator($path);
	foreach ($over as $directory) {
		
		if (strpos($directory, '.') === 0) {
			continue;
		}
		
		$item_name = $path . DIRECTORY_SEPARATOR . $directory;
		if (is_dir($item_name)) {
			$group_name = str_replace('_testcases_', '', str_replace(DIRECTORY_SEPARATOR, '_', str_replace(dirname(__FILE__), '', $item_name)));
			// create a group and pass it recursively
			$group = new GroupTest($group_name);
			create_groups($item_name, $group);
		} else {
		    if ($testcase != null && $testcase != $directory) continue;
			// Add the testcase to the current group
			/* @var $toGroup GroupTest */
			if (strrpos(strrev($item_name), 'php.') === 0) {
				$toGroup->addTestFile($item_name);
			}
		}
	}
	
	$groups[] = $toGroup;
}


?>