<?php

namespace timiyang\timiyang;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Restart
{
    public static function run()
    {
        $cache = new FilesystemAdapter();
        // 创建一个新元素并尝试从缓存中得到它
        $numProducts = $cache->getItem('timiyang-queue-restart');

        // assign a value to the item and save it
        // 对元素赋值并存储它
        $value =- time();
        $numProducts->set($value);
        $cache->save($numProducts);
    }
}
