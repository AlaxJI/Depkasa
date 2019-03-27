<?php

#namespace libraries;


$system_path = __DIR__;

define( 'BASEPATH', str_replace( '\\', '/', $system_path ) );


require_once 'core/autoload.php';

use \PHPUnit\Framework\TestCase;

/**
 * Description of unitTest
 *
 * @author alaxji
 */
class outPutTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider likeJSONProvider
     */
    public function testLikeJSON( $input1, $input2, $input3, $input4, $expectedOutput )
    {
        $result = \libraries\outPut::likeJSON( $input1, $input2, $input3, $input4 );
        $this->assertEquals( $result, $expectedOutput );
    }

    public function likeJSONProvider()
    {
        return [
            [ "string", false, 0, true, '["string"' ],
            [ [ "array" => "array" ], false, 0, true, ',{"array":"array"}' ],
            [ 100, false, 0, true, ',100' ],
            [ 100, true, 100, true, true ],
        ];
    }

    /**
     * @dataProvider likeErrorProvider
     */
    public function testLikeError( $input, $expectedOutput )
    {
        $result = \libraries\outPut::likeError( $input );
        $this->assertEquals( $result, $expectedOutput );
    }

    public function likeErrorProvider()
    {
        $test1 = [
            'header' =>
            [
                'headers' => [ 'header1.1', 'header1.2', 'header1.3' ],
            ],
            'msg'    => 'message1'
        ];
        $test2 = [
            'header' =>
            [
                'code'    => 503,
                'headers' => [ 'header1.1', 'header1.2', 'header1.3' ],
            ],
            'msg'    => 'message1'
        ];
        $test3 = [
            'header' =>
            [
                'code' => 503,
            ],
            'msg'    => 'message1'
        ];
        $test4 = [
            'header' =>
            [
                'code'    => 503,
                'headers' => [ 'header1.1', 'header1.2', 'header1.3' ],
            ],
        ];
        return [
            [ $test1, false ],
            [ $test2, true ],
            [ $test3, true ],
            [ $test4, true ],
        ];
    }

}
