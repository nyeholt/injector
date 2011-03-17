<?php

include_once dirname(dirname(dirname(__FILE__))).'/Injector.php';

define('TEST_SERVICES', dirname(dirname(__FILE__)).'/services');

class TestInjector extends UnitTestCase
{
    public function testBasicInjector() {
        $injector = new Injector();
        $config = array(array('src' => TEST_SERVICES.'/SampleService.php',));

        $injector->load($config);
        $this->assertTrue($injector->hasService('SampleService'));
                
        $myObject = new TestObject();
        $injector->inject($myObject);
        
        $this->assertEqual(get_class($myObject->sampleService), 'SampleService');
    }
    
    public function testConfiguredInjector() {
        $injector = new Injector();
        
        $services = array (
		    array (
		      'src' => TEST_SERVICES.'/AnotherService.php',
		      'properties' => array('config_property' => 'Value'),
		    ),
			array(
				'src' => TEST_SERVICES.'/SampleService.php',
			)
		);

        $injector->load($services);
        $this->assertTrue($injector->hasService('SampleService'));
        // We expect a false because the 'AnotherService' is actually
        // just a replacement of the SampleService
	    $this->assertTrue($injector->hasService('AnotherService'));

		$item = $injector->get('AnotherService');

		$this->assertEqual('Value', $item->config_property);
    }

	public function testReplaceService() {
		$injector = new Injector();

        $config = array(array('src' => TEST_SERVICES.'/SampleService.php'));

		// load
        $injector->load($config);

		// inject
		$myObject = new TestObject();
        $injector->inject($myObject);

        $this->assertEqual(get_class($myObject->sampleService), 'SampleService');
		
		$config = array(array('src' => TEST_SERVICES.'/AnotherService.php', 'id' => 'SampleService'));
		// load
        $injector->load($config);

		$injector->inject($myObject);
        $this->assertEqual('AnotherService', get_class($myObject->sampleService));
	}

    public function testAutoSetInjector() {
        $injector = new Injector();
        $injector->addAutoProperty('auto', 'somevalue');
		$config = array(array('src' => TEST_SERVICES.'/SampleService.php',));
        $injector->load($config);

        $this->assertTrue($injector->hasService('SampleService'));
        // We expect a false because the 'AnotherService' is actually
        // just a replacement of the SampleService

        $myObject = new TestObject();

        $injector->inject($myObject);

        $this->assertEqual(get_class($myObject->sampleService), 'SampleService');
        $this->assertEqual($myObject->auto, 'somevalue');
    }
	
	public function testInjectUsingConstructor() {
		$injector = new Injector();
        $config = array(array(
			'src' => TEST_SERVICES.'/SampleService.php',
			'constructor' => array(
				'val1',
				'val2',
			)
		));
		
		$injector->load($config);
		$sample = $injector->get('SampleService');
		$this->assertEqual($sample->constructorVarOne, 'val1');
		$this->assertEqual($sample->constructorVarTwo, 'val2');
		
		$injector = new Injector();
        $config = array(
			'AnotherService',
			array(
				'src' => TEST_SERVICES.'/SampleService.php',
				'constructor' => array(
					'val1',
					'#$AnotherService',
				)
			)
		);
		
		$injector->load($config);
		$sample = $injector->get('SampleService');
		$this->assertEqual($sample->constructorVarOne, 'val1');
		$this->assertEqual(get_class($sample->constructorVarTwo), 'AnotherService');
	}

	public function testInjectUsingSetter() {
		$injector = new Injector();
        $config = array(array('src' => TEST_SERVICES.'/SampleService.php',));

        $injector->load($config);
        $this->assertTrue($injector->hasService('SampleService'));

        $myObject = new OtherTestObject();
        $injector->inject($myObject);

        $this->assertEqual(get_class($myObject->s()), 'SampleService');

		// and again because it goes down a different code path when setting things
		// based on the inject map
		$myObject = new OtherTestObject();
        $injector->inject($myObject);

        $this->assertEqual(get_class($myObject->s()), 'SampleService');
	}
	
	// make sure we can just get any arbitrary object - it should be created for us
	public function testInstantiateAnObjectViaGet() {
		$injector = new Injector();
        $config = array(array('src' => TEST_SERVICES.'/SampleService.php',));

        $injector->load($config);
        $this->assertTrue($injector->hasService('SampleService'));

        $myObject = $injector->get('OtherTestObject');
        $this->assertEqual(get_class($myObject->s()), 'SampleService');

		// and again because it goes down a different code path when setting things
		// based on the inject map
		$myObject = $injector->get('OtherTestObject');
        $this->assertEqual(get_class($myObject->s()), 'SampleService');
	}

	public function testCircularReference() {
		$services = array ('CircularOne', 'CircularTwo');
        $injector = new Injector($services);

		$obj = $injector->get('NeedsBothCirculars');

		$this->assertTrue($obj->circularOne instanceof CircularOne);
		$this->assertTrue($obj->circularTwo instanceof CircularTwo);
	}

	public function testPrototypeObjects() {
		$services = array('CircularOne', 'CircularTwo', array('class' => 'NeedsBothCirculars', 'type' => 'prototype'));
		$injector = new Injector($services);

		$obj1 = $injector->get('NeedsBothCirculars');
		$obj2 = $injector->get('NeedsBothCirculars');

		// if this was the same object, then $obj1->var would now be two
		$obj1->var = 'one';
		$obj2->var = 'two';

		$this->assertTrue($obj1->circularOne instanceof CircularOne);
		$this->assertTrue($obj1->circularTwo instanceof CircularTwo);

		$this->assertEqual($obj1->circularOne, $obj2->circularOne);
		$this->assertNotEqual($obj1, $obj2);
	}
	

	public function testSimpleInstantiation() {
		$services = array('CircularOne', 'CircularTwo');
		$injector = new Injector($services);

		// similar to the above, but explicitly instantiating this object here
		$obj1 = $injector->get('NeedsBothCirculars');
		$obj2 = $injector->get('NeedsBothCirculars');

		// if this was the same object, then $obj1->var would now be two
		$obj1->var = 'one';
		$obj2->var = 'two';

		$this->assertEqual($obj1->circularOne, $obj2->circularOne);
		$this->assertNotEqual($obj1, $obj2);
	}
	
	public function testOverridePriority() {
		$injector = new Injector();

        $config = array(
			array(
				'src' => TEST_SERVICES.'/SampleService.php',
				'priority' => 10,
			)
		);

		// load
        $injector->load($config);

		// inject
		$myObject = new TestObject();
        $injector->inject($myObject);

        $this->assertEqual(get_class($myObject->sampleService), 'SampleService');
		
		$config = array(
			array(
				'src' => TEST_SERVICES.'/AnotherService.php', 
				'id' => 'SampleService',
				'priority' => 1,
			)
		);
		// load
        $injector->load($config);

		$injector->inject($myObject);
        $this->assertEqual('SampleService', get_class($myObject->sampleService));
	}
}

class TestObject {
    public $sampleService;
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