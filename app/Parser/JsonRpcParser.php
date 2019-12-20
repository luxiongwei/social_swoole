<?php

namespace App\Parser;

use App\Exception\InvalidRequest;
use App\Exception\JsonParseError;
use App\Exception\MethodNotFound;
use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Socket\Bean\Response;
use EasySwoole\Socket\Bean\Caller;

/**
 * Class JsonRpcParser
 *
 * @package App\Parser
 */
class JsonRpcParser implements ParserInterface
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
        // 解析客户端原始消息
        $data = json_decode($raw, true);
        if ($data === null) {
            // 正则匹配抽取 JSON 字符串
            $isMatched = preg_match('/\{(?:[^{}]|(?R))*\}/', $raw, $matches);
            if (!$isMatched) {
                throw new JsonParseError('语法解析错误，无效的 JSON 字符串。', -32700);
            }
            $data = json_decode($matches[0], true);
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

        if (array_diff(array_keys($data['params']), $mapping[$name . '.' . $action])) {
            throw new JsonParseError('无效的 params 参数，出现未知的字段', -32602);
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
            ],
            'ChatRadio.connect' => [
                'message',
            ],
            'ChatRadio.close' => [
                'message',
            ],
        ];
    }
}
