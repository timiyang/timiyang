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
}
