<?php

//ini_set( 'max_execution_time', '6000' );
//ignore_user_abort( true );
//set_time_limit( 6000 );

$system_path = __DIR__;

define( 'BASEPATH', str_replace( '\\', '/', $system_path ) );


require_once 'core/autoload.php';
require_once 'core/config.php';

$depkasaWeb = new controllers\DepkasaWeb( $config );
$action     = 'index';

if ( isset( $_REQUEST['action'] ) )
{
    $action = $_REQUEST['action'];
}
if ( !method_exists( $depkasaWeb, $action ) )
{
    header( 'HTTP/1.1 400 Bad Request .', TRUE, 400 );
    header( "Content-Type: text/html;charset=utf-8" );
    echo 'Метод не существует<br/>';
    exit( 3 );
}

$depkasaWeb->$action();

