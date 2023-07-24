<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace timiyang\timiyang;

use Closure;
use Exception;
use ReflectionException;
use ReflectionFunction;
use think\helper\Str;

/**
 * 事件管理类
 * @package think
 */
class Event
{
    /**
     * 监听者
     * @var array
     */
    protected $listener = [];

    /**
     * 事件别名
     * @var array
     */
    protected $bind = [
        //'AppInit'     => event\AppInit::class,

    ];

    public function __construct()
    {
    }

    /**
     * 批量注册事件监听
     * @access public
     * @param array $events 事件定义
     * @return $this
     */
    public function listenEvents(array $events)
    {
        foreach ($events as $event => $listeners) {
            if (isset($this->bind[$event])) {
                $event = $this->bind[$event];
            }

            $this->listener[$event] = array_merge($this->listener[$event] ?? [], $listeners);
        }

        return $this;
    }

    /**
     * 注册事件监听
     * @access public
     * @param string $event    事件名称
     * @param mixed  $listener 监听操作（或者类名）
     * @param bool   $first    是否优先执行
     * @return $this
     */
    public function listen(string $event, $listener, bool $first = false)
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        if ($first && isset($this->listener[$event])) {
            array_unshift($this->listener[$event], $listener);
        } else {
            $this->listener[$event][] = $listener;
        }

        return $this;
    }

    /**
     * 是否存在事件监听
     * @access public
     * @param string $event 事件名称
     * @return bool
     */
    public function hasListener(string $event): bool
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        return isset($this->listener[$event]);
    }

    /**
     * 移除事件监听
     * @access public
     * @param string $event 事件名称
     * @return void
     */
    public function remove(string $event): void
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        unset($this->listener[$event]);
    }

    /**
     * 指定事件别名标识 便于调用
     * @access public
     * @param array $events 事件别名
     * @return $this
     */
    public function bind(array $events)
    {
        $this->bind = array_merge($this->bind, $events);

        return $this;
    }

    /**
     * 触发事件
     * @access public
     * @param string|object $event  事件名称
     * @param mixed         $params 传入参数
     * @param bool          $once   只获取一个有效返回值
     * @return mixed
     */
    public function trigger($event, $params = null, bool $once = false)
    {
        if (is_object($event)) {
            $params = $event;
            $event = get_class($event);
        }

        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        $result = [];
        $listeners = $this->listener[$event] ?? [];

        if (strpos($event, '.')) {
            [$prefix, $event] = explode('.', $event, 2);
            if (isset($this->listener[$prefix . '.*'])) {
                $listeners = array_merge($listeners, $this->listener[$prefix . '.*']);
            }
        }

        $listeners = array_unique($listeners, SORT_REGULAR);

        foreach ($listeners as $key => $listener) {
            $result[$key] = $this->dispatch($listener, $params);

            if (false === $result[$key] || (!is_null($result[$key]) && $once)) {
                break;
            }
        }

        return $once ? end($result) : $result;
    }

    /**
     * 触发事件(只获取一个有效返回值)
     * @param      $event
     * @param null $params
     * @return mixed
     */
    public function until($event, $params = null)
    {
        return $this->trigger($event, $params, true);
    }

    /**
     * 执行事件调度
     * @access protected
     * @param mixed $event  事件方法
     * @param mixed $params 参数
     * @return mixed
     */
    protected function dispatch($event, $params = null)
    {
        if (!is_string($event)) {
            $call = $event;
        } elseif (strpos($event, '::')) {
            $call = $event;
        } else {
            $obj = new $event();
            $call = [$obj, 'handle'];
        }

        return $this->invoke($call, $params);
    }

    /**
     * 调用反射执行callable 支持参数绑定
     * @access public
     * @param mixed $callable
     * @param array $vars       参数
     * @param bool  $accessible 设置是否可访问
     * @return mixed
     */
    public function invoke($callable, $vars = null)
    {
        if ($callable instanceof Closure) {
            return $this->invokeFunction($callable, $vars);
        } else {
            throw new Exception('暂时只支持闭包函数');
        }
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|Closure $function 函数或者闭包
     * @param array          $vars     参数
     * @return mixed
     */
    public function invokeFunction($function, $vars = null)
    {
        try {
            $reflect = new ReflectionFunction($function);
        } catch (ReflectionException $e) {
            throw new Exception("function not exists: {$function}()");
        }

        return $function($vars);
    }
}
