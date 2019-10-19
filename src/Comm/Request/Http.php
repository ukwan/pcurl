<?php

namespace Comm\Request;

/**
 * 接口处理抽象类,预留
 *
 * @package    Base
 * @version    2016年06月06日15:19:19
 */
class Http extends Object
{

    /**
     * @var \Pcurl\Comm\Request\Platform
     */
    protected $obj_request;
    protected static $host;

    /**
     * 创建一个url连接
     *
     * @param string $is_get 是否为get传输方式
     * @param string $http_content 传输格式 例如：json
     * @return \Pcurl\Comm\Request\Platform
     */
    protected function createRequest($is_get, $http_content = '')
    {
        //创建一个连接对象
        $request_mode = ($is_get == 'GET') ? "GET" : "POST";
        $this->obj_request = new \Pcurl\Comm\Request\Platform(self::$host, $request_mode, $http_content);

        //设置默认超时时间
        $this->obj_request->setRequestTimeout(2000, 2000);

        return $this->obj_request;
    }

    /**
     * 提交一个url请求
     *
     * @return array|bool|object
     * @throws \Comm\Exception\Api
     * @throws \Comm\Exception\Program
     */
    protected function commitRequest()
    {
        try {
            $res = $this->obj_request->getResponse();
        } catch (\Exception $e) {
            $res = false;
        }
        $this->reset();
        return $res;
    }

    /**
     * 销毁变量
     */
    private function reset()
    {
        $this->obj_request = ''; //接口对象
    }

}
