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

		$item = $injector->getService('AnotherService');

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