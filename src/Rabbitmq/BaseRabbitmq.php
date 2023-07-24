<?php

namespace timiyang\timiyang\Rabbitmq;


use PhpAmqpLib\Connection\AMQPStreamConnection;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

 class BaseRabbitmq
{
    //MQ的默认连接配置
    public $config = array(
        'host' => '', //ip
        'port' => '',      //端口号
        'user' => '',     //用户
        'password' => '', //密码
        'vhost' => '/'         //虚拟host
    );
    public $connection;     //链接
    public $channel;        //信道

    public $exchangeName = '';     //交换机名
    public $queueName = '';        //队列名
    public $routeKey = '';         //路由键
    public $exchangeType = 'direct';    //交换机类型

    public $autoAck = true; //是否自动ack应答

   

    public function __construct($exchangeName, $queueName, $routeKey, $exchangeType = 'direct', $config = array())
    {
        $this->exchangeName = empty($exchangeName) ? '' : $exchangeName;
        $this->queueName = empty($queueName) ? '' : $queueName;
        $this->routeKey = empty($routeKey) ? '' : $routeKey;
        $this->exchangeType = empty($exchangeType) ? '' : 'direct';
        if (!empty($config)) {
            $this->setConfig($config);
        }
        $this->createConnect();
    }

    //创建连接与信道
    private function createConnect()
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $user = $this->config['user'];
        $password = $this->config['password'];
        $vhost = $this->config['vhost'];
        if (empty($host) || empty($port) || empty($user) || empty($password)) {
            throw new \Exception('RabbitMQ的连接配置不正确');
        }
        //创建链接
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        //创建信道
        $this->channel = $this->connection->channel();
        //交换机(延时)
        $this->createExchange();
        //queue(延时)
        $this->createQueue();
        //绑定交换机
        $this->queueBind();
    }

    //创建交换机
    private function createExchange()
    {

        //* type: direct        // 交换机类型，分别为direct/fanout/topic，参考另外文章的Exchange Type说明。    x-delayed-message 延时队列类型
        //创建交换机$channel->exchange_declare($exhcange_name,$type,$passive,$durable,$auto_delete);
        //passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
        // 如果设置true存在则返回OK，否则就报错。设置false存在返回OK，不存在则自动创建 延时交换机需要在web管理页面创建
        // web 创建 需设置Arguments:参数 x-delayed-type= 交换机类型,分别为direct/fanout/topic
        //durable：true、false true：服务器重启会保留下来Exchange。警告：仅设置此选项，不代表消息持久化。即不保证重启后消息还在
        //autoDelete:true、false.true:当已经没有消费者时，服务器是否可以删除该Exchange
        $this->channel->exchange_declare($this->exchangeName, 'x-delayed-message', false, true, false);

        //$this->channel->queue_declare($this->queueName, false, true, false, false);
    }

    //创建queue (延时)
    private function createQueue()
    {
        // 设置延时队列类型
        $args = new AMQPTable([
            'x-delayed-type' => $this->exchangeType,

        ]);
        //passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
        //durable：true、false true：在服务器重启时，能够存活
        //exclusive ：是否为当前连接的专用队列，在连接断开后，会自动删除该队列
        //autodelete：当没有任何消费者使用时，自动删除该队列
        //arguments: 自定义规则
        //声明初始化一条队列
        //参数：队列名，是否检测同名队列，是否开启队列持久化，是否能被其他队列访问，通道关闭后是否删除队列
        //$channel->queue_declare($amqpDetail['queue_name'], false, true, false, false, false, $args);
        $this->channel->queue_declare($this->queueName, false, true, false, false, false, $args);
    }

    //将队列与某个交换机进行绑定，并使用路由关键字
    //参数：队列名，交换机名，路由键名
    public function queueBind()
    {
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->routeKey);
    }

    //发送消息
    public function sendMessage($data, $delay_time = 1000)
    {
        /*
         * 创建AMQP消息类型
         * $messageBody:消息体
         * delivery_mode 消息是否持久化
         *      AMQPMessage::DELIVERY_MODE_NON_PERSISTENT = 1; 不持久化
         *      AMQPMessage::DELIVERY_MODE_PERSISTENT = 2; 持久化
         */
        $message = new AMQPMessage($data, array(
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable([
                // 设置延迟时间（单位：毫秒）
                'x-delay' => $delay_time,
            ])
        ));


        /*
         * 发送消息
         * msg       // AMQP消息内容
         * exchange  // 交换机名称
         */
        $this->channel->basic_publish($message, $this->exchangeName, $this->routeKey);
    }

   

 

    public function closeConnetct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    //重新设置MQ的链接配置
    public function setConfig($config)
    {
        if (!is_array($config)) {
            throw new \Exception('config不是一个数组');
        }
        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }
    }
}
