<?php

namespace timiyang\timiyang\Event;

class JobRelease
{
   
    /** @var Job */
    public $job;

    public function __construct( $job)
    {
        $this->job        = $job;
    }
}
