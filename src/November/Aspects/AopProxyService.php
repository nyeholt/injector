<?php

namespace November\Aspects;

/**
 * A class that proxies another, allowing various functionality to be
 * injected
 * 
 * @author marcus@silverstripe.com.au
 * @license http://silverstripe.org/bsd-license/
 */
class AopProxyService {
	public $beforeCall = array();

	public $afterCall = array();

	public $proxied;
	
	/**
	 * Because we don't know exactly how the proxied class is usually called,
	 * provide a default constructor
	 */
	public function __construct() {
		
	}

	public function __call($method, $args) {
		if (method_exists($this->proxied, $method)) {
			$continue = true;
			if (isset($this->beforeCall[$method])) {
				$methods = $this->beforeCall[$method];
				if (!is_array($methods)) {
					$methods = array($methods);
				}
				foreach ($methods as $handler) {
					$result = $handler->beforeCall($this->proxied, $method, $args);
					if ($result === false) {
						$continue = false;
					}
				}
			}

			if ($continue) {
				$result = call_user_func_array(array($this->proxied, $method), $args);
			
				if (isset($this->afterCall[$method])) {
					$methods = (array) $this->afterCall[$method];
					if (!is_array($methods)) {
						$methods = array($methods);
					}
					foreach ($methods as $handler) {
						$handler->afterCall($this->proxied, $method, $args, $result);
					}
				}

				return $result;
			}
		}
	}
}
