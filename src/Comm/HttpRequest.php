<?php

namespace PCurl\Comm;

/**
 * curl请求封装
 *
 * @package    Comm
 * @version    2015-09-28 23:58:40
 */
class HttpRequest
{
    const CRLF = "\r\n";
    public $cookies = array();
    public $headers = array();
    public $postFields = array();
    public $queryFields = array();
    public $hasUpload = false;
    public $fileFields = array();

    public $url;
    public $method = false;
    public $hostName;
    public $hostPort = "80";
    public $isSsl = false;
    public $actualHostIp;
    public $noBody = false;
    public $reqRange = array();
    public $queryString = '';

    public $responseState;
    public $curlInfo;
    public $errorMsg;
    public $errorNo;
    public $responseHeader;
    public $responseContent;

    public $debug = false;
    public $urlencode = "urlencodeRfc3986";

    public $connectTimeout = 1000;
    public $timeout = 1000;

    private $ch = null;
    private $curlId = false;
    private $newCurlPool = false;

    private $callbackMethod;
    private $callbackObj;

    public $curlCli;

    public $gzip = false;

    public $user = null;
    public $psw = null;

    /**
     * 请求头（headers）中的 Content-Type,请求中的消息主体是用何种方式编码
     *
     * @var string
     */
    protected $content_type = '';

    public function __construct($url = "")
    {
        if (!empty($url)) {
            $this->setUrl($url);
        }
    }

    public function setUrl($url)
    {
        if (!empty($this->url)) {
            throw new \PCurl\Comm\Exception\Program("url be setted");
        }

        $urlElement = parse_url($url);

        if ($urlElement["scheme"] == "https") {
            $this->isSsl = true;
            $this->hostPort = '443';
        } elseif ($urlElement["scheme"] != "http") {
            throw new \PCurl\Comm\Exception\Program("api url not support. " . $url);
        }

        $this->hostName = $urlElement['host'];

        $this->url = $urlElement['scheme'] . '://' . $this->hostName;
        if (isset($urlElement['port'])) {
            $this->hostPort = $urlElement['port'];
            $this->url .= ':' . $urlElement['port'];
        }
        if (isset($urlElement['path'])) {
            $this->url .= $urlElement['path'];
        }

        if (!empty($urlElement['query'])) {
            parse_str($urlElement['query'], $queryFields);
            $keys = array_map(array($this, "runUrlencode"), array_keys($queryFields));
            $values = array_map(array($this, "runUrlencode"), array_values($queryFields));
            $this->queryFields = array_merge($this->queryFields, array_combine($keys, $values));
        }
    }

    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    public function setActualHost($ip)
    {
        $this->actualHostIp = $ip;
    }

    public function setConnectTimeout($timeout)
    {
        $this->connectTimeout = (int)$timeout;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = (int)$timeout;
    }

    public function setRequestRange($start, $end)
    {
        $this->reqRange = array($start, $end);
    }

    public function setDebug($debug, $logFormatter = null)
    {
        $this->debug = $debug;
        if ($logFormatter != null) {
            if (is_a($logFormatter, '\Comm\Log\Formatter')) {
                $this->logFormatter = $logFormatter;
            } else {
                throw new \PCurl\Comm\Exception\Program('logFomatter must be \Comm\Log\Formatter');
            }
        }
    }

    public function setUrlencode($urlencode)
    {
        $this->urlencode = $urlencode;
    }

    public function setCallback($method, $obj)
    {
        $this->callbackMethod = $method;
        $this->callbackObj = $obj;
    }

    public function setNeedNewCurl($flag = false)
    {
        if ($flag) {
            $this->newCurlPool = true;
        }
    }

    public function setContentType($content_type)
    {
        $this->content_type = strtolower($content_type);
        if (strpos($this->content_type, 'json') !== false) {
            $this->headers['Accept'] = 'application/json';
            $this->headers['Content-type'] = 'application/json';
        }
    }

    public function addHeader($primary, $secondary, $urlencode = false)
    {
        $primary = $this->runUrlencode($primary, $urlencode);
        $secondary = $this->runUrlencode($secondary, $urlencode);
        $this->headers[$primary] = $secondary;
    }

    public function addUserPsw($user, $psw)
    {
        $this->user = $user;
        $this->psw = $psw;
    }

    public function addCookie($name, $value, $urlencode = false)
    {
        $name = $this->runUrlencode($name, $urlencode);
        $value = $this->runUrlencode($value, $urlencode);
        $this->cookies[$name] = $value;
    }

    public function addQueryField($name, $value, $urlencode = false)
    {
        $name = $this->runUrlencode($name, $urlencode);
        $value = $this->runUrlencode($value, $urlencode);
        $this->queryFields[$name] = $value;
    }

    public function getQueryParams()
    {
        return $this->queryFields;
    }

    public function addPostField($name, $value, $urlencode = false)
    {
        $name = $this->runUrlencode($name, $urlencode);
        $value = $this->runUrlencode($value, $urlencode);
        $this->postFields[$name] = $value;
    }

    public function addPostFile($name, $path)
    {
        $this->hasUpload = true;
        $name = $this->runUrlencode($name);
        if (class_exists('\CURLFile')) {
            $this->fileFields[$name] = new \CURLFile(realpath($path), mime_content_type($path), pathinfo($path, PATHINFO_BASENAME));
        } else {
            $this->fileFields[$name] = '@' . $path;
        }
    }

    public function getPostParams()
    {
        return $this->postFields;
    }

    public function runUrlencode($input, $urlencode = false)
    {
        if ($urlencode) {
            return $this->{$urlencode}($input);
        } elseif ($this->urlencode) {
            return $this->{$this->urlencode}($input);
        } else {
            return $input;
        }
    }

    public function curlInit()
    {
        if ($this->ch !== null) {
            throw new \PCurl\Comm\Exception\Program('curl init already');
        }

        if (empty($this->hostName) || empty($this->url)) {
            throw new \PCurl\Comm\Exception\Program('httprequest need api_url' . ', uniqid:' . \Comm\Context::get('req_uniqid'));
        }

        $ch = \PCurl\Comm\HttpRequestPool::getCurl($this->getHostID(), $this->newCurlPool);
        $this->curlId = self::fetchCurlID($ch);
        $this->ch = $ch;
        $this->curlCli = 'curl -v ';
        $this->curlSetopt();
    }

    public function getCh()
    {
        return $this->ch;
    }

    public function getCurlID()
    {
        return $this->curlId;
    }

    public function send()
    {
        $this->curlInit();
        $content = curl_exec($this->ch);
        if (curl_errno($this->ch) == 0) {
            $rtn = true;
            $this->setResponseState(true, "", curl_errno($this->ch));
        } else {
            $this->setResponseState(false, curl_error($this->ch), curl_errno($this->ch));
            $rtn = false;
        }
        $this->setResponse($content, curl_getinfo($this->ch));
        \PCurl\Comm\HttpRequestPool::resetCurlState($this->getHostID(), $this->getCurlID());
        $this->resetCh();

        return $rtn;
    }

    public function resetCh()
    {
        $this->ch = null;
        $this->curlId = false;
    }

    public function getCurlCli()
    {
        if (!$this->debug) {
            throw new \PCurl\Comm\Exception\Program("cann't get info when debug disable");
        }

        return $this->curlCli;
    }

    public function setResponseState($state, $errorMsg, $errorNo)
    {
        $this->responseState = $state;
        $this->errorMsg = $errorMsg;
        $this->errorNo = $errorNo;
    }

    public function setHttpCode($http_code)
    {
        $this->curlInfo['http_code'] = $http_code;
    }

    public function setResponse($content, $info, $invokeCallback = true)
    {
        $this->curlInfo = $info;

        if (empty($content)) {
            return;
        }
        $sectionSeparator = str_repeat(self::CRLF, 2);
        $sectionSeparatorLength = strlen($sectionSeparator);
        // pick out http 100 status header
        $http_100 = "HTTP/1.1 100 Continue" . $sectionSeparator;
        if (false !== strpos($content, $http_100)) {
            $content = substr($content, strlen($http_100));
        } else {
            //过滤郭峰http 100 status header
            $http_100_header = "HTTP/1.1 100 Continue";
            $http_100_content = "Content-Length: 0" . $sectionSeparator;
            if (false !== strpos($content, $http_100_header)) {
                $content = substr($content, strlen($http_100_header));
            }
            if (false !== strpos($content, $http_100_content)) {
                $content = substr($content, strlen($http_100_content));
            }
        }

        $lastHeaderPos = 0;
        // put header and content into each var, 3xx response will generate many header :(
        for ($i = 0, $pos = 0; $i <= $this->curlInfo['redirect_count']; $i++) {
            if ($i + 1 > $this->curlInfo['redirect_count'] && $pos) {
                $lastHeaderPos = $pos + $sectionSeparatorLength;
            }
            $pos += $i > 0 ? $sectionSeparatorLength : 0;
            $pos = strpos($content, $sectionSeparator, $pos);
        }

        $this->responseContent = substr($content, $pos + $sectionSeparatorLength);
        $headers = substr($content, $lastHeaderPos, $pos - $lastHeaderPos);
        $headers = explode(self::CRLF, $headers);
        foreach ($headers as $header) {
            if (false !== strpos($header, "HTTP/1.1")) {
                continue;
            }

            $tmp = explode(":", $header, 2);
            $responseHeaderKey = strtolower(trim($tmp[0]));
            if (!isset($this->responseHeader[$responseHeaderKey])) {
                $this->responseHeader[$responseHeaderKey] = trim($tmp[1]);
            } else {
                if (!is_array($this->responseHeader[$responseHeaderKey])) {
                    $this->responseHeader[$responseHeaderKey] = (array)$this->responseHeader[$responseHeaderKey];
                }
                $this->responseHeader[$responseHeaderKey][] = trim($tmp[1]);
            }
        }
        // is there callback?
        if ($invokeCallback && !empty($this->callbackObj) && !empty($this->callbackMethod)) {
            call_user_func_array(array($this->callbackObj, $this->callbackMethod), array($this));
        }
    }

    public function getResponseState()
    {
        return $this->responseState;
    }

    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    public function getErrorNo()
    {
        return $this->errorNo;
    }

    public function getResponseTime()
    {
        return $this->getResponseInfo('total_time');
    }

    public function getResponseInfo($key = "")
    {
        if (empty($key)) {
            return $this->curlInfo;
        } else {
            if (isset($this->curlInfo[$key])) {
                return $this->curlInfo[$key];
            } else {
                throw new \PCurl\Comm\Exception\Program("info: " . $key . " not exists");
            }
        }
    }

    public function getResponseHeader($key = "")
    {
        if (empty($key)) {
            return $this->responseHeader;
        } else {
            if (isset($this->responseHeader[$key])) {
                return $this->responseHeader[$key];
            } else {
                throw new \PCurl\Comm\Exception\Program("header: " . $key . " not exists");
            }
        }
    }

    public function getResponseContent()
    {
        return $this->responseContent;
    }

    public function getMethod()
    {
        if (isset($this->method)) {
            return $this->method;
        }

        return null;
    }

    public function getUrl()
    {
        if (isset($this->url)) {
            return $this->url;
        }

        return '';
    }

    public static function urlencode($input)
    {
        if (is_array($input)) {
            return array_map(array('\Comm\HttpRequest', 'urlencode'), $input);
        } elseif (is_scalar($input)) {
            return urlencode($input);
        } else {
            return '';
        }
    }

    public static function urlencodeRaw($input)
    {
        if (is_array($input)) {
            return array_map(array('\Comm\HttpRequest', 'urlencodeRaw'), $input);
        } elseif (is_scalar($input)) {
            return rawurlencode($input);
        } else {
            return '';
        }
    }

    public static function urlencodeRfc3986($input)
    {
        if (is_array($input)) {
            return array_map(array('\Comm\HttpRequest', 'urlencodeRfc3986'), $input);
        } elseif (is_scalar($input)) {
            return str_replace('+', ' ', str_replace('%7E', '~', rawurlencode($input)));
        } else {
            return '';
        }
    }

    public static function fetchCurlID($ch)
    {
        preg_match('/[^\d]*(\d+)[^\d]*/', (string)$ch, $matches);

        return $matches[1];
    }

    /**
     * 拼装http查询串（不经过urlencode）
     */
    public static function httpBuildQuery($queryData = array(), $keyPrefix = '')
    {
        if (empty($queryData) && empty($keyPrefix)) {
            return '';
        }
        $pairs = [];
        foreach ($queryData as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $mid = self::httpBuildQuery($value, $keyPrefix ? ($keyPrefix . '[' . $key . ']') : $key);
                $pairs = array_merge($pairs, $mid);
            } elseif (is_array($value) && empty($value)) {
                $pairs[] = ($keyPrefix ? ($keyPrefix . '[' . $key . ']') : $key) . '=';
            } else {
                $pairs[] = ($keyPrefix ? ($keyPrefix . '[' . $key . ']') : $key) . '=' . $value;
            }
        }
        if ($keyPrefix) {
            return $pairs;
        }

        $queryString = implode("&", $pairs);

        return $queryString;
    }

    public function getHostID()
    {
        return $this->hostName . ':' . $this->hostPort;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    private function curlSetopt()
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->url);
        // -v
        curl_setopt($this->ch, CURLOPT_HEADER, true);

        if ($this->isSsl) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            $this->curlCli .= " -k";
        }

        if ($this->noBody) {
            curl_setopt($this->ch, CURLOPT_NOBODY, true);
        }

        if (!empty($this->reqRange)) {
            curl_setopt($this->ch, CURLOPT_RANGE, $this->reqRange[0] . "-" . $this->reqRange[1]);
        }

        // -v
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        // default
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        // not use
        curl_setopt($this->ch, CURLOPT_USERAGENT, "framework HttpRequest class");

        if ($this->debug) {
            // -v
            curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
        }

        if ($this->gzip) {
            curl_setopt($this->ch, CURLOPT_ENCODING, "gzip");
            $this->curlCli .= " --compressed ";
        }

        $version = curl_version();
        if (version_compare($version["version"], "7.16.2") < 0) {
            //如果timeout为0，则curl将wait indefinitely.故此处将意外设置timeout < 1sec的情况，重新
            //设置为1s
            $timeout = floor($this->connectTimeout / 1000);
            if ($this->connectTimeout > 0 && $timeout <= 0) {
                $timeout = 1;
            }
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
        } else {
            curl_setopt($this->ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
            curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        }
        unset($version);
        $this->curlCli .= " --connect-timeout " . round($this->connectTimeout / 1000, 3);
        $this->curlCli .= " -m " . round($this->timeout / 1000, 3);

        // -x
        if (!empty($this->actualHostIp)) {
            curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($this->ch, CURLOPT_PROXY, $this->actualHostIp);
            curl_setopt($this->ch, CURLOPT_PROXYPORT, $this->hostPort);
            $this->curlCli .= " -x " . $this->actualHostIp . ":" . $this->hostPort;
        }

        $this->loadCookies();
        $this->loadHeaders();
        $this->loadQueryFields();
        $this->loadPostFields();
        $this->loadUserPwd();

        if ($this->method) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, strtoupper($this->method));
            $this->curlCli .= " -X \"{$this->method}\"";
        }
        $this->curlCli .= " \"" . $this->url . ($this->queryString ? '?' . $this->queryString : '') . "\"";
    }

    private function loadUserPwd()
    {
        if (is_null($this->user) || is_null($this->psw)) {
            return;
        }
        $strUserpwd = $this->user . ':' . $this->psw;
        $this->curlCli .= "-u \"$strUserpwd\" ";
        curl_setopt($this->ch, CURLOPT_USERPWD, $strUserpwd);
    }

    private function loadCookies()
    {
        if (empty($this->cookies)) {
            return;
        }

        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        $cookie = implode('; ', $pairs);
        curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
        $this->curlCli .= " -b \"" . $cookie . "\"";
    }

    private function loadHeaders()
    {
        if (empty($this->headers)) {
            return;
        }
        $headers = array();
        foreach ($this->headers as $k => $v) {
            $tmp = $k . ":" . $v;
            $this->curlCli .= " -H \"" . $tmp . "\"";
            $headers[] = $tmp;
        }

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }

    private function loadQueryFields()
    {
        $this->queryString = '';
        if (empty($this->queryFields)) {
            return;
        }

        /*foreach ($this->queryFields as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        if ($pairs) {
            $this->queryString = implode('&', $pairs);
        }*/
        $this->queryString = self::httpBuildQuery($this->queryFields);
        curl_setopt($this->ch, CURLOPT_URL, $this->url . '?' . $this->queryString);
    }

    private function loadPostFields()
    {
        if (empty($this->postFields) && empty($this->fileFields)) {
            return;
        }
        if (strpos($this->content_type, 'json') === false) {
            $data_string = self::httpBuildQuery($this->postFields);
            if ($this->hasUpload) {
                foreach ($this->fileFields as $name => $value) {
                    if (is_object($value) && $value instanceof \CURLFile) {
                        $this->curlCli .= " --form \"" . $name . '=@' . $value->getFilename() . "\"";
                    } else {
                        $this->curlCli .= " --form \"" . $name . '=' . $value . "\"";
                    }
                }
                //$this->postFields = array_merge($this->postFields, $this->fileFields);
            }
        } else {
            $data_string = json_encode($this->postFields);
        }

        if (true == $this->hasUpload) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, array_merge($this->postFields, $this->fileFields));
        } else {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data_string);
        }

        if (!empty($data_string)) {
            $this->curlCli .= " -d '" . $data_string . "'";
        }
    }
}
