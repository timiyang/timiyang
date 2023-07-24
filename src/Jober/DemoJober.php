<?php
namespace timiyang\timiyang\Jober;
use timiyang\timiyang\Job;
class DemoJober {
    public function fire(Job $job, $data){
        echo '我是任务:'.$job->getName()."\n";
        echo json_encode($data);

    }
}