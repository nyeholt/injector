<?php

namespace November\Tests;

use November\Injection\Injector;
use November\Injection\InjectionCreator;

define('TEST_SERVICES', __DIR__ . '/Services');

class InjectorTest extends \PHPUnit_Framework_TestCase {
	
	public function testBasicInjector() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);
		$config = array(
			array(
				'src' => TEST_SERVICES . '/GlobalNamespaceService.php',
			)
		);

		$injector->load($config);
		$this->assertTrue($injector->hasService('GlobalNamespaceService') != null);

		$myObject = new TestObject();
		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->globalNamespaceService), 'GlobalNamespaceService');
	}

	public function testConfiguredInjector() {
		$injector = new Injector();
		$services = array(
			'AnotherService'	=> array(
				'class' => 'November\Tests\Services\AnotherService',
				'properties' => array('config_property' => 'Value'),
			),
			'SampleService'		=> 'November\Tests\Services\SampleService',
		);

		$injector->load($services);
		$this->assertNotNull($injector->hasService('SampleService'));
		// We expect a false because the 'AnotherService' is actually
		// just a replacement of the SampleService
		$this->assertNotNull($injector->hasService('AnotherService'));

		$item = $injector->get('AnotherService');

		$this->assertEquals('Value', $item->config_property);
	}

	public function testIdToNameMap() {
		$injector = new Injector();
		$services = array(
			'FirstId' => 'November\Tests\Services\AnotherService',
			'SecondId' => 'November\Tests\Services\SampleService',
		);

		$injector->load($services);

		$this->assertNotNull($injector->hasService('FirstId'));
		$this->assertNotNull($injector->hasService('SecondId'));
		
		$this->assertTrue($injector->get('FirstId') instanceof \November\Tests\Services\AnotherService);
		$this->assertTrue($injector->get('SecondId') instanceof \November\Tests\Services\SampleService);
	}

	public function testReplaceService() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);

		$config = array(
			'SampleService' => 'November\Tests\Services\SampleService',
		);

		// load
		$injector->load($config);

		// inject
		$myObject = new TestObject();
		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->sampleService), 'November\Tests\Services\SampleService');

		// also tests that ID can be the key in the array
		$config = array(
			'SampleService' => 'November\Tests\Services\AnotherService',
		);
		// load
		$injector->load($config);

		$injector->inject($myObject);
		$this->assertEquals('November\Tests\Services\AnotherService', get_class($myObject->sampleService));
	}

	public function testAutoSetInjector() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);
		$injector->addAutoProperty('auto', 'somevalue');
		$config = array(
			'SampleService' => 'November\Tests\Services\SampleService',
		);
		
		$injector->load($config);

		$this->assertNotNull($injector->hasService('SampleService'));
		// We expect a false because the 'AnotherService' is actually
		// just a replacement of the SampleService

		$myObject = new TestObject();

		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->sampleService), 'November\Tests\Services\SampleService');
		$this->assertEquals($myObject->auto, 'somevalue');
	}

	public function testSettingSpecificProperty() {
		$injector = new Injector();
		$config = array(
			'AnotherService'	=> 'November\Tests\Services\AnotherService',
			'TestObject'		=> 'November\Tests\TestObject'
		);

		$injector->load($config);
		$injector->setInjectMapping('TestObject', 'sampleService', 'AnotherService');
		$testObject = $injector->get('TestObject');

		$this->assertEquals(get_class($testObject->sampleService), 'November\Tests\Services\AnotherService');
	}

	public function testSettingSpecificMethod() {
		$injector = new Injector();
		$config = array(
			'AnotherService'	=> 'November\Tests\Services\AnotherService',
			'TestObject'		=> 'November\Tests\TestObject'
		);
		
		$injector->load($config);
		$injector->setInjectMapping('TestObject', 'setSomething', 'AnotherService', 'method');

		$testObject = $injector->get('TestObject');

		$this->assertEquals(get_class($testObject->sampleService), 'November\Tests\Services\AnotherService');
	}
	
	public function testInjectingScopedService() {
		$injector = new Injector();
		
		$config = array(
			'AnotherService'				=> 'November\Tests\Services\AnotherService',
			'TestObject'					=> 'November\Tests\TestObject',
			'AnotherService.DottedChild'	=> 'November\Tests\Services\SampleService',
		);
		
		$injector->load($config);
		
		$service = $injector->get('AnotherService.DottedChild');
		$this->assertEquals(get_class($service), 'November\Tests\Services\SampleService');
		
		$service = $injector->get('AnotherService.Subset');
		$this->assertEquals(get_class($service), 'November\Tests\Services\AnotherService');
		
		$injector->setInjectMapping('TestObject', 'sampleService', 'AnotherService.Geronimo');
		$testObject = $injector->get('TestObject');
		$this->assertEquals(get_class($testObject->sampleService), 'November\Tests\Services\AnotherService');
		
		$injector->setInjectMapping('TestObject', 'sampleService', 'AnotherService.DottedChild.AnotherDown');
		$testObject = $injector->get('TestObject', false);
		
		$this->assertEquals(get_class($testObject->sampleService), 'November\Tests\Services\SampleService');
		
	}

	public function testInjectUsingConstructor() {
		$injector = new Injector();
		
		$config = array(
			'AnotherService'				=> 'November\Tests\Services\AnotherService',
			'TestObject'					=> 'November\Tests\TestObject',
			'SampleService'					=> array(
				'class'			=> 'November\Tests\Services\SampleService',
				'constructor'	=> array(
					'val1',
					'val2',
				)
			)
		);

		$injector->load($config);
		$sample = $injector->get('SampleService');
		$this->assertEquals($sample->constructorVarOne, 'val1');
		$this->assertEquals($sample->constructorVarTwo, 'val2');

		$injector = new Injector();
		
		$config = array(
			'AnotherService'				=> 'November\Tests\Services\AnotherService',
			'TestObject'					=> 'November\Tests\TestObject',
			'SampleService'					=> array(
				'class'			=> 'November\Tests\Services\SampleService',
				'constructor'	=> array(
					'val1',
					'%$AnotherService',
				)
			)
		);

		$injector->load($config);
		$sample = $injector->get('SampleService');
		$this->assertEquals($sample->constructorVarOne, 'val1');
		$this->assertEquals(get_class($sample->constructorVarTwo), 'November\Tests\Services\AnotherService');
	}

	public function testInjectUsingSetter() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);
		$config = array('SampleService'	=> 'November\Tests\Services\SampleService',);

		$injector->load($config);
		$this->assertNotNull($injector->hasService('SampleService'));

		$myObject = new OtherTestObject();
		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->s()), 'November\Tests\Services\SampleService');

		// and again because it goes down a different code path when setting things
		// based on the inject map
		$myObject = new OtherTestObject();
		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->s()), 'November\Tests\Services\SampleService');
	}

	// make sure we can just get any arbitrary object - it should be created for us
	public function testInstantiateAnObjectViaGet() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);
		$config = array(
			'SampleService'	=> 'November\Tests\Services\SampleService',
			'OtherTestObject'	=> 'November\Tests\OtherTestObject',
		);

		$injector->load($config);
		$this->assertNotNull($injector->hasService('SampleService'));

		$myObject = $injector->get('OtherTestObject');
		$this->assertEquals(get_class($myObject->s()), 'November\Tests\Services\SampleService');

		// and again because it goes down a different code path when setting things
		// based on the inject map
		$myObject = $injector->get('OtherTestObject');
		$this->assertEquals(get_class($myObject->s()), 'November\Tests\Services\SampleService');
	}

	public function testCircularReference() {
		$services = array(
			'CircularOne'	=> 'November\Tests\CircularOne', 
			'CircularTwo'	=> 'November\Tests\CircularTwo',
			'NeedsBothCirculars'	=> 'November\Tests\NeedsBothCirculars'
		);

		$injector = new Injector($services);
		$injector->setAutoScanProperties(true);

		$obj = $injector->get('NeedsBothCirculars');

		$this->assertTrue($obj->circularOne instanceof CircularOne);
		$this->assertTrue($obj->circularTwo instanceof CircularTwo);
	}

	public function testPrototypeObjects() {
		$services = array(
			'CircularOne'	=> 'November\Tests\CircularOne', 
			'CircularTwo'	=> 'November\Tests\CircularTwo',
			'NeedsBothCirculars'	=> array(
				'class'		=> 'November\Tests\NeedsBothCirculars',
				'type' => 'prototype'
			)
		);
		
		$injector = new Injector($services);
		$injector->setAutoScanProperties(true);
		$obj1 = $injector->get('NeedsBothCirculars');
		$obj2 = $injector->get('NeedsBothCirculars');

		// if this was the same object, then $obj1->var would now be two
		$obj1->var = 'one';
		$obj2->var = 'two';

		$this->assertTrue($obj1->circularOne instanceof CircularOne);
		$this->assertTrue($obj1->circularTwo instanceof CircularTwo);

		$this->assertEquals($obj1->circularOne, $obj2->circularOne);
		$this->assertNotEquals($obj1, $obj2);
	}

	public function testSimpleInstantiation() {
		$services = array(
			'CircularOne'	=> 'November\Tests\CircularOne', 
			'CircularTwo'	=> 'November\Tests\CircularTwo',
			'NeedsBothCirculars'	=> 'November\Tests\NeedsBothCirculars'
		);

		$injector = new Injector($services);
		$injector->setAutoScanProperties(true);
		
		// similar to the above, but explicitly instantiating this object here
		$obj1 = $injector->create('NeedsBothCirculars');
		$obj2 = $injector->create('NeedsBothCirculars');

		// if this was the same object, then $obj1->var would now be two
		$obj1->var = 'one';
		$obj2->var = 'two';

		$this->assertEquals($obj1->circularOne, $obj2->circularOne);
		$this->assertNotEquals($obj1, $obj2);
	}

	public function testOverridePriority() {
		$injector = new Injector();
		$injector->setAutoScanProperties(true);
		$config = array(
			'SampleService'	=> array(
				'class' => 'November\Tests\Services\SampleService',
				'priority' => 10,
			)
		);

		// load
		$injector->load($config);

		// inject
		$myObject = new TestObject();
		$injector->inject($myObject);

		$this->assertEquals(get_class($myObject->sampleService), 'November\Tests\Services\SampleService');

		$config = array(
			'SampleService'	=> array(
				'class' => 'November\Tests\Services\AnotherService',
				'priority' => 1,
			)
		);
		// load
		$injector->load($config);

		$injector->inject($myObject);
		$this->assertEquals('November\Tests\Services\SampleService', get_class($myObject->sampleService));
	}

	/**
	 * Specific test method to illustrate various ways of setting a requirements backend
	 */
	public function testRequirementsSettingOptions() {
		$injector = new Injector();
		$config = array(
			'OriginalRequirementsBackend'		=> 'November\Tests\OriginalRequirementsBackend',
			'NewRequirementsBackend'			=> 'November\Tests\NewRequirementsBackend',
			'Requirements' => array(
				'class' => 'November\Tests\Requirements',
				'constructor' => array(
					'%$OriginalRequirementsBackend'
				)
			)
		);

		$injector->load($config);

		$requirements = $injector->get('Requirements');
		$this->assertEquals('November\Tests\OriginalRequirementsBackend', get_class($requirements->backend));

		// just overriding the definition here
		$injector->load(array(
			'Requirements' => array(
				'class' => 'November\Tests\Requirements',
				'constructor' => array(
					'%$NewRequirementsBackend'
				)
			)
		));

		// requirements should have been reinstantiated with the new bean setting
		$requirements = $injector->get('Requirements');
		$this->assertEquals('November\Tests\NewRequirementsBackend', get_class($requirements->backend));
	}

	/**
	 * disabled for now
	 */
	public function testStaticInjections() {
//		$injector = new Injector();
//		$config = array(
//			'NewRequirementsBackend',
//		);
//
//		$injector->load($config);
//
//		$si = $injector->get('StaticInjections');
//		$this->assertEquals('NewRequirementsBackend', get_class($si->backend));
	}

	public function testCustomObjectCreator() {
		
		// skipped for now 
		
//		$injector = new Injector();
//		$injector->setObjectCreator(new SSObjectCreator());
//		$config = array(
//			'OriginalRequirementsBackend'		=> 'November\Tests\OriginalRequirementsBackend',
//			'Requirements' => array(
//				'class' => 'November\Tests\Requirements(\'%$OriginalRequirementsBackend\')'
//			)
//		);
//		$injector->load($config);
//
//		$requirements = $injector->get('Requirements');
//		$this->assertEquals('OriginalRequirementsBackend', get_class($requirements->backend));
	}

}


class TestObject {

	public $globalNamespaceService;
	
	public $sampleService;

	public function setSomething($v) {
		$this->sampleService = $v;
	}

}

class OtherTestObject {

	private $sampleService;

	public function setSampleService($s) {
		$this->sampleService = $s;
	}

	public function s() {
		return $this->sampleService;
	}

}

class CircularOne {

	public $circularTwo;

}

class CircularTwo {

	public $circularOne;

}

class NeedsBothCirculars {

	public $circularOne;
	public $circularTwo;
	public $var;

}

class Requirements {

	public $backend;

	public function __construct($backend) {
		$this->backend = $backend;
	}

	public function setBackend($backend) {
		$this->backend = $backend;
	}

}

class OriginalRequirementsBackend {

}

class NewRequirementsBackend {

}

class StaticInjections {

	public $backend;
	static $injections = array(
		'backend' => '%$NewRequirementsBackend'
	);

}

/**
 * An example object creator that uses the SilverStripe class(arguments) mechanism for
 * creating new objects
 *
 * @see https://github.com/silverstripe/sapphire
 */
class SSObjectCreator extends InjectionCreator {

	public function create($class, $params = array()) {
		if (strpos($class, '(') === false) {
			return parent::create($class, $params);
		} else {
			list($class, $params) = self::parse_class_spec($class);
			return parent::create($class, $params);
		}
	}

	/**
	 * Parses a class-spec, such as "Versioned('Stage','Live')", as passed to create_from_string().
	 * Returns a 2-elemnent array, with classname and arguments
	 */
	static function parse_class_spec($classSpec) {
		$tokens = token_get_all("<?php $classSpec");
		$class = null;
		$args = array();
		$passedBracket = false;

		// Keep track of the current bucket that we're putting data into
		$bucket = &$args;
		$bucketStack = array();

		foreach ($tokens as $token) {
			$tName = is_array($token) ? $token[0] : $token;
			// Get the class naem
			if ($class == null && is_array($token) && $token[0] == T_STRING) {
				$class = $token[1];
				// Get arguments
			} else if (is_array($token)) {
				switch ($token[0]) {
					case T_CONSTANT_ENCAPSED_STRING:
						$argString = $token[1];
						switch ($argString[0]) {
							case '"': $argString = stripcslashes(substr($argString, 1, -1));
								break;
							case "'": $argString = str_replace(array("\\\\", "\\'"), array("\\", "'"), substr($argString, 1, -1));
								break;
							default: throw new \Exception("Bad T_CONSTANT_ENCAPSED_STRING arg $argString");
						}
						$bucket[] = $argString;
						break;

					case T_DNUMBER:
						$bucket[] = (double) $token[1];
						break;

					case T_LNUMBER:
						$bucket[] = (int) $token[1];
						break;

					case T_STRING:
						switch ($token[1]) {
							case 'true': $args[] = true;
								break;
							case 'false': $args[] = false;
								break;
							default: throw new \Exception("Bad T_STRING arg '{$token[1]}'");
						}

					case T_ARRAY:
						// Add an empty array to the bucket
						$bucket[] = array();
						$bucketStack[] = &$bucket;
						$bucket = &$bucket[sizeof($bucket) - 1];
				}
			} else {
				if ($tName == ')') {
					// Pop-by-reference
					$bucket = &$bucketStack[sizeof($bucketStack) - 1];
					array_pop($bucketStack);
				}
			}
		}

		return array($class, $args);
	}

}