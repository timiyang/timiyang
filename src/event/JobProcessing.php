<?php

namespace timiyang\timiyang\Event;

class JobProcessing
{
    /** @var string */

    /** @var Job */
    public $job;

    public function __construct($job)
    {
        $this->job        = $job;
    }
}
