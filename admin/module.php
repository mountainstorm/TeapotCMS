<?php

namespace Teapot;


/* base class for all modules, makes creation easy */
class Module {
    protected $_module_name = NULL;
    protected $_teapot = NULL;
    protected $_defaults = NULL;

    static function create($teapot, $module_name) {
        $file = $module_name.DIRECTORY_SEPARATOR.'module.php';
        require_once $file;
        
        $type = explode('.', $module_name, 2);
        $classname = 'Teapot\\'.ucfirst($type[0]);
        return new $classname($teapot, $module_name);
    }

    function __construct(&$teapot, $module_name) {
        $this->_teapot = $teapot;
        $this->_module_name = $module_name;
    }

    function _init($args) {
        /* make handling of params MUCH nicer */
        if (method_exists($this, 'init') === true) {
            $params = NULL;
            if (count($args->params) > 0 &&
                isset($args->params[0][$this->_module_name]) === true) {
                $params = &$args->params[0][$this->_module_name];
            }
            $this->init(new Args($params));
        }
    }

    function get_modules(&$args) {
        if ($args->retval === NULL) {
            /* if we're the end of the array we need to set this */
            $args->retval = array();
        }
        array_push($args->retval, $this->_module_name);
    }
}

?>
