<?php

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

	public function __call($method, $args) {
		if (method_exists($this->proxied, $method)) {
			$continue = true;
			if (isset($this->beforeCall[$method])) {
				$result = $this->beforeCall[$method]->preCall($this->proxied, $method, $args);
				if ($result === false) {
					$continue = false;
				}
			}

			if ($continue) {
				$result = call_user_func_array(array($this->proxied, $method), $args);
			
				if (isset($this->afterCall[$method])) {
					$this->afterCall[$method]->postCall($this->proxied, $method, $args, $result);
				}

				return $result;
			}
        }
	}
}