<?php

class AnotherService
{
    public $testValue;
    
    public function configure($config)
    {
        if (isset($config['config_property'])) {
            $this->testValue = $config['config_property'];
        }
    }
}
?>