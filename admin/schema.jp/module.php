<?php

namespace Teapot;

class Schema extends Module {
    const SCHEMA_FILE = 'schema.json';

    protected $_schema = NULL;

    function init(&$args) {
        Args::label($args, 'schema_file');
        /* load the raw schema data */
        $this->_schema_file = dirname(__FILE__).
                              DIRECTORY_SEPARATOR.
                              Schema::SCHEMA_FILE;
        if (isset($args->schema_file) === true) {
            /* first param, if present, overrides schema file */
            $this->_schema_file = $args->schema_file;
        }
        $this->_schema = JSON::decode_file($this->_schema_file);
        if ($this->_schema === NULL) {
            if (file_exists($this->_schema_file) === true) {
                /* schema file exists but isn't loading - probably a stray comma */
                throw new ConfigException(
                    'fatal; unable to parse schema file: '.$this->_schema_file
                );
            } else {
                /* sschema doesn't exist */
                throw new ConfigException(
                    'fatal; schema file: '.$this->_schema_file.', doesn\'t exist'
                );
            }
        }
    }    

    function get_site(&$args) {
        /* decide where to store site */
        $args->retval = $this->_teapot->get_model('settings')[0];
    }

    function get_collections(&$args) {
        /* get the top level collection views */
        if ($args->retval == NULL) {
            $args->retval = array();
        }
        $args->retval = array_merge_recursive($args->retval, $this->_schema['collections']);        
    }

    function get_fields(&$args) {
        Args::label($args, 'route', 'user');
        /* first check direct route */
        $route = $args->route;
        if (!isset($this->_schema[$route])) {
            /* no direct route for this, check for generic */
            $fragments = explode('/', $args->route, 2);
            $route = $fragments[0].'/*';
            error_log($route);
            if (!isset($this->_schema[$route])) {
                throw new NotFoundException($args->route.', invalid route');
            }
        }
        if ($args->retval == NULL) {
            $args->retval = array();
        }        
        $args->retval = array_merge_recursive($args->retval, $this->_schema[$route]);
    }
}

?>
