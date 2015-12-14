<?php

namespace Teapot;

/* if defined causes extra debug */
const TEAPOT_DEBUG = true;

/* CLEF appid/ecret - used for oauth */
const AUTH_APPID = 'd59557806380d9195987e4c2f605cf09';
const AUTH_SECRET = '7e1310bd4b43748b81e820fa8651a50b';

/* the public URL of the frontend - overrides any other value IF set */
const SITE_URL = 'http://menwillhang.mountainstorm.co.uk/';

/* modules to load in 'push' order top to bottom.  Should be modulename or 
   an array(modulename, param1, param2) to pass when creating */
$MODULES = array(
	'auth.clef',

	'theme.jp',
	'schema.jp',
	
	'backend.json'
);

?>