<?php
require_once __DIR__ . '/../vendor/autoload.php';
use timiyang\timiyang\Event;
use timiyang\timiyang\Event\JobProcessed;

$event = new Event();
$event->listen(JobProcessed::class, function(JobProcessed $event){
   echo  $event->job;
    echo '处理完成中';
});
$event->trigger(new JobProcessed(1,3));