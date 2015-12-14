<?php

namespace Teapot;

require_once 'settings.php';
require_once 'module.php';

/*
 * Teapot requires PHP 5.5 to run; reference in foreach
 */

/* Argument class used to pass things up and down the chain */
class Args {
    public $_retval = NULL;
    public $params = NULL;
    protected $_labels = NULL;

    static function label() {
        $labels = func_get_args();
        $args = array_shift($labels);
        $args->_labels = NULL;
        if (count($labels) > 0) {
            $args->_labels = $labels;
        }
    }

    function __construct($params = NULL) {
        $this->params = $params !== NULL ? $params: array();
    }

    function &__get($label) {
        if ($label == 'retval') {
            return $this->_retval;
        }

        $retval = NULL;
        if ($this->_labels !== NULL) {
            $i = array_search($label, $this->_labels);
            if ($i !== false) {
                $retval = $this->params[$i];
            }
        }
        return $retval;
    }

    function __set($label, $value) {
        if ($label == 'retval') {
            $this->_retval = $value;
        }

        if ($this->_labels !== NULL) {
            $i = array_search($label, $this->_labels);
            if ($i !== false) {
                $this->params[$i] = $value;
            }
        }        
    } 
}

/* Teapot base exception class */
class Exception extends \Exception {
    var $_str = null;
    function __construct($str) {
        $this->_str = $str;
        error_log($str);
    }

    public function __toString()
    {
        return $this->_str;
    }
}


class ConfigException extends Exception {}
class NotFoundException extends Exception {}


/* output a debug message */
function debug($str) {
    if (defined('Teapot\\TEAPOT_DEBUG') === true) {
        error_log('debug; '.$str);
    }    
}


/* Main teapot - through which all things flow */
class Teapot {
    const DOWN_PREFIX = 'put_';

    var $_modules = array();

    function __construct() {
        /* load all the modules */
        global $MODULES;
        $modules = $MODULES;
        $init_params = array();
        foreach ($modules as $module_name) {
            $params = NULL;
            if (is_array($module_name) === true) {
                /* 1st element is module name, others are constructor params */
                $params = $module_name;
                $module_name = array_shift($params);
            }
            $init_params[$module_name] = $params;
            $mod = Module::create($this, $module_name);
            if ($mod === NULL) {
                error_log(
                    'fatal; invalid module: '.print_r($module_name, true)
                );
                die();
            }
            /* reverse array so its in 'get' order, as thats more common */
            array_unshift($this->_modules, $mod);
        }
    
        /* tell the modules setup is complete - bottom up */
        $this->_init($init_params);
    }

    function __call($name, $params) {
        /* although the user defines MODULES in 'put' order we reverse it at 
           load as 'get' is far more common a request - to help make calling
           code as simple as possible we also support no prefix.  If the call
           is a 'get' we add an additional reference parameter [first one].
           This is used to pass the return value up the chain */
        $args = new Args($params);
        if (strpos($name, Teapot::DOWN_PREFIX) === 0) {
            foreach (array_reverse($this->_modules) as &$mod) {
                if (method_exists($mod, $name) === true) {
                    Args::label($args); /* reset labels */
                    $mod->$name($args);
                }
            }
        } else {
            /* call needs a return value */
            $called = 0;
            foreach ($this->_modules as &$mod) {
                if (method_exists($mod, $name) === true) {
                    $mod->$name($args);
                    $called += 1;
                }
            }
            if ($called === 0) {
                throw new ConfigException('error; nobody handled call: '.$name);
            }
        }
        return $args->retval;
    }
}


/* helper class to make php's json easier to call */
class JSON {
    public static function decode_file($file) {
        return json_decode(utf8_encode(file_get_contents($file)), true);
    }

    public static function encode_file($file, $data) {
        file_put_contents(
            $file,
            utf8_decode(json_encode($data, JSON_PRETTY_PRINT))
        );
    }
}

?>
