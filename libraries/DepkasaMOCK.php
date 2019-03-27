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

    private static $apiKey;
    private static $secretKey;
    private static $paymentURL       = 'https://mock01.ecpdss.net/depkasa/a/payment/welcome';
    private static $paymentDetailURL = 'https://mock01.ecpdss.net/depkasa/a/payment/detail';
    private static $postdata;
    private static $cURL;
    public static $db;
    private static $callbackStatuses = [
        'APPROVED' => [ 'success', 'Покупка успешно завершена', 1 ],
        'DECLINED' => [ 'decline', 'Покупка отклонена по причинам от эквайера', 1 ],
        'CANCELED' => [ 'decline', 'Транзакция отменена пользователем', 1 ],
        'PENDING'  => [ '', '', 0 ],
        'ERROR'    => [ 'decline', 'Ошибка в ПС', 1 ],
        'WAITING'  => [ 'awaiting_callback', '', 0 ],
    ];
    private $answer;
    private $referenceNo;

    public function __construct( $config = [] )
    {
        if ( !isset( $config['apiKey'] ) )
        {
            throw new \Exception( 'Нет ключа API', 503 );
        }
        else
        {
            self::$apiKey = $config['apiKey'];
        }

        if ( !isset( $config['secretKey'] ) )
        {
            throw new \Exception( 'Нет секретного ключа', 503 );
        }
        else
        {
            self::$secretKey = $config['secretKey'];
        }

        if ( !isset( $config['database'] ) || !is_array( $config['database'] ) || empty( $config['database'] ) )
        {
            throw new \Exception( 'Задайте параметры БД', 503 );
        }
        $database = $config['database'];
        $dbs      = $database['drv'] . ':host=' . $database['host'] . ';dbname=' . $database['dbname'] . ';charset=utf8;';
        try
        {
            self::$db = new \PDO( $dbs, $database['user'], $database['pass'], [ PDO::ATTR_PERSISTENT => true, PDO::ERRMODE_SILENT => true ] );
        } catch ( \Exception $ex )
        {
            throw new \Exception( 'Нет доступа к базе данных', 503 );
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

        $params  = [
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

    public function payment_init( $postdata )
    {
        $amount            = $postdata['amount'];
        $ts_created        = date( 'Y-m-d H:i:s', $postdata['timestamp'] );
        $timestamp         = $postdata['timestamp'];
        $referenceNo       = $postdata['referenceNo'];
        $this->referenceNo = $referenceNo;

        $postdata = array_merge(
            [
                'token'  => self::generateToken( $amount, 'EUR', $referenceNo, $timestamp ),
                'apiKey' => self::$apiKey, ],
            $postdata
        );


        self::$postdata = $postdata;

        $insert = "INSERT INTO transactions (reference_no, amount, currency, ts_created) VALUES('$referenceNo', $amount, 'EUR', '$ts_created' )";
        if ( false === self::$db->query( $insert ) )
        {
            throw new \Exception( 'Ошибка записи транзакции в БД :: ' . implode( ' ', self::$db->errorInfo() ), 503 );
        }

        return [ 'code' => 0, 'msg' => "$ts_created - статус 'init'" ];
    }

    public function payment_external()
    {
        if ( empty( $this->referenceNo ) )
        {
            return [ 'code' => 999, 'msg' => 'Платёж не инициализирован' ];
        }

        try
        {
            self::updateTransaction( $this->referenceNo, [ 'status' => 'external' ] );
        } catch ( \Exception $ex )
        {
            throw new \Exception( $ex->getMessage(), $ex->getCode() );
        }

        return [ 'code' => 0, 'msg' => date( "Y-m-d H:i:s" ) . " - статус 'external'" ];
    }

    public function payment_delivered()
    {
        if ( empty( $this->referenceNo ) )
        {
            return [ 'code' => 999, 'msg' => 'Платёж не инициализирован' ];
        }

        self::$cURL   = curl_init();
        curl_setopt( self::$cURL, CURLOPT_URL, self::$paymentURL );
        curl_setopt( self::$cURL, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( self::$cURL, CURLOPT_POST, 1 );
        curl_setopt( self::$cURL, CURLOPT_POSTFIELDS, self::$postdata );
        curl_setopt( self::$cURL, CURLOPT_CONNECTTIMEOUT, 30 );
        $this->answer = curl_exec( self::$cURL );

        if ( curl_getinfo( self::$cURL, CURLINFO_HTTP_CODE ) == 500 )
        {
            $data = [
                'status'  => 'decline',
                'comment' => '500 Внутренняя ошибка сервера платёжного аргегатора'
            ];
            try
            {
                self::updateTransaction( $this->referenceNo, $data );
            } catch ( \Exception $ex )
            {
                throw new \Exception( $ex->getMessage(), $ex->getCode() );
            }
            return [ 'code' => 998, 'msg' => "Платёж не удался. Внутренняя ошибка сервера платёжного аргегатора" ];
        }
        curl_close( self::$cURL );
        try
        {
            self::updateTransaction( $this->referenceNo, [ 'status' => 'delivered' ] );
        } catch ( \Exception $ex )
        {
            throw new \Exception( $ex->getMessage(), $ex->getCode() );
        }
        return [ 'code' => 0, 'msg' => date( "Y-m-d H:i:s" ) . " - статус 'delivered'" ];
    }

    public function payment_deinit()
    {
        if ( empty( $this->referenceNo ) )
        {
            return [ 'code' => 999, 'msg' => 'Платёж не инициализирован' ];
        }

        $answer = json_decode( $this->answer, true );
        $status = $answer['status'];
        if ( key_exists( $status, self::$callbackStatuses ) )
        {
            $data = [
                'status'  => self::$callbackStatuses[$answer['status']][0],
                'comment' => self::$callbackStatuses[$answer['status']][1],
            ];
            try
            {
                self::updateTransaction( $this->referenceNo, $data );
            } catch ( Exception $ex )
            {
                throw new \Exception( $ex->getMessage(), $ex->getCode() );
            }
            return [ 'code' => self::$callbackStatuses[$answer['status']][2], 'msg' => date( "Y-m-d H:i:s" ) . " - статус '" . self::$callbackStatuses[$answer['status']][0] . "'" ];
        }
        else
        {
            $data = [
                'comment' => 'Неизвестный статус' . print_r( $answer, true )
            ];
            try
            {
                self::updateTransaction( $this->referenceNo, $data );
            } catch ( Exception $ex )
            {
                throw new \Exception( $ex->getMessage(), $ex->getCode() );
            }
            return [ 'code' => 997, 'msg' => "Неизвестный статус" . print_r( $answer, true ) ];
        }
    }

    public function callback()
    {

        // Если на сервере что-то случилось и ответ пришёл спустя  какое-то время.....
        //
        $referenceNo = $_REQUEST['referenceNo'];
        try
        {
            self::updateTransaction( $referenceNo, [ 'status' => 'received' ] );
        } catch ( \Exception $ex )
        {
            throw new \Exception( $ex->getMessage(), $ex->getCode() );
        }

        if ( !self::checkToken( $_REQUEST ) )
        {
            throw new \Exception( 'Токен не действительный', 409 );
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
                try
                {
                    self::updateTransaction( $referenceNo, $data );
                } catch ( \Exception $ex )
                {
                    throw new \Exception( $ex->getMessage(), $ex->getCode() );
                }
            }
            else
            {
                $data = [
                    'status'  => 'decline',
                    'comment' => "Неизвесный статус {$_REQUEST['status']}",
                ];
                try
                {
                    self::updateTransaction( $referenceNo, $data );
                } catch ( \Exception $ex )
                {
                    throw new \Exception( $ex->getMessage(), $ex->getCode() );
                }
            }
        }

        $postdata      = [
            'apiKey'      => self::$apiKey,
            'referenceNo' => $referenceNo,
        ];
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
                try
                {
                    self::updateTransaction( $referenceNo, $data );
                } catch ( \Exception $ex )
                {
                    curl_close( $ch );
                    throw new \Exception( $ex->getMessage(), $ex->getCode() );
                }
                curl_close( $ch );
                return [ 'code' => 996, 'msg' => "Платёж не удался. Внутренняя ошибка сервера" ];
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
                    try
                    {
                        self::updateTransaction( $referenceNo, $data );
                    } catch ( \Exception $ex )
                    {
                        curl_close( $ch );
                        throw new \Exception( $ex->getMessage(), $ex->getCode() );
                    }
                    curl_close( $ch );
                    return;
                }
                else
                {
                    $data = [
                        'status'  => 'decline',
                        'comment' => "Неизвесный статус {$answer['status']}",
                    ];
                    self::updateTransaction( $referenceNo, $data );
                    curl_close( $ch );
                    return;
                }
            }
            if ( $pending_count >= 10 )
            {
                $data = [
                    'status'  => 'decline',
                    'comment' => "Кол-во опросов статуса 10-и.",
                ];
                try
                {
                    self::updateTransaction( $referenceNo, $data );
                } catch ( \Exception $ex )
                {
                    curl_close( $ch );
                    throw new \Exception( $ex->getMessage(), $ex->getCode() );
                }
                curl_close( $ch );
                return;
            }
            else
            {
                sleep( 5 );
            }
        }
        curl_close( $ch );
    }

    public static function updateTransaction( $referenceNo, $fields = [] )
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
            throw new \Exception( 'Ошибка обновления транзакции в БД :: ' . implode( ' ', self::$db->errorInfo() ), 503 );
        }
        return true;
    }

}
