<?php
/**
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to marcus@mikenovember.com so I can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2006-2007 Marcus Nyeholt (http://mikenovember.com)
 * @version	$Id$
 * @license	New BSD License
 */

/**
 * A simple injection manager that manages loading beans and injecting
 * dependencies between them. It borrows quite a lot from ideas taken from
 * Spring's configuration.
 *
 * There are two ways to have services managed by Injector; firstly
 * by specifying an explicit configuration array, secondly by annotating
 * various classes and preprocessing them to generate this configuration
 * automatically (@TODO). 
 *
 * Specify a configuration array of the format
 *
 * array(
 *		array(
 *			'id'			=> 'BeanId',					// the name to be used if diff from the filename
 *			'piority'		=> 1,							// priority. If another bean is defined with the same ID, 
 *															// but has a lower priority, it is NOT overridden
 *			'class'			=> 'ClassName',					// the name of the PHP class
 *			'src'			=> '/path/to/file'				// the location of the class
 *			'type'			=> 'singleton|prototype'		// if you want prototype object generation, set it as the type
 *															// By default, singleton is assumed
 *
 *			'construct'		=> array(						// properties to set at construction
 *				'scalar',									
 *				'#$BeanId',
 *			)
 *			'properties'	=> array(
 *				'name' => 'value'							// scalar value
 *				'name' => '#$BeanId',						// a reference to another bean
 *				'name' => array(
 *					'scalar',
 *					'#$BeanId'
 *				)
 *			)
 *		)
 * )
 *
 * In addition to specifying the bindings directly in the configuration,
 * you can simply create a publicly accessible property on the target
 * class which will automatically be injected.
 *
 */
class Injector {

	/**
	 * Local store of all services
	 *
	 * @var array
	 */
	private $serviceCache;

	/**
	 * Cache of items that need to be mapped for each service that gets injected
	 *
	 * @var array
	 */
	private $injectMap;

	/**
	 * A store of all the service configurations that have been defined.
	 *
	 * @var array
	 */
	private $specs;
	
	/**
	 * A map of all the properties that should be automagically set on a 
	 * service
	 */
	private $autoProperties;

	/**
	 * A singleton if you want to use it that way
	 *
	 * @var Injector
	 */
	private static $instance;
	
	/**
	 * Create a new injector. 
	 *
	 * @param array $config
	 *				Service configuration
	 */
	public function __construct($config = null) {
		$this->injectMap = array();
		$this->serviceCache = array();
		$this->autoProperties = array();
		$this->specs = array();

		if ($config) {
			$this->load($config);
		}
	}

	/**
	 * If a user wants to use the injector as a static reference
	 *
	 * @param array $config
	 */
	public static function inst($config=null) {
		if (!self::$instance) {
			self::$instance = new Injector($config);
		}
		return self::$instance;
	}
	
	/**
	 * Add an object that should be automatically set on managed objects
	 *
	 * This allows you to specify, for example, that EVERY managed object
	 * will be automatically inject with a log object by the following
	 *
	 * $injector->addAutoProperty('log', new Logger());
	 *
	 * @param string $property
	 *				the name of the property
	 * @param object $object
	 *				the object to be set
	 */
	public function addAutoProperty($property, $object) {
		$this->autoProperties[$property] = $object;
	}

	/**
	 * Load services using the passed in configuration for those services
	 *
	 * @param array $config
	 */
	public function load($config = array()) {
		$services = array();

		foreach ($config as $spec) {
			if (is_string($spec)) {
				$spec = array('class' => $spec);
			}

			$file = isset($spec['src']) ? $spec['src'] : null; 
			$name = null;

			if (file_exists($file)) {
				$filename = basename($file);
				$name = substr($filename, 0, strrpos($filename, '.'));
			}

			$class = isset($spec['class']) ? $spec['class'] : $name;
			// make sure the class is set...
			$spec['class'] = $class;

			$id = isset($spec['id']) ? $spec['id'] : $class; 
			
			$priority = isset($spec['priority']) ? $spec['priority'] : 1;
			
			// see if we already have this defined. If so, check 
			// priority weighting
			if (isset($this->specs[$id]) && isset($this->specs[$id]['priority'])) {
				if ($this->specs[$id]['priority'] > $priority) {
					return;
				}
			}

			// okay, actually include it now we know we're going to use it
			if (file_exists($file)) {
				require_once $file;
			}

			// make sure to set the id for later when instantiating
			// to ensure we get cached
			$spec['id'] = $id;

			if (!class_exists($class)) {
				throw new Exception("Failed to load '$class' from $file");
			}

			// store the specs for now - we lazy load on demand later on. 
			$this->specs[$id] = $spec;

			// EXCEPT when there's already an existing instance at this id.
			// if so, we need to instantiate and replace immediately
			if (isset($this->serviceCache[$id])) {
				$this->instantiate($spec, $id);
			}
		}

		return $this;
	}

	/**
	 * Recursively convert a value into its proper representation
	 *
	 * @param string $value 
	 */
	protected function convertServiceProperty($value) {
		if (is_array($value)) {
			$newVal = array();
			foreach ($value as $k => $v) {
				$newVal[$k] = $this->convertServiceProperty($v);
			}
			return $newVal;
		}
		
		if (strpos($value, '#$') === 0) {
			$id = substr($value, 2);
			if (!$this->hasService($id)) {
				throw new Exception("Undefined service $id for property when trying to resolve property");
			}
			return $this->get($id);
		}
		return $value;
	}

	/**
	 * Instantiate a managed object
	 *
	 * Given a specification of the form
	 *
	 * array(
	 *		'class' => 'ClassName',
	 *		'properties' => array('property' => 'scalar', 'other' => '#$BeanRef')
	 *		'id' => 'ServiceId',
	 *		'type' => 'singleton|prototype'
	 * )
	 *
	 * will create a new object, store it in the service registry, and
	 * set any relevant properties
	 *
	 * Optionally, you can pass a
	 *
	 * @param array $spec
	 *				The specification of the class to instantiate
	 */
	public function instantiate($spec, $id=null) {
		if (is_string($spec)) {
			$spec = array('class' => $spec);
		}
		$class = $spec['class'];
		
		if (isset($spec['constructor']) && is_array($spec['constructor'])) {
			$reflector = new ReflectionClass($class);
			$object = $reflector->newInstanceArgs($this->convertServiceProperty($spec['constructor']));
		} else {
			$object = new $class;
		}
		
		$props = isset($spec['properties']) ? $spec['properties'] : array(); 

		// figure out if we have an id or not
		if (!$id) {
			$id = isset($spec['id']) ? $spec['id'] : null;
		}

		foreach ($props as $key => $value) {
			$val = $this->convertServiceProperty($value);
			if (method_exists($object, 'set'.$key)) {
				$object->{'set'.$key}($val);
			} else {
				$object->$key = $val;
			}
		}

		$type = isset($spec['type']) ? $spec['type'] : null; 
		if ($id && (!$type || $type != 'prototype')) {
			// this ABSOLUTELY must be set before the object is injected.
			// This prevents circular reference errors down the line
			$this->serviceCache[$id] = $object;
		}

		// now inject safely
		$this->inject($object);

		return $object;
	}

	/**
	 * Inject $object with available objects from the service cache
	 *
	 * @param Injectable $object
	 */
	public function inject($object) {
		$objtype = get_class($object);
		$mapping = isset($this->injectMap[$objtype]) ? $this->injectMap[$objtype] : null;
		
		if (!$mapping) {
			$mapping = new ArrayObject();
			$robj = new ReflectionObject($object);
			$properties = $robj->getProperties();
	
			foreach ($properties as $propertyObject) {
				/* @var $propertyObject ReflectionProperty */
				if ($propertyObject->isPublic()) {
					$origName = $propertyObject->getName();
					$name = ucfirst($origName);
					if ($this->hasService($name)) {
						// Pull the name out of the registry
						$value = $this->get($name);
						$propertyObject->setValue($object, $value);
						$mapping[$origName] = array('name' => $name, 'type' => 'property');
					}
				}
			}

			$methods = $robj->getMethods(ReflectionMethod::IS_PUBLIC);

			foreach ($methods as $methodObj) {
				/* @var $methodObj ReflectionMethod */
				$methName = $methodObj->getName();
				if (strpos($methName, 'set') === 0) {
					$pname = substr($methName, 3);
					if ($this->hasService($pname)) {
						// Pull the name out of the registry
						$value = $this->get($pname);
						$methodObj->invoke($object, $value);
						$mapping[$pname] = array('name' => $pname, 'type' => 'method');
					}
				}
			}

			$this->injectMap[get_class($object)] = $mapping;
		} else {
			foreach ($mapping as $prop => $spec) {
				if ($spec['type'] == 'property') {
					$value = $this->get($spec['name']);
					$object->$prop = $value;
				} else {
					$method = 'set'.$prop;
					$value = $this->get($spec['name']);
					$object->$method($value);
				}
			}
		}
		
		foreach ($this->autoProperties as $property => $value) {
			if (!isset($object->$property)) {
				$object->$property = $value;
			}
		}

		// Call the 'injected' method if it exists
		if (method_exists($object, 'injected')) {
			$object->injected();
		}
	}

	/**
	 * Does the given service exist?
	 */
	public function hasService($name) {
		return isset($this->specs[$name]);
	}

	/**
	 * Register a service object with an optional name to register it as the
	 * service for
	 */
	public function registerService($service, $replace=null) {
		$registerAt = get_class($service);
		if ($replace != null) {
			$registerAt = $replace;
		}
		
		$this->serviceCache[$registerAt] = $service;
		$this->inject($service);
	}
	
	/**
	 * Register a service with an explicit name
	 */
	public function registerNamedService($name, $service) {
		$this->serviceCache[$name] = $service;
		$this->inject($service);
	}
	
	/**
	 * Get a named managed object
	 * 
	 * @param $name the name of the service to retrieve
	 */
	public function get($name) {
		if ($this->hasService($name)) {
			// check to see what the type of bean is. If it's a prototype,
			// we don't want to return the singleton version of it.
			$spec = $this->specs[$name];
			$type = isset($spec['type']) ? $spec['type'] : null;

			if ($type && $type == 'prototype') {
				return $this->instantiate($spec, $name);
			} else {
				if (!isset($this->serviceCache[$name])) {
					$this->instantiate($spec, $name);
				}
				return $this->serviceCache[$name];
			}
		}
		
		throw new Exception("Service $name is not defined");
	}
}
