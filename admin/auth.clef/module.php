<?php

namespace Teapot;

require_once 'clef/init.php';

class Auth extends Module {
    const AUTHORIZED = 'authorized';

    private $_users = NULL;
    private $_user = NULL;

    function init(&$args) {
        /* start the session and get the users state */
        session_start();
        /* check the model has users */
        $_SESSION[Auth::AUTHORIZED] = false;
        $this->_users = $this->_teapot->get_model('users');
        if ($this->_users !== NULL && isset($_SESSION['user']) === true) {
            /* find the user by their id */
            $user = &$this->_find_user($_SESSION['user']);
            if ($user !== NULL &&
                isset($_SESSION['logged_in_at']) == true &&
                isset($user['logged_out_at']) === true) {
                /* check login/logout times */
                if ($user['logged_out_at'] < $_SESSION['logged_in_at']) {
                    /* they have logged in after last logout */
                    $_SESSION[Auth::AUTHORIZED] = true;
                    $this->_user = &$user;
                } else {
                    /* user has logged out - destroy session and restart */
                    session_destroy(); // this session is done, discard
                    session_start(); // new shiny session        
                    $this->_clear_session();
                }
            }
        }
        if ($_SESSION[Auth::AUTHORIZED] !== true) {
            /* session has not attempted auth yet - ensure its clean */
            $this->_clear_session();
        }
    }

    function login_nonce(&$args) {
        /* generate a nonce (state) for this oauth request */
        function base64url_encode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
        $_SESSION['nonce'] = base64url_encode(openssl_random_pseudo_bytes(32));
        $args->retval = $_SESSION['nonce'];
    }

    function login(&$args) {
        $args->retval = false;
        $is_valid = (isset($_SESSION['nonce']) === true &&
                     strlen($_SESSION['nonce']) > 0 &&
                     $_SESSION['nonce'] === $_GET['state']);
        unset($_SESSION['nonce']);
        if ($is_valid === true) {
            /* take the supplied code and check with clef for the user info */
            \Clef\Clef::initialize(AUTH_APPID, AUTH_SECRET);
            try {
                $response = \Clef\Clef::get_login_information($_GET['code']);
                $usr = $response->info;
                /* check if email is in out dataset */
                $user = &$this->_find_user($usr->id, $usr->email);
                if ($user !== NULL) {
                    /* this user is allowed to login -
                       give this session a new id */
                    session_regenerate_id(true);
                    $_SESSION['user'] = $usr->id;
                    $_SESSION['logged_in_at'] = time();
                    $_SESSION[Auth::AUTHORIZED] = true;
                    /* now update email and username in database */
                    $this->_update_user($user, $usr);
                    error_log('login successful: '.$usr->id.', '.$usr->email);
                    /* updated model so save */
                    // XXX: this isn't multi user safe - we write in once hit
                    $this->_teapot->put_model('users', $this->_users);
                    $args->retval = true;
                } else {
                    error_log('login failed: '.$usr->id.', '.$usr->email);
                }
            } catch (Exception $e) {
                /* pass */
            }
        }
    }

    function logout(&$args) {
        $args->retval = false;
        error_log('log out requested');
        \Clef\Clef::initialize(AUTH_APPID, AUTH_SECRET);
        if(isset($_POST['logout_token'])) {
            try {
                $id = \Clef\Clef::get_logout_information(
                    $_POST['logout_token']
                );
                $user = &$this->_find_user($id);
                if ($user !== NULL) {
                    $user['logged_out_at'] = time();
                    /* updated model so save */
                    // XXX: this isn't multi user safe - we write in once hit
                    $this->_teapot->put_model('users', $this->_users);
                    error_log('logged out via Clef: '.$user['id'].', '.$user['email']);
                }
                $args->retval = true;
            } catch (Exception $e) {
                /* pass */
            }
        } else {
            /* not the result of a oauth logout - check session */
            if ($_SESSION[Auth::AUTHORIZED] === true) {
                $this->_user['logged_out_at'] = time();
                session_destroy(); // this session is done, discard
                session_start(); // new shiny session
                $this->_clear_session();
                /* updated model so save */
                // XXX: this isn't multi user safe - we write in once hit
                $this->_teapot->put_model('users', $this->_users);
                $args->retval = true;
                error_log('logged out: '.$this->_user['id'].', '.$this->_user['email']);
            }
        }
    }

    function get_user(&$args) {
        /* return the current user object */
        $args->retval = $this->_user;
    }

    function &_find_user($id, $email = NULL) {
        $retval = NULL;
        if ($this->_users !== NULL) {
            /* check ALL user_id's first */
            foreach ($this->_users as &$user) {
                if (isset($user['id']) === true &&
                    $user['id'] === $id) {
                    $retval = &$user;
                    //$user_ret = &$user;
                    break;
                }
            }
            /* if we got no matches (first time) try email addr match */
            if ($retval === NULL && $email !== NULL) {
                foreach ($this->_users as &$user) {
                    if (isset($user['email']) === true &&
                        $user['email'] === $email) {
                        $retval = &$user;
                        break;
                    }
                }
            }
        }
        return $retval;
    }

    function _update_user(&$user, $usr) {
        /* ensure there is always a logged_out_at */
        if (isset($user['logged_out_at']) !== true) {
            $user['logged_out_at'] = 0;
        }
        /* update all the user info */
        if (isset($user['id']) !== true) {
            /* only allow setting if not already set */
            $user['id'] = $usr->id;
        }
        if (isset($usr->first_name) === true) {
            $user['first_name'] = $usr->first_name;
        }
        if (isset($usr->last_name) === true) {
            $user['last_name'] = $usr->last_name;
        }
        if (isset($usr->email) === true) {
            $user['email'] = $usr->email;
        }
    }

    function _clear_session() {
        unset($_SESSION['user']);
        unset($_SESSION['logged_in_at']);
        $_SESSION[Auth::AUTHORIZED] = false;
    }
}

?>
