<?php

namespace timiyang\timiyang\Event;

class JobProcessed
{
   
    /** @var Job */
    public $job;

    public function __construct( $job)
    {
        $this->job        = $job;
    }
}
