<?php
namespace Pcurl\Comm\Request;

/**
 * HttpRequest Wrapper类
 * 隐藏!!技术!!细节，方便SDK用户调用,按服务提供方的维度进行封装,每个接口至少一个方法
 * 设计目标
 * 1、参数数据类型（强行转换）,参数规则见 applyRules
 * 2、参数必要性（效验）
 * 3、支持CURL并发请求
 * 4、封装某个服务下多个接口共同的业务需求，例如身份效验等需求
 * 5、提供统一的界面供调用者使用（参数设置、获取请求结果、设置callback）
 *
 * @package    Api
 * @version    2015-09-24 10:59:51
 */
abstract class Base
{
    /**
     * @var \Comm\HttpRequest
     */
    protected $httpRequest;
    /**
     * @var string 默认为false，由curl根据参数确定
     */
    protected $method;

    /**
     * $rules[实名] = array(
     * 'dataType' => 'int/int64/string/filepath/float',
     * 'where' => 'PARAM_IN_*',
     * 'isRequired' => 'true/false',
     * 'finalValue' => ''
     * );
     *
     * @var array 存放参数规则
     */
    protected $rules = array();

    /**
     * $ruleMethod[paramName] = array(
     *     method,
     * );
     *
     * @var array 存放参数名对应的请求方式
     */
    protected $ruleMethod = array();

    /**
     * $alias[别名] = 实名
     *
     * @var array 存放参数别名
     */
    protected $alias = array();

    /**
     * @var array 参数的值，key为actualName
     */
    protected $values = array();

    /**
     * @var array 设置各种的回调
     */
    protected $callback = array();

    /*
     * @var string 接口返回值格式
     */
    protected $returnFormat = "json";

    /**
     * 设置接口警报值，超过此值，将写超时日志、默不记录
     *
     * @var int
     */
    protected $warningTimeout = 0;

    /**
     * 超时重试一次
     *
     * @var bool
     */
    protected $retry = 1;

    /**
     * @var int 参数位置由接口的http method决定（在url或http body中）
     */
    const PARAM_IN_BY_METHOD = 0;

    /**
     * @var int 强行将参数放在url中
     */
    const PARAM_IN_GET = 1;

    /**
     * @var int 强行将参数放在http body中
     */
    const PARAM_IN_POST = 2;

    /**
     * 供 ##接口开发者## 设置URL和HTTP REQUEST METHOD
     *
     * @param string $url
     * @param string $method
     * @param string $content_type
     */
    public function __construct($url, $method = '', $content_type = '')
    {
        $this->httpRequest = new \Pcurl\HttpRequest($url);

        $this->method = strtoupper($method);
        $this->httpRequest->setMethod($method);
        $this->httpRequest->setContentType($content_type);
    }

    /**
     * 发送请求 curl错误在这里被处理,正确的返回值由getResponse处理
     *
     * @throws \Comm\Exception\Api
     */
    protected function send()
    {
        $this->applyRules();
        $this->runCallback("beforeSend");
        $sendRst = $this->httpRequest->send();

        //只有测试环境和开发环境才写日志
        if (\Tool\Misc::envCheck() == false) {
            $this->debug();
        }
        $this->runCallback("afterSend");

        //请求时间超过超时阀值，记录日志
        $warningTimeout = max($this->httpRequest->timeout, $this->getWarningTimeout()) / 1000;
        $responseTime   = $this->httpRequest->getResponseTime();
        if ($warningTimeout != 0 && $responseTime >= $warningTimeout && \Tool\Misc::envCheck()) {
            \Comm\Api\Request\Log::writeError($this->httpRequest, \Comm\Api\Request\Log::ERROR_TIMEOUT);
            throw new \Comm\Exception\Timeout($this->getExceptionMsg());
        }

        //无返回,重试一次[超时不重试]
        $iCurlNo = $this->httpRequest->getErrorNo();
        if ($this->retry > 0 && !$sendRst && ($responseTime < $this->httpRequest->timeout / 1000 || $iCurlNo == 28 || $iCurlNo == 7 || $iCurlNo == 6)) {
            //echo sprintf('-----url=%s, retry=%s, responseTime=%s, timeout=%s, connectTimeout=%s <br>', $this->httpRequest->getUrl(),$this->retry, $responseTime, $this->httpRequest->timeout, $this->httpRequest->connectTimeout) . PHP_EOL;
            $this->retry = $this->retry - 1;
            $this->send();
        } elseif (!$sendRst) {
            \Comm\Api\Request\Log::writeError($this->httpRequest, \Comm\Api\Request\Log::ERROR);
            throw new \Comm\Exception\Api($this->getExceptionMsg());
        }
    }

    /**
     * 获取接口需要抛出的异常信息
     */
    protected function getExceptionMsg()
    {
        $responseInfo = $this->httpRequest->getResponseInfo();
        $expMsg       = '';
        $expMsg .= 'uniqid:' . \Comm\Context::get('req_uniqid') . ',';
        $expMsg .= 'url:' . \Comm\Context::getCurrentUrl(false) . ',';
        $expMsg .= 'total_time:' . $responseInfo['total_time'] . ',';
        $expMsg .= 'request:' . $responseInfo['url'] . ',';
        $expMsg .= 'msg:' . $this->httpRequest->getErrorNo() . ':' . $this->httpRequest->getErrorMsg();

        return $expMsg;
    }

    protected function debug()
    {
        //超过0.3秒，写日志
        $timeoutLog = false;
        if ($this->httpRequest->getResponseInfo('total_time') > 0.3) {
            $timeoutLog = true;
        }

        static $requestFlag, $sequenceNo;
        if (empty($requestFlag)) {
            $requestFlag = true;
            $sequenceNo  = 1;
            $msg         = "[" . date('Y-m-d H:i:s') . "] " . \Comm\Context::getServer('REQUEST_URI');
            $msg .= " (at " . \Tool\Ip::getClientIP() . ', use ' . \Comm\ClientProber::getClientAgent('browser') . "), ";
        } else {
            $sequenceNo++;
            $msg = "";
        }
        $msg .= "api: " . $this->httpRequest->url . ', ';
        $msg .= "sn: " . $sequenceNo . ', ';
        $msg .= "php stack: " . $this->getBacktraceInfo() . ', ';
        $msg .= "http code: " . $this->httpRequest->getResponseInfo('http_code') . ', ';
        $msg .= "used time: " . $this->httpRequest->getResponseInfo('total_time') . " s" . ', ';
        $msg .= "request size: " . $this->httpRequest->getResponseInfo('request_size') . " byte" . ', ';
        $download_size = $this->httpRequest->getResponseInfo('download_content_length');
        $download_size = $download_size > 0 ? $download_size : $this->httpRequest->getResponseInfo('size_download');
        $msg .= "response size: " . $download_size . " byte" . ', ';
        $this->httpRequest->setDebug(true);
        $msg .= $this->httpRequest->getCurlCli();

        //请求时间超过0.3秒的接口写入log
        if ($timeoutLog) {
            \Tool\Log::write($msg, 'comm.request.debug.timeout');
        } else {
            \Tool\Log::write($msg, 'comm.request.debug');
        }
    }

    public function getBacktraceInfo()
    {
        $trace = debug_backtrace();
        foreach ($trace as $item) {
            if (isset($item['file'])) {
                if (preg_match('#/libs/(.*)$#Di', $item['file'], $match)) {
                    $info[] = $match[1] . '@' . $item['line'];
                }
            } else {
                $info[] = $item['class'] . '@' . $item['function'];
            }
        }

        if (!empty($info)) {
            $info = array_reverse($info);

            return implode(', ', $info);
        } else {
            return '';
        }
    }

    /**
     * 获取正确的值
     */
    abstract public function getResponse();

    /**
     * 供 ##接口开发者## 设置接口规则
     *
     * @param string $actualName
     * @param string $dataType
     * @param bool   $isRequired
     * @param int    $where
     */
    public function addRule($actualName, $dataType, $isRequired = false, $where = 0)
    {
        $this->rules[$actualName]['dataType']   = $dataType;
        $this->rules[$actualName]['isRequired'] = $isRequired;
        $this->rules[$actualName]['where']      = $where;
    }

    /**
     * 为参数添加特殊的请求
     *
     * @param string $actualName
     * @param string $method
     * @throws \Comm\Exception\Program
     */
    public function addRuleMethod($actualName, $method)
    {
        $allowMethods = array('GET' => 0, 'POST' => 1, 'DELETE' => 2);
        if (!isset($allowMethods[$method])) {
            throw new \Comm\Exception\Program("method for the param {$actualName} error:  $method");
        }
        if ($this->method != 'POST' && $method == 'POST') {
            $this->httpRequest->setMethod('POST');
        }
        $this->ruleMethod[$actualName] = $method;
    }

    /**
     * 供 ##接口开发者## 设置参数别名
     *
     * @param string $actualName
     * @param string $alias
     */
    public function addAlias($actualName, $alias)
    {
        $this->alias[$alias] = $actualName;
    }

    /**
     * 供 ##接口开发者## 增加 ##设置单个参数时## 的callback
     * 回调方法示意：（第一个参数为按引用传递的$value）
     * public function func($value, $p1, $p2..., $pn)
     *
     * @param string   $actualName
     * @param callable $callback
     * @param array    $param
     */
    public function addSetCallback($actualName, callable $callback, array $param = [])
    {
        $this->callback['set'][$actualName][] = $callback;
        $this->callback['set'][$actualName][] = $param;
    }

    /**
     * 供 ##接口开发者## 增加 ##发送请求前## 的callback
     * 回调方法示意：（最后一个参数为当前$request）
     * public function func($p1, $p2..., $pn, $request)
     *
     * @param callable $callback
     * @param array    $param
     */
    public function addBeforeSendCallback(callable $callback, array $param = [])
    {
        \Comm\Assert::asException();
        \Comm\Assert::false(isset($this->callback['beforeSend']), "don not add before send callback repeatly");
        $this->callback['beforeSend'][] = $callback;
        $this->callback['beforeSend'][] = $param;
    }

    /**
     * 供 ##接口开发者## 增加 ##发送请求后## 的callback
     * 回调方法示意：（最后一个参数为当前$request）
     * public function func($p1, $p2..., $pn, $request)
     *
     * @param callable $callback
     * @param array    $param
     */
    public function addAfterSendCallback(callable $callback, array $param = [])
    {
        \Comm\Assert::asException();
        \Comm\Assert::false(isset($this->callback['afterSend']), "don not add after send callback repeatly");
        $this->callback['afterSend'][] = $callback;
        $this->callback['afterSend'][] = $param;
    }

    /**
     * 供 ##接口调用者## 设置参数
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        \Comm\Assert::asException();
        \Comm\Assert::true($actualName = $this->getActualName($name), "{$name} is not allowed");
        $this->values[$actualName] = $this->runCallback('set', $actualName, $value);
    }

    /**
     * 返回values数据
     *
     * @param string $actualName
     * @return mixed
     */
    public function __get($actualName)
    {
        if (isset($this->values[$actualName])) {
            return $this->values[$actualName];
        }

        return null;
    }

    /**
     * 返回values数据
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * 返回http请求对象
     */
    public function getHttpRequest()
    {
        return $this->httpRequest;
    }

    /**
     * 设定请求的超时时间
     *
     * @param int $connectTimeout
     * @param int $time
     */
    public function setRequestTimeout($connectTimeout, $time)
    {
        $this->httpRequest->connectTimeout = $connectTimeout;
        $this->httpRequest->timeout        = $time;
    }

    /**
     * 设定不进行重试机制
     *
     * @param int $retry  是否重试机制 默认不进行重试
     */
    public function setRequestRetry($retry = 0)
    {
        $this->retry = $retry;
    }

    /**
     * 设置超时阀值
     *
     * @param $time
     */
    public function setWarningTimeout($time)
    {
        $this->warningTimeout = $time;
    }

    /**
     * 获取超时阀值
     */
    public function getWarningTimeout()
    {
        return $this->warningTimeout;
    }

    /**
     * 发送正式请求前验证接口规则
     * 规则来自接口开发者的设定
     *
     * @throws \Comm\Exception\Program
     */
    protected function applyRules()
    {
        if (empty($this->rules)) {
            return;
        }
        foreach ($this->rules as $actualName => $rule) {
            if ($rule['isRequired'] && !isset($this->values[$actualName])) {
                throw new \Comm\Exception\Program("param {$actualName} is required");
            } elseif (!isset($this->values[$actualName])) {
                continue;
            }

            $value = $this->values[$actualName];
            switch ($rule['dataType']) {
                case "boolean":
                    $value = ((boolean)$value) ? 'true' : 'false';
                    break;
                case "int":
                    $value = (int)$value;
                    break;
                case "string":
                case "filepath":
                case "date":
                    $value = (string)$value;
                    break;
                case "float":
                    $value = (float)$value;
                    break;
                case "int64":
                    if (!\Comm\Util::is64bit()) {
                        //if (!is_string($value) && !is_float($value)) {/*throw?*/}
                        $value = (string)$value;
                    } else {
                        $value = (int)$value;
                    }
                    break;
                case "array":
                    $value = (array)$value;
                    break;
                default:
                    throw new \Comm\Exception\Program("invalid data type");
            }

            if (isset($this->ruleMethod[$actualName])) {
                $method = $this->ruleMethod[$actualName];
            } else {
                $method = $this->method;
            }
            if (($rule['where'] == self::PARAM_IN_BY_METHOD && $method === "GET") || $method === "DELETE" || $rule['where'] == self::PARAM_IN_GET) {
                $this->httpRequest->addQueryField($actualName, $value);
            } else {
                if ($rule['dataType'] === 'filepath') {
                    $this->httpRequest->addPostFile($actualName, $value);
                } else {
                    $this->httpRequest->addPostField($actualName, $value);
                }
            }
        }
    }

    /**
     * 检查参数是否在允许范围内
     *
     * @param string $name
     * @return string|mixed
     */
    private function getActualName($name)
    {
        if (isset($this->rules[$name])) {
            return $name;
        }

        if (array_key_exists($name, $this->alias)) {
            return $this->alias[$name];
        }

        return false;
    }

    /**
     * 运行回调函数
     *
     * @param string $phase 定义回调的名字
     * @param string $actualName
     * @param mixed  $value
     * @return mixed
     */
    private function runCallback($phase, $actualName = '', $value = '')
    {
        if (!isset($this->callback[$phase])) {
            return $value;
        }

        if ($phase == "set") {
            \Comm\Assert::true($actualName != '');
            if (isset($this->callback['set'][$actualName])) {
                $callback = $this->callback['set'][$actualName][0];
                $param    = $this->callback['set'][$actualName][1];
                $param    = is_array($param) ? $param : array();
                array_unshift($param, $value);
                $value = call_user_func_array($callback, $param);

                return $value;
            } else {
                return $value;
            }
        } else {
            if (isset($this->callback[$phase])) {
                $callback = $this->callback[$phase][0];
                $param    = $this->callback[$phase][1];
                $param[]  = $this;
                call_user_func_array($callback, $param);
            }
        }
    }
}
