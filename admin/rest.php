<?php

namespace Teapot;

/* REST dispatch object - does all the security checks */
class REST {
    const HTTP_UNAUTHORIZED = 401; // session not authenticated
    const HTTP_FORBIDDEN = 403; // something went wrong during auth
    const HTTP_NOT_FOUND = 404; // invalid route
    const HTTP_NOT_ACCEPTABLE = 406; // somethings wrong, verb/route/mime

    const SOURCE_JAVASCRIPT = 'HTTP_X_REQUESTED_WITH';

    private $_dispatch = array();

    function dispatch($verb, $route) {
        /*
         * first lets send some headers to prevent bad use of the webpage
         * ref: https://www.owasp.org/index.php/List_of_useful_HTTP_headers
         */
        header('X-Frame-Options: deny'); // no rendering in a frame
        header('X-Content-Type-Options: nosniff'); // prevent MIME sniffing

        /* dispatch the request */
        $dispatcher = $this->_find_dispatcher($route, $matches);
        if ($dispatcher !== NULL) {
            if ($this->_check_source($verb, $dispatcher) === true) {
                if ($this->_check_authorized($dispatcher) === true) {
                    $methods = $dispatcher['methods'];
                    if (isset($methods[$verb]) === true ) {
                        /* nearly ready to go */
                        $this->_dispatch_method($dispatcher, $verb, $matches);
                    } else {
                        /* wrong verb */
                        http_response_code(REST::HTTP_NOT_ACCEPTABLE);
                    }
                } else {
                    /* access control prevented using this */
                    http_response_code(REST::HTTP_UNAUTHORIZED);
                }
            } else {
                /* the source was wrong - came from browser not JS */
                http_response_code(REST::HTTP_NOT_ACCEPTABLE);
            }
        } else {
            /* wrong route */
            http_response_code(REST::HTTP_NOT_FOUND);
        }
    }

    function register($route, $dispatcher) {
        $this->_dispatch[$route] = $dispatcher;
    }

    function _find_dispatcher($route, &$matches) {
        $retval = NULL;
        foreach ($this->_dispatch as $re => $dispatcher) {
            if (preg_match($re, $route, $matches) === 1) {
                $retval = $dispatcher;
                break;
            }
        }
        return $retval;
    }

    function _check_source($verb, $dispatcher) {
        $retval = false;
        if ($verb !== 'GET') {
            if (isset($dispatcher['source']) === true) {
                if (isset($_SERVER[$dispatcher['source']]) === true) {
                    /*
                     * CSRF protection - if we don't have X-Requested-With request
                     * may have come from browser link e.g. img; giving rise to a 
                     * CSRF possibility.  As this is an API it should only be 
                     * accesible by javascript, which can add this header jQuery 
                     * always does).  CSRF with javascript is protected by the 
                     * browsers cross origin protection
                     */
                    $retval = true;
                }
            } else {
                /* source not set, so allow everyone */
                $retval = true;
            }
        } else {
            /* get doesn't need the protection */
            $retval = true;
        }
        return $retval;
    }

    function _check_authorized($dispatcher) {
        $retval = false;
        if (isset($dispatcher['authorized']) !== true) {
            /* NULL always returns true */
            $retval = true;
        } else {
            if (isset($_SESSION[$dispatcher['authorized']]) === true &&
                $_SESSION[$dispatcher['authorized']] === true) {
                /* need auth, and is authed */
                $retval = true;
            }
        }
        return $retval;
    }

    function _dispatch_method($dispatcher, $verb, $matches) {
        /* finally check if the content type is right on POST/PUT */
        if ($verb === 'POST' || $verb === 'PUT') {
            if (isset($dispatcher['content-type']) === false ||
                $_SERVER['CONTENT_TYPE'] === $dispatcher['content-type']) {
                $this->_dispatch_handler(
                    $dispatcher,
                    $verb,
                    $matches,
                    json_decode(
                        file_get_contents('php://input'), true
                    )
                    // XXX: should utf8 decode?
                );
            } else {
                /* wrong mime type */
                http_response_code(REST::HTTP_NOT_ACCEPTABLE);
            }
        } else {
            $this->_dispatch_handler($dispatcher, $verb, $matches, NULL);
        }            
    }

    function _dispatch_handler($dispatcher, $verb, $matches, $data) {
        $reply = $dispatcher['methods'][$verb]($matches, $data);
        if ($reply !== NULL) {
            if (isset($dispatcher['content-type']) !== false) {
                header('Content-Type: '.$dispatcher['content-type']);
            }
            echo json_encode($reply); // XXX: should utf8 encode?
        }
    }
}

?>