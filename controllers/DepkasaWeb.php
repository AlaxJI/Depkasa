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
        $depkasa = new \libraries\DepkasaMOCK( $this->config );
        $depkasa->callback();
    }

    public function payment()
    {
        ini_set( 'max_execution_time', '600' );
        ignore_user_abort( true );
        set_time_limit( 600 );

        $depkasa     = new \libraries\DepkasaMOCK( $this->config );
        echo '[';
        // Отправляем платёж
        $referenceNo = $depkasa->payment();
        // Ждём callback
        // Фактически все остальные действия будет производить функция callback.
        // Это нужно для того, если у платёжного агрегатора что-то случилось и ответ пришёл спустя какое-то время.
        if ( false === ($get_status  = \libraries\DepkasaMOCK::$db->prepare( "SELECT id, status FROM transactions WHERE reference_no = '$referenceNo'" )) )
        {
            echo ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка подготовки запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] );
            die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
        }

        $awaiting_callback = true;
        $itteration        = 0;
        while ( $awaiting_callback )
        {
            if ( false === ($get_status->execute()) )
            {
                echo ',' . json_encode( [ 'code' => 2, 'msg' => 'Ошибка запроса в БД :: ' . implode( ' ', \libraries\DepkasaMOCK::$db->errorInfo() ) ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
            }
            $result = $get_status->fetch( PDO::FETCH_ASSOC );

            if ( in_array( $result['status'], [ 'init', 'external', 'delivered' ] ) )
            {
                echo ',' . json_encode( [ 'code' => 0, 'msg' => "Ошибка данных, статус {$result['status']} не ожидался." ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
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
                echo ',' . json_encode( [ 'code' => 0, 'msg' => "Более чем минуты небыло ответа от платёжного агрегатора. Вероятна ошибка." ] );
                die( ',' . json_encode( [ 'code' => 0, 'msg' => "Операция завершена" ] ) );
            }
            else
            {
                echo ',' . json_encode( [ 'code' => 0, 'msg' => "" ] );
                echo str_repeat( ' ', 4096 );
                @ob_flush();
                flush();
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

}
