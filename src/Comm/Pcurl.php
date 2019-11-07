<?php

namespace PCurl\Comm;

class PCurl extends \PCurl\Comm\Request\Http
{
    /**
     * PCurl constructor.
     * @param string $host 接口地址
     */
    public function __construct($host)
    {
        parent::$host = $host;
    }

    /**
     * 提交请求并获取返回值
     *
     * @param array $rules 参数规则
     * @param array $param 参数
     * @param string $method curl方法post，get
     * @return array|bool|object
     * @throws \PCurl\Comm\Exception\Api
     * @throws \PCurl\Comm\Exception\Program
     */
    public function sendRequest($rules, $param, $method = "GET")
    {
        $method = ($method && in_array($method, ["GET", "POST"])) ? $method : "GET";
        $this->createRequest($method);
        $this->setRequestRules($rules, $param);
        return parent::commitRequest();
    }

    /**
     * 创建请求对象
     *
     * @param string $request_method
     * @param string $http_content
     * @return \PCurl\Comm\Request\Platform
     */
    protected function createRequest($request_method = 'GET', $http_content = 'json')
    {
        return parent::createRequest($request_method, $http_content);
    }

    /**
     * 分配参数逻辑
     *
     * @param array $rules 参数规则
     * @param array $param 参数
     * @return \PCurl\Comm\Request\Platform
     */
    protected function setRequestRules($rules, $param)
    {
        foreach ($rules as $val) {
            list($field, $type, $require) = $val;
            //添加参数规则 [参数名 参数值类型,是否必须]
            $this->obj_request->addRule($field, $type, $require);
            $this->obj_request->$field = isset($param[$field]) ? $param[$field] : null;
        }
        return $this->obj_request;
    }
}