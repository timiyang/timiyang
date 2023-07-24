<?php

namespace timiyang\timiyang\Event;

use Exception;


class JobExceptionOccurred
{


    /**
     * The job instance.
     *
     * @var Job
     */
    public $job;

    /**
     * The exception instance.
     *
     * @var Exception
     */
    public $exception;

    /**
     * Create a new event instance.
     *
     * @param string    $connectionName
     * @param Job       $job
     * @param Exception $exception
     * @return void
     */
    public function __construct( $job, $exception)
    {
        $this->job            = $job;
        $this->exception      = $exception;
    }
}
