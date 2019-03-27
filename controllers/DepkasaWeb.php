<?php

namespace controllers;

defined( 'BASEPATH' ) OR exit( 'No direct script access allowed' );

use PDO;
use libraries;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Depkasa
 *
 * @author alaxji
 */
class DepkasaWeb
{

    public $db;
    public $config;

    public function __construct( &$config = [] )
    {
        $this->config = &$config;
    }

    public function index()
    {
        require_once BASEPATH . '/views/main.php';
    }

    public function callback()
    {
        ini_set( 'max_execution_time', '600' );
        ignore_user_abort( true );
        try
        {
            $depkasa = new \libraries\DepkasaMOCK( $this->config );
            $depkasa->callback();
        } catch ( \Exception $exc )
        {
            $this->sendError( $exc->getCode(), \libraries\outPut::likeJSON( [ 'msg' => $exc->getMessage() ] ) );
        }
    }

    public function payment()
    {
        ini_set( 'max_execution_time', '600' );
        ignore_user_abort( true );
        set_time_limit( 600 );

        try
        {
            $depkasa = new \libraries\DepkasaMOCK( $this->config );
        } catch ( \Exception $exc )
        {
            $this->sendError( $exc->getCode(), \libraries\outPut::likeJSON( [ 'msg' => $exc->getMessage() ] ) );
        }
        $callbackUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . '?action=callback';
        $returnUlr   = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];

        $amount = filter_input( INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT );
        if ( $amount <= 0 )
        {
            $this->sendError( 400, \libraries\outPut::likeJSON( [ 'msg' => [ 'msg' => "Сумма платежа {$_POST['amount']} должна быть положительным числом " ] ], false ) );
        }
        $amount      = (int) (round( $amount, 2 ) * 100);
        $timestamp   = time();
        $ts_created  = date( 'Y-m-d H:i:s', $timestamp );
        $referenceNo = md5( microtime() );
        $postdata    = [
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

        // Совершаем платёж
        try
        {
            // Инициализируем
            $result = $depkasa->payment_init( $postdata );
            \libraries\outPut::likeJSON( $result );
            ($result['code'] == 0) || $this->sendError( 520, '' );

            // Отправляем
            $result = $depkasa->payment_external();
            \libraries\outPut::likeJSON( $result );
            ($result['code'] == 0) || $this->sendError( 520, '' );

            // Обрабатываем
            $result = $depkasa->payment_delivered();
            \libraries\outPut::likeJSON( $result );
            ($result['code'] == 0) || $this->sendError( 520, '' );

            // Завершаем
            $result = $depkasa->payment_deinit();
            \libraries\outPut::likeJSON( $result );
            ($result['code'] == 0) || $this->sendError( 520, '' );
        } catch ( Exception $exc )
        {
            $this->sendError( $exc->getCode(), \libraries\outPut::likeJSON( [ 'msg' => $exc->getMessage() ] ) );
        }

        // Ждём callback
        // Фактически все остальные действия будет производить функция callback.
        // Это нужно для того, если у платёжного агрегатора что-то случилось и ответ пришёл спустя какое-то время.
        if ( false === ($get_status = \libraries\DepkasaMOCK::$db->prepare( "SELECT id, status FROM transactions WHERE reference_no = '$referenceNo'" )) )
        {
            $this->sendError( 503, \libraries\outPut::likeJSON( [ 'msg' => 'Ошибка подготовки запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] ) );
        }

        $awaiting_callback = true;
        $itteration        = 0;
        while ( $awaiting_callback )
        {
            if ( false === ($get_status->execute()) )
            {
                $this->sendError( 503, \libraries\outPut::likeJSON( [ 'msg' => 'Ошибка запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] ) );
            }
            $result = $get_status->fetch( PDO::FETCH_ASSOC );

            if ( in_array( $result['status'], [ 'init', 'external', 'delivered' ] ) )
            {
                \libraries\outPut::likeJSON( [ 'code' => 0, 'msg' => "Ошибка данных, статус {$result['status']} не ожидался." ] );
                return;
            }
            elseif ( $result['status'] != 'awaiting_callback' )
            {
                $awaiting_callback = false;
                $transaction_id    = $result['id'];
            }
            sleep( 1 );
            $itteration ++;
            if ( $itteration > 60 )
            {
                \libraries\outPut::likeJSON( [ 'code' => 0, 'msg' => "Более чем минуты небыло ответа от платёжного агрегатора. Вероятна ошибка." ] );
                return;
            }
            else
            {
                \libraries\outPut::likeJSON( [ 'code' => 0, 'msg' => "" ] );
            }
        }
        if ( false === ($res = \libraries\DepkasaMOCK::$db->query( "SELECT id FROM transaction_statuses WHERE transaction_id = $transaction_id AND status = 'awaiting_callback'" )) )
        {
            die( ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка запроса транзакции в БД  :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] ) );
        }
        $res       = $res->fetch( PDO::FETCH_ASSOC );
        $status_id = $res['id'];

        $is_pending    = true;
        $pending_count = 1;
        $select        = "
            SELECT
                transaction_statuses.id
                ,transaction_statuses.status
                ,transaction_statuses.ts_created
                ,transactions.pending_count
            FROM transactions
                LEFT JOIN transaction_statuses ON transactions.id = transaction_statuses.transaction_id AND transaction_statuses.id > ?
            WHERE transactions.id = $transaction_id
            ORDER BY transaction_statuses.id";
        if ( false === ($get_status    = \libraries\DepkasaMOCK::$db->prepare( $select )) )
        {
            die( ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка подготовки запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] ) );
        }
        while ( $is_pending )
        {
            if ( false === ($get_status->execute( [ $status_id ] )) )
            {
                echo ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
            }

            $res = $get_status->fetchAll();
            foreach ( $res as $value )
            {
                if ( $value['status'] == 'success' || $value['status'] == 'decline' )
                {
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => "{$value['ts_created']} - статус {$value['status']}" ] );
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] );
                    exit();
                }
                elseif ( !empty( $value['status'] ) )
                {
                    $status_id = $value['id'];
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => "{$value['ts_created']} - статус {$value['status']}" ] );
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => date( "Y-m-d H:i:s" ) . " - запрос статуса" ] );
                    echo str_repeat( ' ', 4096 );
                    @ob_flush();
                    flush();
                }
                elseif ( $pending_count != $value['pending_count'] )
                {
                    $pending_count = $value['pending_count'];
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => date( "Y-m-d H:i:s" ) . " - запрос статуса" ] );
                    echo str_repeat( ' ', 4096 );
                    if ( $pending_count == 10 )
                    {
                        echo ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] );
                        exit();
                    }
                    @ob_flush();
                    flush();
                }
                else
                {
                    echo ',' . json_encode( [ 'code' => 0, 'msg' => "" ] );
                    echo str_repeat( ' ', 4096 );
                    @ob_flush();
                    flush();
                }
                sleep( 1 );
            }
        }


        //echo ',' . json_encode( [ 'code' => 0, 'msg' => "$status_id" ] );
        //@ob_flush();
        //flush();
    }

    protected function sendError( $code, $msg )
    {
        \libraries\outPut::likeError(
            [
                'header' => [
                    'code'   => $code,
                    'heares' => [
                        "Content-Type: text/html;charset=utf-8",
                    ]
                ],
                'msg'    => $msg,
        ] );
    }

}
