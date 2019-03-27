<?php

namespace libraries;

/**
 * Description of outPut
 *
 * @author alaxji
 */
class outPut
{

    /**
     * @var array
     */
    public static $httpCodes = [
        400 => 'Bad Request',
        503 => 'Service Unavailable.',
        520 => 'Unknown Error'
    ];

    /**
     * @var boolean
     */
    protected static $firstJSON = true;

    /**
     * @param mixed $value
     * @param boolean $echo
     * @param int $buffer_length
     * @param boolean $flush
     * @return mixed
     */
    public static function likeJSON( $value, $echo = true, $buffer_length = 4096, $flush = true )
    {
        $stringJSON = json_encode( $value );
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
                echo $buffer_length == 0 ? '' : str_repeat( ' ', $buffer_length );
                @ob_flush();
                flush();
            }
            return true;
        }
        return $outPut;
    }

    /**
     * @param array $header =
     *                  [
     *                      'header'=>
     *                          [
     *                      'code' => code,
     *                              'headers' => ['header1', 'header2',..., 'headerN'],
     *                          ]
     *                      'msg' => 'message'
     *                  ]
     */
    public static function likeError( $header )
    {
        if ( !isset( $header['header']['code'] ) )
        {
            return false;
        }
        $code    = $header['header']['code'];
        $desc    = isset( self::$httpCodes[$code] ) ? self::$httpCodes[$code] : "";
        $headers = isset( $header['header']['headers'] ) ? $header['header']['headers'] : [];
        if ( !headers_sent() )
        {
            header( "HTTP/1.1 $code $desc", TRUE, $code );
            foreach ( $headers as $head )
            {
                header( $head );
            }
        }
        echo isset( $header['msg'] ) ? $header['msg'] : "";
        return true;
    }

}
