<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \timiyang\timiyang\Rabbitmq\BaseRabbitmq;
use \PhpAmqpLib\Message\AMQPMessage;
use \timiyang\timiyang\Worker;
use \timiyang\timiyang\Event;
use \timiyang\timiyang\Event\JobExceptionOccurred;
use \timiyang\timiyang\Event\JobProcessing;
use \timiyang\timiyang\Event\JobFailed;
use \timiyang\timiyang\Event\WorkerStopping;
use \timiyang\timiyang\Event\JobProcessed;
use \timiyang\timiyang\Event\JobRelease;
use \timiyang\timiyang\Event\WorkerExceptionOccured;





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
$rabbitmq = new BaseRabbitmq($amqpDetail['exchange_name'], $amqpDetail['queue_name'], $amqpDetail['route_key'], $amqpDetail['exchange_type'], $amqp);

//事件监听
$event = new Event();
$event->listen(JobProcessing::class, function (JobProcessing $event) {
    $content =    '名称:' . $event->job->getName();
    console_log('开始处理job',  $content);
});
$event->listen(JobProcessed::class, function (JobProcessed $event) {
    console_log('job处理完成');
});
$event->listen(JobFailed::class, function (JobFailed $event) {
    $content =   $event->job->getName() . "\n" . 'message:' . $event->exception->getMessage();
    console_log('job处理失败', $content);
});
$event->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) {
    $content =  $event->job->getName() . ':异常' . "\n" . '信息:' . $event->exception->getMessage() . "\n" .
        '文件:' . $event->exception->getFile() . "\n" .
        '行号:' . $event->exception->getLine();


    console_log('job处理异常',  $content);
});
$event->listen(WorkerStopping::class, function (WorkerStopping $event) {
    $content =    '状态码:' . $event->status;
    console_log('working stopping',  $content);
});
$event->listen(JobRelease::class, function (JobRelease $event) {
    $content =    '名称:' . $event->job->getName() . "\n" .
        'ID:' . $event->job->getJobId();
    console_log('重新加入推送至消息队列',  $content);
});
$event->listen(WorkerExceptionOccured::class, function (WorkerExceptionOccured $event) {
    $content =   '信息:' . $event->exception->getMessage() . "\n" .
        '文件:' . $event->exception->getFile() . "\n" .
        '行号:' . $event->exception->getLine();
    console_log('队列运行:异常',  $content);
});
$work = new Worker();
//是否自动应答 false 开启自动应答
$ackFlag = false;
$work->setAutoAck($ackFlag);
$work->setMaxTries(3);
$work->setDelay(10);
$work->setTimeOut(60);
$work->setEvent($event);
$work->setRabbitmq($rabbitmq);
echo '开启队列............' . "\n";
$work->master();

echo '退出队列............' . "\n";
