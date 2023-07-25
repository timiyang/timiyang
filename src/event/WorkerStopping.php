<?php

namespace timiyang\timiyang\Event;

class WorkerStopping
{
    /**
     * The exit status.
     *
     * @var int
     */
    public $status;

    const STATUS_MAP = [
        'quit' => 0, //接收到退出信号
        'longtime' => 1, //job 运行过长
        'memory_out' => 12, //运行内存超出设置值

    ];

    /**
     * Create a new event instance.
     *
     * @param int $status
     * @return void
     */
    public function __construct($status = 0)
    {
        $this->status = $status;
    }

    public function getMessage()
    {
        switch ($this->status) {
            case self::STATUS_MAP['quit']:
                $message = '接收到退出信号';
                break;
            case self::STATUS_MAP['longtime']:
                $message = 'job 运行过长';
                break;
            case self::STATUS_MAP['memory_out']:
                $message = '运行内存超出设置值';
                break;
            default:
                $message = '未知';
        }
        return $message;
    }
}
