<?php

namespace November\Injection;

/**
 * Used to locate configuration for a particular named service. 
 * 
 * This is specifically to allow 3rd party frameworks to override how configuration lookup is 
 * performed; it's not exactly the nicest implementation yet and will be progressively refactored 
 * over time
 * 
 * If it isn't found, return null 
 * 
 */
class ServiceConfigurationLocator {
	public function locateConfigFor($name) {
		
	}
}