<?php


namespace Teapot;

require_once 'teapot.php';


function send_file($path) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $content_type = finfo_file($finfo, $path);
    finfo_close($finfo);
    header('Content-Type: '.$content_type);
    readfile($path);
}


$teapot = new Teapot();
if ($teapot->get_user() !== NULL) {
	/* send admin.ui/admin.html */
	send_file('admin.ui/admin.html');

} else {
	/* send admin.ui/login.html */
	send_file('admin.ui/login.html');
}

?>
