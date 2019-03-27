<?php

namespace libraries;

use PDO;

defined( 'BASEPATH' ) OR exit( 'No direct script access allowed' );

/**
 * Description of DepkasaMOCK
 * Класс для работы с Depkasa MOCK.
 *
 *
 *
 * @author alaxji
 */
class DepkasaMOCK
{

    private static $apiKey           = 'daef4ff758859227ac5ff22b3d73e090';
    private static $secretKey        = 'f07fce0a';
    private static $paymentURL       = 'https://mock01.ecpdss.net/depkasa/a/payment/welcome';
    private static $paymentDetailURL = 'https://mock01.ecpdss.net/depkasa/a/payment/detail';
    private static $callbackStatuses = [
        'APPROVED' => [ 'success', 'Покупка успешно завершена' ],
        'DECLINED' => [ 'decline', 'Покупка отклонена по причинам от эквайера' ],
        'CANCELED' => [ 'decline', 'Транзакция отменена пользователем' ],
        'PENDING'  => [ '', '' ],
        'ERROR'    => [ 'decline', 'Ошибка в ПС' ],
    ];
    public static $db;

    public function __construct( $config = [] )
    {
        if ( !isset( $config['database'] ) || !is_array( $config['database'] ) )
        {
            header( 'HTTP/1.1 503 Service Unavailable.', TRUE, 503 );
            header( "Content-Type: text/html;charset=utf-8" );
            echo 'Задайте параметры БД<br/>';
            die(); // EXIT_CONFIG;
        }
        $database = $config['database'];
        $dbs      = $database['drv'] . ':host=' . $database['host'] . ';dbname=' . $database['dbname'] . ';charset=utf8;';
        try
        {
            self::$db = new \PDO( $dbs, $database['user'], $database['pass'], [ PDO::ATTR_PERSISTENT => true, PDO::ERRMODE_SILENT => true ] );
        } catch ( \Exception $ex )
        {
            header( 'HTTP/1.1 503 Service Unavailable.', TRUE, 503 );
            header( "Content-Type: text/html;charset=utf-8" );
            echo 'Нет доступа к базе данных<br/>';
            exit( 3 ); // EXIT_CONFIG;
        }
    }

    /**
     *
     * @param type $amount
     * @param type $currency
     * @param type $referenceNo
     * @param type $timestamp
     * @return type
     */
    public static function generateToken( $amount, $currency = false, $referenceNo = false, $timestamp = false )
    {

        $params = [
            'apiKey'      => self::$apiKey,
            'amount'      => $amount,
            'currency'    => ($currency === false) ? 'EUR' : $currency,
            'referenceNo' => ($referenceNo === false) ? uniqid( 'reference_' ) : $referenceNo,
            'timestamp'   => ($timestamp === false) ? time() : $timestamp,
        ];

        $rawHash = self::$secretKey . implode( '', $params );
        return md5( $rawHash );
    }

    public static function checkToken( $request )
    {
        $rawHash = self::$secretKey
            . self::$apiKey
            . $request['code']
            . $request['status']
            . $request['amount']
            . $request['currency']
            . $request['referenceNo']
            . $request['timestamp'];
        return ($request['token'] == md5( $rawHash ));
    }

    /**
     * token
     * apiKey
     * email
     * birthday
     * amount
     * currency
     * returnUrl
     * referenceNo
     * timestamp
     * language
     * billingFirstName
     * billingLastName
     * billingAddress1
     * billingCity
     * billingPostcode
     * billingCountry
     * paymentMethod
     * number
     * cvv
     * expiryMonth
     * expiryYear
     * callbackUrl
     *
     */
    public function payment()
    {
        $callbackUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . '?action=callback';
        $returnUlr   = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];

        $amount = filter_input( INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT );

        if ( $amount <= 0 )
        {
            die( json_encode( [ 'code' => 1, 'msg' => "Сумма платежа {$_POST['amount']} должна быть положительна " ] ) );
        }
        $amount      = (int) (round( $amount, 2 ) * 100);
        $timestamp   = time();
        $ts_created  = date( 'Y-m-d H:i:s', $timestamp );
        $referenceNo = md5( microtime() );
        $insert      = "INSERT INTO transactions (reference_no, amount, currency, ts_created) VALUES('$referenceNo', $amount, 'EUR', '$ts_created' )";
        if ( false === self::$db->query( $insert ) )
        {
            echo json_encode( [ 'code' => 1, 'msg' => 'Ошибка записи транзакции в БД :: ' . implode( ' ', self::$db->errorInfo() ) ] );
            die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
        }

        echo json_encode( [ 'code' => 0, 'msg' => "$ts_created - статус 'init'" ] );
//echo "$ts_created - статус 'init'";
        @ob_flush();
        flush();
        ob_clean();

        $postdata = [
            'token'            => self::generateToken( $amount, 'EUR', $referenceNo, $timestamp ),
            'apiKey'           => self::$apiKey,
            'email'            => 'ek4all@mail.ru',
            'birthday'         => '1970-01-01',
            'amount'           => $amount,
            'currency'         => 'EUR',
            'returnUrl'        => $returnUlr,
            'referenceNo'      => $referenceNo,
            'timestamp'        => $timestamp,
            'language'         => 'en',
            'billingFirstName' => 'A',
            'billingLastName'  => 'D',
            'billingAddress1'  => 'A',
            'billingCity'      => 'C',
            'billingPostcode'  => 'P',
            'billingCountry'   => 'C',
            'paymentMethod'    => 'GIFTCARD',
            'number'           => '4012888888881881',
            'cvv'              => '123',
            'expiryMonth'      => '2',
            'expiryYear'       => '2',
            'callbackUrl'      => $callbackUrl,
        ];
        //die( print_r( $postdata, true ) );
        $opts     = [ 'http' =>
            [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query( $postdata )
            ],
        ];
        sleep( 1 );

        self::setStatus( $referenceNo, 'external' );

        $ch     = curl_init();
        curl_setopt( $ch, CURLOPT_URL, self::$paymentURL );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
        $answer = curl_exec( $ch );
        if ( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) == 500 )
        {
            $data = [
                'status'  => 'decline',
                'comment' => '500 Внутренняя ошибка сервера'
            ];
            self::updateTransaction( $referenceNo, $data );
            echo ',' . json_encode( [ 'code' => 0, 'msg' => "Платёж не удался. Внутренняя ошибка сервера" ] );
            die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
        }
        curl_close( $ch );

        self::setStatus( $referenceNo, 'delivered' );
        sleep( 1 );

        $answer = json_decode( $answer, true );
        if ( $answer['status'] == 'DECLINED' )
        {
            $data = [
                'status'  => 'decline',
                'comment' => print_r( $answer, true )
            ];
            self::updateTransaction( $referenceNo, $data );
            echo ',' . json_encode( [ 'code' => 0, 'msg' => 'Платёж отклонён :: ' . print_r( $answer, true ) ] );
            die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
        }
        elseif ( $answer['status'] == 'WAITING' )
        {
            $data = [
                'status'              => 'awaiting_callback',
                'transaction_foreign' => "{$answer['transactionId']}",
            ];
            self::updateTransaction( $referenceNo, $data );
        }
        else
        {
            $data = [
                'comment' => 'Неизвестный статус' . print_r( $answer, true )
            ];
            self::updateTransaction( $referenceNo, $data );
            echo ',' . json_encode( [ 'code' => 0, 'msg' => "Неизвестный статус" . print_r( $answer, true ) ] );
            die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
        }
        return $referenceNo;
    }

    public function callback()
    {

        // Если на сервере что-то случилось и ответ пришёл спустя  какое-то время.....
        //
        $referenceNo = $_REQUEST['referenceNo'];
        self::setStatus( $referenceNo, 'received', false );

        if ( !self::checkToken( $_REQUEST ) )
        {
            header( 'HTTP/1.1 409 Conflict.', TRUE, 409 );
            header( "Content-Type: text/html;charset=utf-8" );
            echo 'Токен не действительный';
            die();
        }
        sleep( 1 );
        if ( $_REQUEST['status'] != 'PENDING' )
        {
            if ( key_exists( $_REQUEST['status'], self::$callbackStatuses ) )
            {
                $data = [
                    'status'  => self::$callbackStatuses[$_REQUEST['status']][0],
                    'comment' => self::$callbackStatuses[$_REQUEST['status']][1],
                ];
                self::updateTransaction( $referenceNo, $data, false );
                exit();
            }
            else
            {
                $data = [
                    'status'  => 'decline',
                    'comment' => "Неизвесный статус {$_REQUEST['status']}",
                ];
                self::updateTransaction( $referenceNo, $data, false );
                exit();
            }
        }

        $postdata      = [
            'apiKey'      => self::$apiKey,
            'referenceNo' => $referenceNo,
        ];
        //die( print_r( $postdata, true ) );
        $opts          = [ 'http' =>
            [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query( $postdata )
            ],
        ];
        $is_pending    = true;
        $pending_count = 0;
        $ch            = curl_init();
        curl_setopt( $ch, CURLOPT_URL, self::$paymentDetailURL );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );

        while ( $is_pending )
        {
            $pending_count++;
            self::updateTransaction( $referenceNo, [ 'pending_count' => $pending_count ], false );
            $answer = curl_exec( $ch );
            if ( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) == 500 )
            {
                $data = [
                    'status'  => 'decline',
                    'comment' => '500 Внутренняя ошибка сервера'
                ];
                self::updateTransaction( $referenceNo, $data );
                echo ',' . json_encode( [ 'code' => 0, 'msg' => "Платёж не удался. Внутренняя ошибка сервера" ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
            }
            $answer = json_decode( $answer, true );
            error_log( print_r( $answer, true ) );
            if ( $answer['status'] != 'PENDING' )
            {
                if ( key_exists( $answer['status'], self::$callbackStatuses ) )
                {
                    $data = [
                        'status'  => self::$callbackStatuses[$answer['status']][0],
                        'comment' => self::$callbackStatuses[$answer['status']][1],
                    ];
                    self::updateTransaction( $referenceNo, $data, false );
                    curl_close( $ch );
                    exit();
                }
                else
                {
                    $data = [
                        'status'  => 'decline',
                        'comment' => "Неизвесный статус {$answer['status']}",
                    ];
                    self::updateTransaction( $referenceNo, $data, false );
                    curl_close( $ch );
                    exit();
                }
            }
            if ( $pending_count >= 10 )
            {
                $data = [
                    'status'  => 'decline',
                    'comment' => "Кол-во опросов статуса 10-и.",
                ];
                self::updateTransaction( $referenceNo, [ 'status' => "decline", 'comment' => "'Кол-во опросов статуса 10-и.'" ], false );
                curl_close( $ch );
                exit();
            }
            else
            {
                sleep( 5 );
            }
        }

        curl_close( $ch );
    }

    public static function setStatus( $referenceNo, $status, $with_output = true )
    {
        self::updateTransaction( $referenceNo, [ 'status' => $status ], $with_output );
    }

    public static function updateTransaction( $referenceNo, $fields = [], $with_output = true )
    {
        if ( empty( $fields ) )
        {
            return false;
        }

        $sets = array ();
        foreach ( $fields as $key => $value )
        {
            if ( gettype( $value ) == "string" )
            {
                $sets[] = "$key = " . self::$db->quote( $value );
            }
            else
            {
                $sets[] = "$key = $value";
            }
        }

        $set = implode( ', ', $sets );

        if ( empty( $set ) )
        {
            return false;
        }
        $ts_modify = date( 'Y-m-d H:i:s' );
        $update    = "UPDATE transactions SET $set WHERE reference_no='$referenceNo'";
        if ( false === self::$db->query( $update ) )
        {
            if ( $with_output )
            {
                echo ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка обновления транзакции в БД :: ' . $update . ' :: ' . implode( ' ', self::$db->errorInfo() ) ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
            }
            else
            {
                header( 'HTTP/1.1 503 Service Unavailable.', TRUE, 503 );
                header( "Content-Type: text/html;charset=utf-8" );
                echo 'Ошибка обновления транзакции в БД :: ' . implode( ' ', self::$db->errorInfo() );
                die();
            }
        }
        if ( $with_output )
        {
            echo ',' . json_encode( [ 'code' => 0, 'msg' => "$ts_modify - статус {$fields['status']}" ] );
            echo str_repeat( ' ', 4096 );
            @ob_flush();
            flush();
        }
    }

}
