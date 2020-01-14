<?php

namespace Mix\JsonRpc\Helper;

use Mix\JsonRpc\Constants;
use Mix\JsonRpc\Exception\ParseException;
use Mix\JsonRpc\Message\Request;
use Mix\JsonRpc\Message\Response;
use Swoole\Coroutine\Channel;

/**
 * Class JsonRpcHelper
 * @package Mix\JsonRpc\Helper
 */
class JsonRpcHelper
{

    /**
     * 解析请求
     * @param string $payload
     * @return array [bool $single, Request[] $requests]
     * @throws ParseException
     */
    public static function parseRequests(string $payload)
    {
        $payload = static::decode($payload);
        if (!$payload) {
            throw new ParseException('Parse requests failed.');
        }
        $requests = [];
        $single   = false;
        if (count($payload) == count($payload, true)) {
            $single  = true;
            $payload = [$payload];
        }
        foreach ($payload as $value) {
            $request          = new Request();
            $request->jsonrpc = $value['jsonrpc'] ?? null;
            $request->id      = $value['id'] ?? null;
            $request->method  = $value['method'] ?? null;
            $request->params  = $value['params'] ?? null;
            $requests[]       = $request;
        }
        return [$single, $requests];
    }

    /**
     * 验证请求
     * @param Request $request
     * @return bool
     */
    public static function validRequest(Request $request)
    {
        if (!is_string($request->method)) {
            return false;
        }
        if (!(is_scalar($request->id) || is_null($request->id))) {
            return false;
        }
        if (!is_array($request->params)) {
            return false;
        }
        return true;
    }

    /**
     * 解析请求
     * @param string $payload
     * @return Response[]
     * @throws ParseException
     */
    public static function parseResponses(string $payload)
    {
        $payload = static::decode($payload);
        if (!$payload) {
            throw new ParseException('Parse responses failed.');
        }
        $responses = [];
        if (count($payload) == count($payload, true)) {
            $payload = [$payload];
        }
        foreach ($payload as $value) {
            $response          = new Response();
            $response->jsonrpc = $value['jsonrpc'] ?? null;
            $response->id      = $value['id'] ?? null;
            $response->method  = $value['method'] ?? null;
            $response->params  = $value['params'] ?? null;
            $response->error   = $value['error'] ?? null;
            $response->result  = $value['result'] ?? null;
            $responses[]       = $response;
        }
        return $responses;
    }

    /**
     * Send
     * @param Channel $sendChan
     * @param bool $single
     * @param Response ...$responses
     */
    public static function send(Channel $sendChan, bool $single, Response ...$responses)
    {
        $json = [];
        foreach ($responses as $response) {
            $array  = (array)$response;
            $json[] = array_filter($array);
        }
        if ($single) {
            $jsonStr = static::encode(array_pop($json)) . Constants::EOF;
        } else {
            $jsonStr = static::encode($json) . Constants::EOF;
        }
        $sendChan->push($jsonStr);
    }

    /**
     * Encode
     * @param $value
     * @return false|string
     */
    public static function encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode
     * @param $value
     * @return false|array
     */
    public static function decode($value)
    {
        return json_decode($value, true);
    }

}
