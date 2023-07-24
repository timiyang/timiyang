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

namespace  timiyang\timiyang\Job;

use timiyang\timiyang\Rabbitmq\BaseRabbitmq as RabbitmQueue;
use timiyang\timiyang\Job;

class RabbitmqJob extends Job
{

    /**
     * The redis queue instance.
     * @var RedisQueue
     */
    protected $rabbitmq;

    /**
     * The database job payload.
     * @var Object
     */
    protected $job;

    /**
     * The Redis job payload inside the reserved queue.
     *
     * @var string
     */
    protected $reserved;

    public function __construct($job, RabbitmQueue $rabbitmq)
    {

        $this->job        = $job;
        $this->rabbitmq =  $rabbitmq;
        $reserved = json_decode($job, true);
        $reserved['attempts']++;
        $reserved = json_encode($reserved);
        $this->reserved   = $reserved;
    }

    /**
     * Get the number of times the job has been attempted.
     * @return int
     */
    public function attempts()
    {
        return $this->payload('attempts') + 1;
    }

    /**
     * Get the raw body string for the job.
     * @return string
     */
    public function getRawBody()
    {
        return $this->job;
    }

    /**
     * 删除任务
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        //$this->redis->deleteReserved($this->queue, $this);
    }

    /**
     * 重新发布任务
     *
     * @param int $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);
        $this->rabbitmq->sendMessage($this->reserved, $delay);
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->payload('id');
    }

    /**
     * Get the underlying reserved Redis job.
     *
     * @return string
     */
    public function getReservedJob()
    {
        return $this->reserved;
    }
}
