<?php
/**
 * 接口请求类
 */

namespace PCurl\Comm\Request;

class Platform extends Base
{
    /**
     * @var string 接口返回值格式
     */
    protected $returnFormat = "json";

    const REQUEST_IP_KEY = 'API-RemoteIP';

    /**
     * Platform constructor.
     *
     * @param string $url 请求地址
     * @param string $method
     * @param string $content_type 请求头（headers）中的 Content-Type,请求中的消息主体是用何种方式编码
     */
    public function __construct($url, $method = '', $content_type = '')
    {
        parent::__construct($url, $method, $content_type);
    }

    /**
     * 获取接口请求结果
     *
     * @return mixed
     * @throws \PCurl\Comm\Exception\Api
     * @throws \PCurl\Comm\Exception\Program
     */
    public function getResponse()
    {
        try {
            $this->httpRequest->addHeader(self::REQUEST_IP_KEY, \PCurl\Tool\Ip::getClientIP());
            parent::send();//发送curl请求
            $content = $this->httpRequest->getResponseContent();
            $result = $this->returnFormat == 'json' ? \PCurl\Tool\Util::jsonDecode($content, true) : $content;
            $expMsg = $expCode = false;
            if ($this->httpRequest->getResponseInfo('http_code') != '200') {
                if (isset($result['error'])) {
                    $expMsg = $result['error'];
                    $expCode = $result['code'];
                } else {
                    $expMsg = "http error:" . $this->httpRequest->getResponseInfo('http_code');
                    $expCode = $this->httpRequest->getResponseInfo('http_code');
                }
            }
            if ($expMsg != false && $expCode != false) {
                throw new \PCurl\Comm\Exception\Api($expMsg, $expCode);
            }
            return $result;
        } catch (\Exception $e) {
            throw new \PCurl\Comm\Exception\Api($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 添加请求header头
     *
     * @param string $primary
     * @param string $secondary
     * @param bool $urlencode
     */
    public function addHeader($primary, $secondary, $urlencode = false)
    {
        $this->httpRequest->addHeader($primary, $secondary, $urlencode);
    }
}
