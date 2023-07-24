<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace  timiyang\timiyang;

use Exception;
use RuntimeException;
use Carbon\Carbon;
use Throwable;
use timiyang\timiyang\Job\RabbitmqJob;
use timiyang\timiyang\Event;
use timiyang\timiyang\Rabbitmq\BaseRabbitmq;
use \timiyang\timiyang\Event\JobExceptionOccurred;
use \timiyang\timiyang\Event\JobProcessing;
use \timiyang\timiyang\Event\JobFailed;
use \timiyang\timiyang\Event\WorkerStopping;
use \timiyang\timiyang\Event\JobProcessed;
use timiyang\timiyang\Event\JobRelease;
use \timiyang\timiyang\Event\WorkerExceptionOccured;

class Worker
{

    protected  $rabbitmq;

    /**
     * Indicates if the worker should exit.
     *
     * @var bool
     */
    public $shouldQuit = false;


    //定义程序最大执行时间
    protected $timeout = 60;

    //queue任务处理类名称 带命名空间
    protected $queue = '';
    //任务重新放入队列延迟时间
    protected $delay = 0;

    //处理完任务之后等待时间
    protected $sleep = 0;

    //job 重试最大次数
    protected $maxTries = 0;
    //程序运行内存
    protected $memory = 128;
    //rabbitmq 自动应答开关
    protected $autoAck = false;

    /**
     * Indicates if the worker is paused.
     *
     * @var bool
     */
    public $paused = false;

    public $event;

    public function setEvent(Event $event)
    {
        $this->event = $event;
    }

    public function setRabbitmq(BaseRabbitmq $rabbitmq)
    {
        $this->rabbitmq = $rabbitmq;
    }

    public function setQueue($queueName)
    {
        $this->queue = $queueName;
    }

    public function setDelay($time = 0)
    {
        $this->delay = $time;
    }

    public function setSleep($time = 0)
    {
        $this->sleep = $time;
    }

    public function setTimeOut($time = 0)
    {
        $this->timeout = $time;
    }

    public function setMaxTries($maxTries = 0)
    {
        $this->maxTries = $maxTries;
    }

    public function setMemory($memory = 128)
    {
        $this->memory = $memory;
    }

    public function setAutoAck($autoAck = false)
    {
        $this->autoAck = $autoAck;
    }

    /**
     * @param string $connection
     * @param string $queue
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     * @param int    $memory
     * @param int    $timeout
     */
    public function master()
    {
        //监听推出信号
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        //@todo 重启机制
        $lastRestart = $this->getTimestampOfLastQueueRestart();
        //处理消息
        $this->rabbitmq->autoAck = $this->autoAck;
        $this->rabbitmq->queueBind();
        //prefetchSize：0
        //prefetchCount：会告诉RabbitMQ不要同时给一个消费者推送多于N个消息，即一旦有N个消息还没有ack，则该consumer将block掉，直到有消息ack
        //global：true\false 是否将上面设置应用于channel，简单点说，就是上面限制是channel级别的还是consumer级别
        //$this->channel->basic_qos(0, 1, false);

        //流量控制 我们可以通过设置 basic_qos 第二个参数 prefetch_count = 1。
        //这一项告诉RabbitMQ不要一次给一个消费者发送多个消息。或者换一种说法，在确认前一个消息之前，不要向消费者发送新的消息。
        //相反，新的消息将发送到一个处于空闲的消费者又或者只有consumer已经处理并确认了上一条message时queue才分派新的message给它
        $this->rabbitmq->channel->basic_qos(0, 1, false);
        //1:queue 要取得消息的队列名
        //2:consumer_tag 消费者标签
        //3:no_local false这个功能属于AMQP的标准,但是rabbitMQ并没有做实现.参考
        //4:no_ack  false收到消息后,是否不需要回复确认即被认为被消费
        //5:exclusive false排他消费者,即这个队列只能由一个消费者消费.适用于任务不允许进行并发处理的情况下.比如系统对接
        //6:nowait  false不返回执行结果,但是如果排他开启的话,则必须需要等待结果的,如果两个一起开就会报错
        //7:callback  null回调函数
        //8:ticket  null
        //9:arguments null
        $this->rabbitmq->channel->basic_consume($this->rabbitmq->queueName, '', false, $this->rabbitmq->autoAck, false, false, function ($msg) {
            $this->get($msg);
        });
        //监听消息
        while (count($this->rabbitmq->channel->callbacks)) {
            $this->rabbitmq->channel->wait();
            $this->stopIfNecessary($lastRestart, $this->memory);
        }
        $this->rabbitmq->closeConnetct();
    }

    /**
     * 消息处理
     */
    public function get($msg)
    {
        //@todo PARMA TOJOB
        $param = $msg->body;
        $job = new RabbitmqJob($param, $this->rabbitmq);
        //定时器定义任务执行时间
        if ($this->supportsAsyncSignals()) {
            $this->registerTimeoutHandler($job, $this->timeout);
        }
        try {
            $this->runJob($job);
        } catch (Exception | Throwable $e) {
            $this->event->trigger(new WorkerExceptionOccured($e));
        }

        if (!$this->rabbitmq->autoAck) {
            //手动ack应答
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        }
        //Send a message with the string "quit" to cancel the consumer. 发送退出信号
        // if ($msg->body === 'quit') {
        //     $msg->delivery_info['channel']->basic_cancel($msg->delivery_info['consumer_tag']);
        // }
    }


    
    protected function stopIfNecessary($lastRestart, $memory)
    {
        if ($this->shouldQuit || $this->queueShouldRestart($lastRestart)) {
            $this->stop();
        } elseif ($this->memoryExceeded($memory)) {
            $this->stop(12);
        }
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param int|null $lastRestart
     * @return bool
     */
    protected function queueShouldRestart($lastRestart)
    {
        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param int $memoryLimit
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * 获取队列重启时间
     * @return mixed
     */
    protected function getTimestampOfLastQueueRestart()
    {
        // if ($this->cache) {
        //     return $this->cache->get('think:queue:restart');
        // }
    }

    /**
     * Register the worker timeout handler.
     *
     * @param Job|null $job
     * @param int      $timeout
     * @return void
     */
    protected function registerTimeoutHandler($job, $timeout)
    {
        pcntl_signal(SIGALRM, function () {
            $this->kill(1);
        });

        pcntl_alarm(
            max($this->timeoutForJob($job, $timeout), 0)
        );
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param int $status
     * @return void
     */
    public function stop($status = 0)
    {
        $this->event->trigger(new WorkerStopping($status));

        exit($status);
    }

    /**
     * Kill the process.
     *
     * @param int $status
     * @return void
     */
    public function kill($status = 0)
    {
        $this->event->trigger(new WorkerStopping($status));

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param Job|null $job
     * @param int      $timeout
     * @return int
     */
    protected function timeoutForJob($job, $timeout)
    {
        return $job && !is_null($job->timeout()) ? $job->timeout() : $timeout;
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals()
    {
        return extension_loaded('pcntl');
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }



    /**
     * 执行任务
     * @param Job    $job
     * @param string $connection
     * @param int    $maxTries
     * @param int    $delay
     * @return void
     */
    protected function runJob($job)
    {
        $this->process($job);
    }



    /**
     * Process a given job from the queue.
     * @param string $connection
     * @param Job    $job
     * @param int    $maxTries
     * @param int    $delay
     * @return void
     * @throws Exception
     */
    public function process($job)
    {
        try {
            //钩子 开始处理
            $this->event->trigger(new JobProcessing($job));

            //触发执行太多次，失败
            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $job,
            );

            $job->fire();
            //钩子 处理完成
            $this->event->trigger(new JobProcessed($job));
        } catch (Exception | Throwable $e) {
            try {
                if (!$job->hasFailed()) {
                    $this->markJobAsFailedIfWillExceedMaxAttempts($job, (int) $this->maxTries, $e);
                }

                //钩子 任务异常
                $this->event->trigger(new JobExceptionOccurred($job, $e));
            } finally {
                if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                    //重回队列
                    $this->event->trigger(new JobRelease($job, $e));
                    $job->release($this->delay);
                }
            }
            throw $e;
        }
    }

    /**
     * @param string $connection
     * @param Job    $job
     * @param int    $maxTries
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($job)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $this->maxTries;

        $timeoutAt = $job->timeoutAt();

        if ($timeoutAt && Carbon::now()->getTimestamp() <= $timeoutAt) {
            return;
        }

        if (!$timeoutAt && (0 === $maxTries || $job->attempts() <= $maxTries)) {
            return;
        }
        $this->failJob($job, $e = new Exception(
            $job->getName() . ' has been attempted too many times or run too long. The job may have previously timed out.'
        ));

        throw $e;
    }

    /**
     * @param string    $connection
     * @param Job       $job
     * @param int       $maxTries
     * @param Exception $e
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($job, $maxTries, $e)
    {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->timeoutAt() && $job->timeoutAt() <= Carbon::now()->getTimestamp()) {
            $this->failJob($job, $e);
        }

        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    /**
     * @param string    $connection
     * @param Job       $job
     * @param Exception $e
     */
    protected function failJob($job, $e)
    {
        $job->markAsFailed();

        if ($job->isDeleted()) {
            return;
        }

        try {
            $job->delete();

            $job->failed($e);
        } finally {
            //钩子 任务失败
            $this->event->trigger(new JobFailed(
                $job,
                $e ?: new RuntimeException('ManuallyFailed')
            ));
        }
    }

    /**
     * Sleep the script for a given number of seconds.
     * @param int $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }
}
