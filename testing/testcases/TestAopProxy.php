<?php

/**
 *
 * @author marcus@silverstripe.com.au
 * @license http://silverstripe.org/bsd-license/
 */
class TestAopProxy extends UnitTestCase {
	public function testProxyClass() {

		$config = array(
			array('class' => 'TestServiceForProxy', 'id' => 'TestServiceForProxy'),
			array('class' => 'TestAspectBean', 'id' => 'PreCallTest'),
			array('class' => 'TestAspectBean', 'id' => 'PostCallTest'),
			array(
				'src' => dirname(dirname(dirname(__FILE__))).'/services/AopProxyService.php',
				'id' => 'TestService',
				'properties' => array(
					'proxied' => '#$TestServiceForProxy',
					'beforeCall' => array(
						'calculate' => '#$PreCallTest',
					),
					'afterCall' => array(
						'calculate' => '#$PostCallTest',
					)
				)
			),
		);

		$injector = new Injector($config);

		$test = $injector->get('TestService');

		$this->assertEqual('AopProxyService', get_class($test));
		$this->assertEqual(10, $test->calculate(5));

		$aspect1 = $injector->get('PreCallTest');
		$this->assertEqual(5, $aspect1->data['calculatepre']);

		$aspect2 = $injector->get('PostCallTest');
		$this->assertEqual(10, $aspect2->data['calculatepost']);

		$this->assertNotEqual($aspect1, $aspect2);

	}
}

class TestAspectBean {
	public $data = array();

	public function preCall($proxied, $method, $args) {
		$this->data[$method.'pre'] = $args[0];
	}

	public function postCall($proxied, $method, $args, $result) {
		$this->data[$method.'post'] = $result;
	}
}

class TestServiceForProxy {
	public function calculate($with = 0) {
		return 5 + $with;
	}
}

