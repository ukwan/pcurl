<?php
/**
 * user_order封装
 */

namespace Pcurl;

class Pcurl extends \Comm\Request\Http
{
    public function __construct($host)
    {
        parent::$host = $host;
    }

    /**
     * 创建请求
     *
     * @param string $request_method
     * @param string $http_content
     * @return Comm\Request\Platform
     */
    protected function createRequest($request_method = 'GET', $http_content = 'json')
    {
        return parent::createRequest($request_method, $http_content);
    }

    /**
     * 提交请求并获取返回值
     * @param string $url 请求url
     * @param array $param 请求参数
     * @return array|\Pcurl\Comm\Request\Platform
     */
    protected function commitRequest($url = '', $param = [])
    {
        return parent::commitRequest();
    }

    /**
     * 分配参数逻辑 [添加时验证所有必填参数,修改时只验证有值的参数]
     * @param array $param 传递参数
     * @return \Pcurl\Comm\Request\Platform
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

    /**
     * 发送添加数据请求
     * @param array $rules 参数规则
     * @param array $param 参数列表
     * @return array
     */
    public function post($rules, $param)
    {
        $this->createRequest('POST');
        $this->setRequestRules($rules, $param);
        return $this->commitRequest();
    }

    /**
     * 发送GET请求
     *
     * @param array $rules 参数规则
     * @param array $param 参数列表
     * @return array
     */
    public function get($rules, $param)
    {
        $this->createRequest();
        $this->setRequestRules($rules, $param);
        return $this->commitRequest();
    }
}