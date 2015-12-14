<?php

namespace Teapot;

require_once 'settings.php';

require_once 'rest.php';
require_once 'teapot.php';


class API {
    protected $_teapot = NULL;
    protected $_dispatcher = NULL;
    protected $_user = NULL;

    function __construct() {
        /* load the teapot */
        $this->_teapot = new Teapot();

        /* create and initialize api routes */
        $this->_dispatcher = new REST();

        /* pull current user and register routes */
        $this->_user = $this->_teapot->get_user();
        $this->_register_public_routes();
        $this->_register_private_routes();
    }

    function _register_public_routes() {
        /* api/v?/site - public info */
        $this->_dispatcher->register('/^site$/', array(
            'source' => REST::SOURCE_JAVASCRIPT,
            'content-type' => 'application/teapot.site+json',
            'methods' => array(
                'GET' => function ($args) {
                    return $this->_get_site();
                }
            )
        ));

        /* api/v?/login - the oauth redirect url */
        $this->_dispatcher->register('/^login$/', array(
            'methods' => array(
                'GET' => function ($args) {
                    if ($this->_teapot->login() !== true) {
                        http_response_code(REST::HTTP_UNAUTHORIZED);
                    }
                    /* redirect the user back to the admin.ui */
                    header('Location: ../../');
                }
            )
        ));
        /* api/v?/logout - oauth logout webhook */
        $this->_dispatcher->register('/^logout$/', array(
            'methods' => array(
                'GET' => function ($args) {
                    $this->_teapot->logout();
                    header('Location: ../../');
                },
                'POST' => function ($args) {
                    $this->_teapot->logout();
                    header('Location: ../../');
                }
            )
        )); 
    }

    function _register_private_routes() {
        /* api/v?/attachments/* - images etc required in the admin view */
        $this->_dispatcher->register('/^attachments\/(?P<route>.+)$/', array(
            'source' => REST::SOURCE_JAVASCRIPT,
            'authorized' => Auth::AUTHORIZED,
            'methods' => array(
                'GET' => function ($args) {
                    return $this->_get_attachment($args['route']);
                },
                'POST' => function ($args, $model) {
                    $this->_put_attachment($args['route']);
                }
            )
        ));       

        /* api/v?/form - form for site */
        $this->_dispatcher->register('/^forms\/(?P<route>.+)$/', array(
            'source' => REST::SOURCE_JAVASCRIPT,            
            'content-type' => 'application/teapot.form+json',
            'authorized' => Auth::AUTHORIZED,
            'methods' => array(
                'GET' => function ($args) {
                    try {
                        return array(
                            "fields" => $this->_teapot->get_fields($args['route'], $this->_user),
                            "model" => $this->_teapot->get_model($args['route']),
                        );
                        // XXX: filter model fields
                    } catch (NotFoundException $e) {
                        http_response_code(REST::HTTP_NOT_FOUND);
                        echo $e;
                    }
                },
                'PUT' => function ($args, $data) {
                    try {
                        // XXX: get fields and validate incoming data
                        $this->_teapot->put_model($args['route'], $data['model']);
                    } catch (NotFoundException $e) {
                        http_response_code(REST::HTTP_NOT_FOUND);
                        echo $e;
                    }
                }
            )
        ));

        /* api/v?/extensions/* - admin.ui extensions */
        $this->_dispatcher->register('/^extensions\/(?P<route>.+)$/', array(
            'authorized' => Auth::AUTHORIZED,
            'methods' => array(
                'GET' => function ($args) {
                    return $this->_get_extension_file($args['route']);
                }
            )
        ));                
    }

    function dispatch($verb, $request) {
        /* dispatch request */
        debug('dispatching: '.$verb.' '.$request);
        $this->_dispatcher->dispatch($verb, $request);
    }

    function _get_site() {
        /* return public, pre-login, info about this site */
        $site = $this->_teapot->get_site();
        if ($site === NULL) {
            /* set safe defaults */
            $site = array('title' => '', 'url' => '');
        }
        $site['title'] = (isset($site['title']) === true) ? $site['title']: '';
        $site['image'] = (isset($site['image']) === true) ? $site['image']: '';
        $site['url'] = (isset($site['url']) === true) ? $site['url']: '';
        if (defined('Teapot\\SITE_URL') === true) {
            $site['url'] = SITE_URL;
        }
        $retval = array(
            'title' => $site['title'],
            'url' => $site['url'],
            'authorized' => ($this->_user !== NULL)
        );
        if ($this->_user !== NULL) {
            /* authorized - pass the extensions info */
            $retval['image'] = $site['image'];
            $retval['extensions'] = $this->_get_extensions();
            $retval['collections'] = $this->_teapot->get_collections();

        } else {
            /* unauthorized - pass the oauth info */
            $retval['oauth'] = array (
                'appid' => AUTH_APPID,
                'state' => $this->_teapot->login_nonce(),
                'redirect' => $this->_get_admin_url().'/api/v1/login'
            );
        }
        return $retval;
    }

    function _get_attachment($route) {
        /* only allow read if auth'd or it's the site image */
        $path = $this->_safe_path('attachments', $route);
        if ($path !== NULL) {
            $this->_send_file($path);
        } else {   
            /* not a valid file */
            http_response_code(REST::HTTP_NOT_FOUND);
        }
    }

    function _put_attachment($route) {
        $files = $_FILES;
        if (count($files) === 1 &&
            isset($files['file']) === true &&
            isset($files['file']['name']) === true &&
            isset($files['file']['tmp_name']) === true &&
            isset($files['file']['error']) === true &&
            $files['file']['error'] === UPLOAD_ERR_OK) {
            // XXX: what to do if name already exists
            $filename = basename($files['file']['name']);
            copy(
                $files['file']['tmp_name'],
                'attachments'.DIRECTORY_SEPARATOR.$filename
            );
            /* we don't re-generate the page as it's only when
               the model changes we want to commit the change */
        } else {
            /* no data */
            http_response_code(REST::HTTP_NOT_ACCEPTABLE);           
        }
    }

    function _get_extensions() {
        /* get a list of all routes to extension admin.js files */
        $extensions = array();
        foreach ($this->_teapot->get_modules() as $module_name) {
            /* only load for modules which are directories */
            if (file_exists($module_name.DIRECTORY_SEPARATOR.
                            'admin.ui'.DIRECTORY_SEPARATOR.
                            'admin.js') === true) {
                array_push($extensions, $module.'/admin.js');
            }
        }
        return $extensions;
    }

    function _send_file($path) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $content_type = finfo_file($finfo, $path);
        finfo_close($finfo);
        header('Content-Type: '.$content_type);
        readfile($path);
    }

    function _get_extension_file($extensions, $path) {
        /* return the reuested eextension file
         * 
         * when someone requests 'api/v?/extensions/theme.demo/img.jpg' then
         * will get the contents of 'theme.demo/admin.ui/img.jpg'
         * EXERYTHING in the extensions folder is avaliable for the admin users
         * to retrieve, and bootstrapped by the extensions admin.js file */
        $path = NULL;
        $parts = explode('/', $path, 2);
        /* must have a module name */
        if (count($parts) > 1) {     
            /* get_modules includes native ones, only match submodules */
            if (strpos($parts[0], '.') !== false) {
                /* does the module name match */
                if (in_array($parts[0], $this->get_modules()) === true) {
                    $path = $this->_safe_path($parts[0], $parts[1]);
                }
            }
        }
        if ($path !== NULL) {
            $this->_send_file($path);
        } else {
            /* not a valid file */
            http_response_code(REST::HTTP_NOT_FOUND);
        }
    }    

    function _safe_path($root, $path) {
        /* check and convert a requested file into a one under the root */
        $retval = NULL;
        $source = realpath($root);
        $target = realpath($root.DIRECTORY_SEPARATOR.$path);
        if ($source !== false && $target !== false) {
            if (strpos($target, $source) === 0) {
                $retval = $target;
            } else {
                /* someones trying .. paths */
                error_log('user: '.$this->_user['id'].
                          ' requested invalid file: '.
                          $path);
            }
        }
        return $retval;
    }    

    function _get_admin_url() {
        /* create redirect url */
        $protocol = 'http://';
        if (isset($_SERVER['HTTPS']) === true &&
            $_SERVER['HTTPS'] !== 'off' &&
            $_SERVER['HTTPS'] !== '') {
            $protocol = 'https://';
        }
        /* discard path components until we see admin */
        $path = $_SERVER['REQUEST_URI'];
        while (($i = strrpos($path, '/')) !== false) {
            if (substr($path, $i+1) == 'admin') {
                break;
            }
            $path = substr($path, 0, $i);
        }
        return $protocol.$_SERVER['HTTP_HOST'].$path;
    }
}

$api = new API();
$api->dispatch($_SERVER['REQUEST_METHOD'], $_REQUEST['route']);

?>
