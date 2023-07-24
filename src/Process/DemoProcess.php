<?php
namespace timiyang\timiyang\Process;
use timiyang\timiyang\Job;
class DemoProcess {
    public function fire(Job $job, $params){
        var_dump($params);
    }
}