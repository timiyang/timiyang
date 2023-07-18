<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \timiyang\timiyang\Rabbitmq\RabbitmqService;

class Consumer extends RabbitmqService
{
    /**
     * 初始化
     */
    public function __construct()
    {
        $amqp = [
            'host' => '127.0.0.1',
            'port' => '5672',
            'user' => 'guest',
            'password' => 'guest',
            'vhost' => 'duanju_test'
        ];
        $amqpDetail = [
            'exchange_name' => 'direct_exchange_delay_test',
            'exchange_type' => 'direct', //直连
            'queue_name' => 'direct_queue_delay_test',
            'route_key' => 'direct_routerking_delay_test',
            'consumer_tag' => 'direct'
        ];
        parent::__construct($amqpDetail['exchange_name'], $amqpDetail['queue_name'], $amqpDetail['route_key'], $amqpDetail['exchange_type'], $amqp);
    }
    public function doProcess($param)
    {
        echo '接收到任务，处理中............' . "\n";
        echo $param;
        echo "\n" . '任务处理完成............' . "\n";
    }
}

$consumer = new Consumer();
//是否自动应答 false 开启手动应答
$ackFlag = false;
echo '等待接收任务............' . "\n";
//阻塞处理任务
$consumer->dealMq($ackFlag);
$consumer->closeConnetct();
echo '任务处理完成............' . "\n";
