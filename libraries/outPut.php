<?php

namespace libraries;

/**
 * Description of ajaxOutPut
 *
 * @author alaxji
 */
class outPut
{

    public static $httpCodes    = [
        400 => 'Bad Request',
        503 => 'Service Unavailable.',
        520 => 'Unknown Error'
    ];
    protected static $firstJSON = true;

    public static function likeJSON( $array, $echo = true, $buffer_length = 4096, $flush = true )
    {
        if ( !is_array( $array ) )
        {
            return false;
        }
        $stringJSON = json_encode( $array );
        if ( self::$firstJSON )
        {
            self::$firstJSON = false;
            $outPut          = "[$stringJSON";
        }
        else
        {
            $outPut = ",$stringJSON";
        }
        if ( $echo )
        {
            echo $outPut;
            if ( $flush )
            {
                echo str_repeat( ' ', $buffer_length );
                @ob_flush();
                flush();
            }
            return true;
        }
        return $outPut;
    }

    public static function likeAnswer( $array )
    {

    }

    public static function likeError( $header )
    {
        $code    = $header['header']['code'];
        $desc    = self::$httpCodes[$code];
        $headers = $header['header']['heares'];
        header( "HTTP/1.1 $code $desc", TRUE, $code );
        foreach ( $headers as $head )
        {
            header( $head );
        }
        echo $header['msg'];
        die( $code );
    }

}
