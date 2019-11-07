<?php
/**
 * curl请求封装-pool
 *
 * @package    Comm
 * @copyright  copyright(2016) 51talk.com all rights reserved
 * @author     talk.com php team
 * @version    2015-09-28 23:58:40
 */

namespace PCurl\Comm;

class HttpRequestPool
{
    public static $mh = null;
    public static $requestPool = array();
    public static $selectTimeout = 0.01;

    public static $curlState;
    public static $curlPool;

    public static function getCurl($hostId, $needNew = false)
    {
        if ($needNew) {
            $ch = self::getCurlCreate($hostId);
        } elseif (isset(self::$curlState[$hostId])) {
            $ch = self::getCurlFromPool($hostId);
            if ($ch === false) {
                $ch = self::getCurlCreate($hostId);
            }
        } else {
            $ch = self::getCurlCreate($hostId);
        }

        return $ch;
    }

    public static function attach(\PCurl\Comm\HttpRequest $httpRequest)
    {
        self::$requestPool[] = $httpRequest;
    }

    public static function send($forceAll = false)
    {
        if (empty(self::$requestPool)) {
            throw new \PCurl\Comm\Exception\Program('request pool is empty');
        }

        if (count(self::$requestPool) == 1) {
            self::$requestPool[0]->send();
            self::$requestPool = array();

            return;
        }

        if (self::$mh == null) {
            self::$mh = curl_multi_init();
        }

        // 由于在attach时尚未分配curl，故没有curlId :(
        $curlRequestMap = array();
        foreach (self::$requestPool as $request) {
            /**
             * @var $request \PCurl\Comm\HttpRequest
             */
            $request->curlInit();
            $curlRequestMap[$request->getCurlID()] = $request;
            curl_multi_add_handle(self::$mh, $request->getCh());
        }

        $running = null;
        do {
            $mhRtn = curl_multi_exec(self::$mh, $running);
            if ($mhRtn == CURLM_CALL_MULTI_PERFORM) {
                continue;
            }
            curl_multi_select(self::$mh, self::$selectTimeout);

            // 多个请求中单个请求的状态只有在请求期间才能获取，事后只能获取true or false，没有msg
            while ($rst = curl_multi_info_read(self::$mh, $queuePoint)) {
                $curlId = \PCurl\Comm\HttpRequest::fetchCurlID($rst['handle']);
                /**
                 * @var $request \PCurl\Comm\HttpRequest
                 */
                $request = $curlRequestMap[$curlId];
                $content = curl_multi_getcontent($request->getCh());
                $info = curl_getinfo($request->getCh());

                if ($rst['result'] == CURLE_OK) {
                    $request->setResponseState(true, "");
                    $request->setResponse($content, $info);
                } else {
                    $errorMsg = curl_error($rst['handle']);
                    $request->setResponseState(false, $errorMsg);
                    $request->setResponse($content, $info, false);

                    if (!$forceAll) {
                        self::cleanUp();
                        throw new \PCurl\Comm\Exception\Api("Request " . $request->url . ": " . $errorMsg);
                    }
                }
            }
        } while ($running);

        self::cleanUp();
    }

    public static function cleanUp()
    {
        self::resetCurlStateAll();
        foreach (self::$requestPool as $request) {
            $request->resetCh();
            if (is_resource($request->getCh())) {
                curl_multi_remove_handle(self::$mh, $request->getCh());
            }
        }
        self::$requestPool = array();
    }

    public static function resetCurlState($hostId, $curlId)
    {
        if (isset(self::$curlState[$hostId][$curlId])) {
            self::$curlState[$hostId][$curlId] = true;
        }
    }

    public static function resetCurlStateAll()
    {
        foreach (self::$curlState as $hostId => $states) {
            foreach ($states as $curlId => $state) {
                self::$curlState[$hostId][$curlId] = true;
            }
        }
    }

    public static function getAvailCurlCount($array = "")
    {
        if (empty($array)) {
            $array = self::$curlState;
        }

        $i = 0;
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $i += self::getAvailCurlCount($value);
            } else {
                if ($value) {
                    $i++;
                }
            }
        }

        return $i;
    }

    public static function getAllCurlCount()
    {
        $count = 0;
        foreach (self::$curlState as $host => $states) {
            $count += count($states);
        }

        return $count;
    }

    private static function getCurlCreate($hostId)
    {
        $ch = curl_init();
        $curlId = \PCurl\Comm\HttpRequest::fetchCurlID($ch);
        self::$curlState[$hostId][$curlId] = false;
        self::$curlPool[$curlId] = $ch;

        return $ch;
    }

    private static function getCurlFromPool($hostId)
    {
        foreach (self::$curlState[$hostId] as $curlId => $state) {
            if ($state) {
                self::$curlState[$hostId][$curlId] = false;

                return self::$curlPool[$curlId];
            }
        }

        return false;
    }
}
