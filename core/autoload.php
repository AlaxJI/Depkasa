<?php

defined( 'BASEPATH' ) OR exit( 'No direct script access allowed' );

spl_autoload_register(
    function ( $class ) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . ".php";
    if ( file_exists( $file ) )
    {
        require_once($file);
    }

    $file = str_replace( [ '\\' ], DIRECTORY_SEPARATOR, $class ) . '.php';
    if ( file_exists( $file ) )
    {
        require_once $file;
    }
}, true, true );
