<?php

namespace App\Parser;

use App\Exception\InvalidRequest;
use App\Exception\JsonParseError;
use App\Exception\MethodNotFound;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Socket\Bean\Response;
use EasySwoole\Socket\Bean\Caller;

/**
 * Class JsonRpcParser
 *
 * @package App\Parser
 */
class BeautyApiParser implements ParserInterface
{
    /**
     * @param $raw
     * @param $client
     *
     * @return Caller|null
     * @throws InvalidRequest
     * @throws JsonParseError
     * @throws MethodNotFound
     */
    public function decode($raw, $client): ?Caller
    {
        $fd = $client->getFd();

        $offset = 0;
        $needle = 'req=';
        $offset = strpos($raw, $needle, $offset);
        if ($offset === 0) {
            $req = substr($raw, strlen($needle));
            $data = json_decode(base64_decode(urldecode($req)), true);
            Logger::getInstance()->info('客户端 FD ' . $fd . ' 发送 HTTP Body');
        } elseif ($offset > 0) {
            $req = substr($raw, $offset + strlen($needle));
            $data = json_decode(base64_decode(urldecode($req)), true);
        } else {
            $data = json_decode($raw, true);
            if ($data === null) {
                // 正则匹配抽取 JSON 字符串
                $isMatched = preg_match('/{(?:[^{}]|(?R))*}/', $raw, $matches);
                if ($isMatched) {
                    $data = json_decode($matches[0], true);
                } else {
                    // 判断是否沿用美人信息 API 原有规范
                    $offset = strpos($raw, $needle, $offset);

                    // 找不到 HTTP Body 数据
                    if ($offset <= 0 || !$offset) {
                        // 查看是否为 HTTP 协议，兼容 HTTP 协议头部
                        $httpHeader = 'POST / HTTP/1.1';
                        $offset = strpos($raw, $httpHeader, 0);
                        if (empty($offset) && $offset !== 0) {
                            throw new JsonParseError('语法解析错误，无效的 JSON 字符串。', -32700);
                        } else {
                            $data = [
                                'jsonrpc' => '2.0',
                                'method' => 'ChatRadio.httpConnect',
                                'params' => [
                                    'message' => '客户端 FD ' . $fd . ' 发送 HTTP Header ',
                                ],
                                'id' => $fd,
                            ];
                        }
                    } else {
                        $data = substr($raw, $offset + strlen($needle));
                        $data = json_decode(base64_decode($data), true);
                    }
                }
            }
        }

        if (!is_array($data)) {
            throw new JsonParseError('语法解析错误，无效的 JSON 字符串。', -32700);
        }

        if (!isset($data['jsonrpc']) || empty($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new InvalidRequest('无效请求，缺少 jsonrpc 字段。', -32600);
        }

        if (!isset($data['method']) || empty($data['method'])) {
            throw new InvalidRequest('无效请求，缺少 method 字段。', -32600);
        }

        if (!isset($data['params'])) {
            throw new InvalidRequest('无效请求，缺少 params 字段。', -32600);
        }

        if (!isset($data['id']) || empty($data['id'])) {
            throw new InvalidRequest('无效请求，缺少 id 字段', -32600);
        }

        $method = explode('.', $data['method']);
        if (count($method) !== 2) {
            throw new MethodNotFound('无效的方法参数，找不到 method 字段指定方法', -32601);
        }

        $name = array_shift($method);
        $controller = "App\\TcpController\\" . $name;
        if (!class_exists($controller)) {
            throw new MethodNotFound('无效的方法参数，找不到 method 字段指定控制器', -32601);
        }

        $action = array_pop($method);
        if (!method_exists($controller, $action)) {
            throw new MethodNotFound('无效的方法参数，找不到 method 字段指定操作', -32601);
        }

        $mapping = $this->getMethodActionMapping();
        if (!isset($mapping[$name . '.' . $action])) {
            throw new MethodNotFound('无效的 method 参数，指定方法尚未授权', -32601);
        }

        $bean = new Caller();
        $bean->setControllerClass($controller);
        $bean->setAction($action);
        $bean->setArgs($data['params']);

        return $bean;
    }

    /**
     * @param  Response  $response
     * @param $client
     *
     * @return string|null
     */
    public function encode(Response $response, $client): ?string
    {
        return $response->getMessage();
    }

    /**
     * @return array
     */
    public function getMethodActionMapping()
    {
        return [
            'ChatRadio.join' => [
                'user_id',
                'user_gender',
                'match_times',
                'match_score',
                'match_gender',
                'user_avatar',
                'user_nickname',
            ],
            'ChatRadio.connect' => [
                'message',
            ],
            'ChatRadio.close' => [
                'message',
            ],
            'ChatRadio.httpConnect' => [
                'message',
            ],
        ];
    }
}