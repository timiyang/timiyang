<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \PhpAmqpLib\Message\AMQPMessage;
use \timiyang\timiyang\Rabbitmq\RabbitmqService;

class Send extends RabbitmqService
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
            'vhost' => 'duanju_test',
        ];
        $amqpDetail = [
            'exchange_name' => 'direct_exchange_delay_test',
            'exchange_type' => 'direct', //直连
            'queue_name' => 'direct_queue_delay_test',
            'route_key' => 'direct_routerking_delay_test',
            'consumer_tag' => 'direct',
        ];
        parent::__construct($amqpDetail['exchange_name'], $amqpDetail['queue_name'], $amqpDetail['route_key'], $amqpDetail['exchange_type'], $amqp);
    }
    public function doProcess($param)
    {
    }

    public function push($message, $delay_time = 1000)
    {
        //设为confirm模式
        $this->channel->confirm_select();
        $this->sendMessage($message, $delay_time);
        //消息发送状态回调(成功回调)
        $this->channel->set_ack_handler(function (AMQPMessage $message) {
            echo "成功发送了内容:" . $message->body;
        });
        //失败回调
        $this->channel->set_nack_handler(function (AMQPMessage $message) {
            echo "返回的失败信息:" . $message->body;
        });
        //阻塞等待应答
        $this->channel->wait_for_pending_acks();
        $this->closeConnetct();
    }
}

$publisher = new Send();

$data = [
    'job' => 'timiyang\\timiyang\\Jober\\DemoJober',
    'maxTries' => 4,
    'timeout' => null,
    'data' => [
        'order_id' => time()
    ],
    'id' => 'cjWBzuGnSUlbPvJZPxZyqmrEauvWNopX',
    'attempts' => 0

];
$publisher->push(json_encode($data), 1000);

$publisher->closeConnetct();
