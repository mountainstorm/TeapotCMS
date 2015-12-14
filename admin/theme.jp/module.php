<?php

namespace Teapot;

require_once 'Twig'.DIRECTORY_SEPARATOR.'Autoloader.php';

\Twig_Autoloader::register();


class Theme extends Module {
    const OUTPUT_DIR = '../';

    function init(&$args) {
        Args::label($args, 'output');
        $this->_outputdir = Theme::OUTPUT_DIR;
        if (isset($args->output) === true) {
            $this->_outputdir = $args->output;
        }
    }

    function generate(&$args) {
        Args::label($args, 'route');

        /* load the template system */
        $loader = new \Twig_Loader_Filesystem($this->_module_name);
        $twig = new \Twig_Environment($loader); /* no cache */

        /* cleanup the dst directory */
        $dir = $this->_outputdir.DIRECTORY_SEPARATOR;
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    $fp = $this->_outputdir.DIRECTORY_SEPARATOR.$file;
                    /* delete any file which doens't start with a '.' and
                     * the attachments dir - is_file is unreliable */
                    if (is_dir($fp) !== false) {
                        if ($file == 'attachments') {
                            Theme::rmtree($fp);
                        }
                    } else {
                        /* file */
                        if ($file[0] !== '.') {
                            @unlink($fp);
                        }
                    }
                }
                closedir($dh);
            }
        }

        /* filter to copy attachments to publically readable location */
        $filter = new \Twig_SimpleFilter('attachment', function ($file) {
            $retval = '';
            if ($file !== NULL && $file != '') {
                $attachments = $this->_outputdir.DIRECTORY_SEPARATOR.
                               'attachments'.DIRECTORY_SEPARATOR;
                $dst = $attachments.$file;
                @mkdir($attachments);
                copy('attachments'.DIRECTORY_SEPARATOR.$file, $dst);
                /* return must be page relative url */
                $retval = 'attachments/'.$file;
            }
            return $retval;
        });
        $twig->addFilter($filter);
        
        /* single page site - so just pass the whole model to the template */
        $index = 'index.html';
        if (defined('Teapot\\TEAPOT_DEBUG') === true) {
            $index = '_'.$index;
            file_put_contents(
                $this->_outputdir.DIRECTORY_SEPARATOR.'index.php',
                '<?php
    namespace Teapot;

    chdir("admin");
    require_once "teapot.php";

    $teapot = new Teapot();
    $teapot->generate();

    error_log("regenerating site");

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $content_type = finfo_file($finfo, "..'.DIRECTORY_SEPARATOR.$index.'");
    finfo_close($finfo);
    header("Content-Type: ".$content_type);
    readfile("..'.DIRECTORY_SEPARATOR.$index.'");
?>'
            );
        }
        file_put_contents(
            $this->_outputdir.DIRECTORY_SEPARATOR.$index,
            $twig->render('index.html', $this->_teapot->get_model(NULL)),
            LOCK_EX
        );

        /* copy all the files in frontend.ui out as well */
        $src = $this->_module_name.DIRECTORY_SEPARATOR.'frontend.ui';
        $dir = @opendir($src);
        if ($dir !== false) {
            while (false !== ($file = readdir($dir))) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    copy(
                        $src.DIRECTORY_SEPARATOR.$file,
                        $this->_outputdir.DIRECTORY_SEPARATOR.$file
                    );
                }
            }
            closedir($dir);
        }
    }


    public static function rmtree($dir) { 
        $files = array_diff(scandir($dir), array('.', '..')); 
        foreach ($files as $file) { 
            if (is_dir($dir.'/'.$file)) {
                Theme::rmtree($dir.'/'.$file);
            } else {
                unlink($dir.'/'.$file);
            }
        } 
        return rmdir($dir); 
    }
}

?>
