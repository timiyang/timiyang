<?php

namespace timiyang\timiyang\Event;



class JobFailed
{
   
    /** @var Job */
    public $job;

    /** @var \Exception */
    public $exception;

    public function __construct( $job, $exception)
    {
        $this->job        = $job;
        $this->exception  = $exception;
    }
}
