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
 * @version    $Id$
 * @license    New BSD License
 */

if (!function_exists('ifset')) {
	function ifset(&$array, $key, $default = null) {
	    if (!is_array($array) && !($array instanceof ArrayAccess)) {
			throw new Exception("Must use an array!");
		}
	    return isset($array[$key]) ? $array[$key] : $default;
	}
}

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
 *			'id' => 'BeanId',						// the name to be used if diff from the filename
 *			'class' => 'ClassName',					// the name of the PHP class
 *			'src' => '/path/to/file'				// the location of the class
 *			'properties' => array(
 *				'name' => 'value'					// scalar value
 *				'name' => '#$BeanId',				// a reference to another bean
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
     * Create a new injector. 
     *
     * @param string|array $serviceDirectory
	 *				The location to automatically load services from. Can be a string or an array for multiple
	 *				locations. Any class in these directories will be assumed to be a managed class
     */
    public function __construct() {
        $this->injectMap = array();
        $this->serviceCache = array();
        $this->autoProperties = array();
		$this->specs = array();
    }
    
    /**
     * Adds a directory to load services from
     * @param string $dir
	 *				The directory to load services from
     */
    public function addServiceDirectory($dir) {
        $this->serviceDirectories[] = $dir;
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

		foreach ($config as $bean) {
			$file = ifset($bean, 'src');

			if (!file_exists($file)) {
				throw new Exception("Configured service $file does not exist");
			}

			$filename = basename($file);
			$name = substr($filename, 0, strrpos($filename, '.'));
			
			$id = ifset($bean, 'id', $name);
			$class = ifset($bean, 'class', $name);

			include_once $file;

			if (!class_exists($class, false)) {
				throw new Exception("Failed to load '$class' from $file");
			}

			$service = new $class;
			$props = ifset($bean, 'properties', array());

			foreach ($props as $key => $value) {
				$val = $this->convertServiceProperty($value);
				if (method_exists($service, 'set'.$key)) {
					$service->{'set'.$key}($value);
				} else {
					$service->$key = $value;
				}
			}

			$this->specs[$id] = $bean;
			$this->serviceCache[$id] = $service;
		}

        // so now match up any dependencies and inject away
        foreach ($this->serviceCache as $service) {
            $this->inject($service);
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
			foreach ($value as $v) {
				$newVal[] = $this->convertServiceProperty($value);
			}
			return $newVal;
		}
		
		if (strpos($value, '#$') === 0) {
			$id = substr($value, 2);
			if (!isset($this->serviceCache[$id])) {
				throw new Exception("Undefined service $id for property");
			}
			return $this->serviceCache[$id];
		}
		return $value;
	}
    
    /**
     * Inject $object with available objects from the service cache
     *
     * @param Injectable $object
     */
    public function inject($object) {
        
        $mapping = ifset($this->injectMap, get_class($object), null);
        
        if (!$mapping) {
			$mapping = new ArrayObject();
			$robj = new ReflectionObject($object);
	        $properties = $robj->getProperties();
	
	        foreach ($properties as $propertyObject) {
	            $origName = $propertyObject->getName();
	            $name = ucfirst($origName);
	            if (isset($this->serviceCache[$name])) {
	                // Pull the name out of the registry
                    $value = $this->serviceCache[$name];
	                $propertyObject->setValue($object, $value);
	                $mapping[$origName] = $name;
	            }
	        }
	        $this->injectMap[get_class($object)] = $mapping;
        } else {
            foreach ($mapping as $prop => $serviceName) {
				$value = $this->serviceCache[$serviceName];
                $object->$prop = $value;
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
        return isset($this->serviceCache[$name]);
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
     * Get a named service
     * @param $name the name of the service to retrieve
     */
    public function getService($name) {
        if ($this->hasService($name)) {
            return $this->serviceCache[$name];
        }
        throw new Exception("Service $name is not defined");
    }
}
