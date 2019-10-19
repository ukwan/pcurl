<?php

namespace Pcurl\Comm\Request;

/**
 * openapi接口请求类
 *
 * @package Comm\Api\Request
 */
class Platform extends Base
{
    public static $curl_debug_log;

    /*
     * @var string 接口返回值格式
     */
    protected $returnFormat = "json";

    const REQUEST_IP_KEY = 'API-RemoteIP';

    /**
     * Platform constructor.
     *
     * @param string $url          请求地址
     * @param string $method
     * @param string $content_type 请求头（headers）中的 Content-Type,请求中的消息主体是用何种方式编码
     */
    public function __construct($url, $method = '', $content_type = '')
    {
        parent::__construct($url, $method, $content_type);
        $ip = \Tool\Ip::getClientIP();
        $this->httpRequest->addHeader(self::REQUEST_IP_KEY, $ip);
    }

    /**
     * @param string $returnFormat
     */
    public function setReturnFormat($returnFormat = 'json')
    {
        $this->returnFormat = $returnFormat;
    }

    /**
     * 接口请求方法
     *
     * @param bool|true $throwExp
     * @param array     $defaut
     * @return array|object  接口无异常时的正常返回值
     * @throws \Comm\Exception\Api
     * @throws \Comm\Exception\Program
     * @see        \Comm\Api\Request\Base::getResponse()
     */
    public function getResponse($throwExp = true, $defaut = array())
    {
        $digpoint = RagnarSDK::digLogStart('curl', __FILE__, __LINE__);
        if ($digpoint) {
            $headers = RagnarSDK::getCurlChildCallParam($digpoint);
            foreach ($headers AS $hk => $header) {
                $this->httpRequest->addHeader($hk, $header);
            }
        }

        $circuit_config = \Tool\Conf::get('circuit');
        if (!$this->checkCircuitIsOpen($circuit_config)) {
            parent::send();
            $content = $this->httpRequest->getResponseContent();

            $result = $this->returnFormat == 'json' ? \Comm\Util::jsonDecode($content, true) : $content;
        } else {
            // 实例化熔断器
            $cb = \Comm\Api\Circuit\CircuitBreaker::instance($circuit_config);

            $circuit_key = $this->httpRequest->getUrl();
            if ($cb->isAvailable($circuit_key)) {//不熔断
                try {
                    parent::send();
                    $content = $this->httpRequest->getResponseContent();

                    $result = $this->returnFormat == 'json' ? \Comm\Util::jsonDecode($content, true) : $content;

                    if ($this->httpRequest->getResponseInfo('http_code') == '200') {
                        $cb->reportSuccess($circuit_key);
                    }
                } catch (\Comm\Exception\Timeout $e) {
                    $result = false;
                    $this->httpRequest->setResponseState(false, 'Request Timeout', '28');
                    $this->httpRequest->setHttpCode(408);
                    $cb->reportFailure($circuit_key);
                    \Comm\Api\Request\Log::writeError($this->httpRequest, \Comm\Api\Request\Log::ERROR);
                } catch (\Exception $e) {
                    $result = false;
                }
            } else {//命中熔断
                $result = false;
                $this->httpRequest->setResponseState(false, 'Request Timeout', '28');
                $this->httpRequest->setHttpCode(408);
                \Comm\Api\Request\Log::writeError($this->httpRequest, \Comm\Api\Request\Log::ERROR);
            }
        }

        $expMsg = $expCode = false;
        if ($this->httpRequest->getResponseInfo('http_code') != '200') {
            if (isset($result['error'])) {
                $expMsg   = $result['error'];
                $expCode  = $result['code'];
                $logsType = \Comm\Api\Request\Log::ERROR_API;
                $logsExt  = $result;
            } else {
                $expMsg   = "http error:" . $this->httpRequest->getResponseInfo('http_code');
                $expCode  = $this->httpRequest->getResponseInfo('http_code');
                $logsType = \Comm\Api\Request\Log::SYSERR;
                $logsExt  = array('errMsg' => $expMsg);
            }
            if ($this->httpRequest->getResponseInfo('http_code') != '408') {
                \Comm\Alarm\Client::report('api.request', 'api请求报错,host=' . $this->httpRequest->getHostID(), sprintf('url:%s,error:%s', $this->httpRequest->getUrl(), $expMsg));
            }
            \Comm\Context::setError('comm.api.http_error', $expMsg);
        } elseif ($this->platform_api_default_format == 'json') {
            if (!is_array($result)) {
                $expMsg   = "api return data can not be json_decode";
                $expCode  = -1;
                $logsType = \Comm\Api\Request\Log::INFO;
                $logsExt  = array('errMsg' => $expMsg);
                \Comm\Context::setError('comm.api.data_error', $expMsg);
            } elseif ((isset($result['code']) && $result['code'] != '10000' && $result['code'] != 1) || isset($result['error'])) {
                $expCode  = isset($result['code']) ? $result['code'] : -1;
                $expMsg   = isset($result['error']) ? $result['error'] : "api data is invalid";
                $logsType = \Comm\Api\Request\Log::ERROR_API;
                $logsExt  = $result;
                \Comm\Context::setError('comm.api.return_error', $expMsg);
            }
        }
        $digpoint && RagnarSDK::digCurlEnd($this->httpRequest->getUrl(), $this->httpRequest->getMethod(), $this->httpRequest->getPostParams(), $this->httpRequest->getQueryParams(), ['http_code' => $this->httpRequest->getResponseInfo('http_code')], $this->httpRequest->getErrorNo(), $this->httpRequest->getErrorMsg(), $result);

        if (false !== $expCode && false !== $expMsg) {
            \Comm\Api\Request\Log::writeError($this->httpRequest, $logsType, $logsExt);
            if ($throwExp == true) {
                //throw new \Comm\Exception\Api($expMsg, $expCode);
            } else {
                return $defaut;
            }
        }

        /*if (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], array('192.168.1.82'))) {
            \Comm\Api\Request\Log::writeError($this->httpRequest, \Comm\Api\Request\Log::INFO, array());
        }*/

        return $result;
    }

    /**
     * 判断电路是否开启
     * @param $circuit_config
     * @return true:走电路判断，false：走正常流程
     */
    private function checkCircuitIsOpen($circuit_config){
        if($circuit_config['enabled']){
            // 允许进行熔断的hostname;
            $host_name = $this->httpRequest->getHostName();
            // app_name 判断
            if(in_array(APP_NAME, $circuit_config['allow_circuit']) && in_array($host_name, $circuit_config['allow_host'])){
                return true;
            }
        }

        return false;
    }

    public static function writeDebug($msg)
    {
        if (empty(self::$curl_debug_log)) {
            return;
        }
        foreach (self::$curl_debug_log as $httpRequestData) {
            \Comm\Api\Request\Log::writeDebug($httpRequestData, \Comm\Api\Request\Log::DEBUG, $msg);
        }

        return;
    }

    /**
     * 添加翻页参数的统一方法
     *
     * @param string $pageName
     * @param string $offsetName
     */
    public function supportPagination($pageName = "page", $offsetName = "count")
    {
        parent::addRule($pageName, "int", false);
        parent::addRule($offsetName, "int", false);
    }

    /**
     * 添加baseApp参数的统一方法
     */
    public function supportBaseApp()
    {
        parent::addRule("baseApp", "string", false);
    }

    /**
     * 添加支持gzip的统一方法
     */
    public function supportGzip()
    {
        $this->httpRequest->gzip = true;
    }

    /**
     * 添加游标参数的统一方法
     */
    public function supportCursor($sinceIdName = "sinceId", $maxIdName = "maxId")
    {
        parent::addRule($sinceIdName, "int64");
        parent::addRule($maxIdName, "int64");
    }

    /**
     * 返回结果是否转义
     */
    public function supportEncode()
    {
        parent::addRule('isEncoded', 'int', false);
    }

    /**
     * 生成接口url
     *
     * @param string $resource
     * @param string $interface
     * @param string $preQuery
     * @return string
     */
    public static function assembleUrl($resource, $interface, $preQuery = '')
    {
        $url = $resource;
        if (!empty($interface)) {
            $url .= '/' . $interface;
        }
        if (!empty($preQuery)) {
            $url .= "?" . $preQuery;
        }

        return $url;
    }

    /**
     * 用户名、密码方式访问时，设定相关信息
     *
     * @param string $username
     * @param string $password
     */
    public function addUserPsw($username, $password)
    {
        $this->httpRequest->addUserPsw($username, $password);
    }

    /**
     * 添加请求header头
     *
     * @param string $primary
     * @param string $secondary
     * @param bool   $urlencode
     */
    public function addHeader($primary, $secondary, $urlencode = false)
    {
        $this->httpRequest->addHeader($primary, $secondary, $urlencode);
    }


    /**
     * 设置url加密
     *
     * @param $urlencode
     */
    public function setUrlEnCode($urlencode)
    {
        $this->httpRequest->setUrlencode($urlencode);
    }

}
