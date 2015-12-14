<?php

namespace Teapot;

class Backend extends Module {
    const MODEL_FILE = 'model.json';

    protected $_model = NULL;
    protected $_model_file = NULL;

    function init(&$args) {
        Args::label($args, 'model_file');
        /* load the raw model data */
        $this->_model_file = dirname(__FILE__).
                             DIRECTORY_SEPARATOR.
                             Backend::MODEL_FILE;
        if (isset($args->model_file) === true) {
            /* first param, if present, overrides model name */
            $this->_model_file = $args->model_file;
        }
        $this->_model = JSON::decode_file($this->_model_file);
        if ($this->_model === NULL) {
            if (file_exists($this->_model_file) === true) {
                /* model file exists but isn't loading - probably a stray comma */
                throw new ConfigException(
                    'fatal; unable to parse model file: '.$this->_schema_file
                );
            } else {
                /* file doesn't exist, create it with '{}' */
                file_put_contents($this->_model_file, '{}');
                $this->_model = JSON::decode_file($this->_model_file);
            }
        }
    }

    function get_model(&$args) {
        Args::label($args, 'route');        
        $model = &$this->_model;
        if ($args->route !== NULL) {
            // XXX: add support for matching based named items - search children rather than keys
            $fragments = explode('/', $args->route);
            for ($i = 0; $i < count($fragments); $i++) {
                if (isset($model[$fragments[$i]]) === true) {
                    /* actual model is present */
                    $model = &$model[$fragments[$i]];
                } else {
                    $model = NULL;
                    break;
                }            
            }
        }
        // XXX: error_log('get_model: '.$args->params[0].' - '.print_r($model, true));
        $args->retval = $model;
    }

    function put_model($args) {
        Args::label($args, 'route', 'model');
        $model = &$this->_model;
        // XXX: add support for matching based named items - search children rather than keys
        $fragments = explode('/', $args->route);
        for ($i = 0; $i < count($fragments); $i++) {
            if (isset($model[$fragments[$i]]) !== true) {
                /* create if it doesn't exist */
                $model[$fragments[$i]] = array();
            }
            $model = &$model[$fragments[$i]];
        }
        $model = array_replace_recursive($model, $args->model);
        // XXX: error_log('put_model: '.$args->params[0].' - '.print_r($model, true));       
        JSON::encode_file($this->_model_file, $this->_model);

        /* generate site with data */
        $this->_teapot->generate($args->route);
    }
}

?>
