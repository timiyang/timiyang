<?php

namespace timiyang\timiyang\Event;

class WorkerExceptionOccured
{
   

    /** @var \Exception */
    public $exception;

    public function __construct(  $exception)
    {
       
        $this->exception  = $exception;
    }
}
