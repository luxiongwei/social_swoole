<?php

namespace EasySwoole\EasySwoole;

use App\Parser\BeautyApiParser;
use App\Parser\JsonRpcParser;
use App\Process\MatchProcess;
use App\Task\IncreaseScore;
use EasySwoole\Component\Process\Config as ProcessConfig;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\RedisPool\Redis;
use EasySwoole\Socket\Dispatcher;

/**
 * Class EasySwooleEvent
 *
 * @package EasySwoole\EasySwoole
 */
class EasySwooleEvent implements Event
{
    /**
     * 当前环境变量
     *
     * @var
     */
    public static $env;
    /**
     * 开发模式
     */
    const ENV_DEV = 'dev';

    public static function initialize()
    {
        // 设置时区
        date_default_timezone_set('Asia/Shanghai');

        // 清除所有定时器
        \Swoole\Timer::clearAll();

        // 设置环境变量
        self::$env = Config::getInstance()->getConf('ENV');

        // 注册 Redis 连接池
        $redisPoolConfig = Redis::getInstance()->register('redis', new RedisConfig(Config::getInstance()->getConf('REDIS')));

        // 配置 Redis 连接池数量
        $redisPoolConfig->setMinObjectNum(5);
        $redisPoolConfig->setMaxObjectNum(20);
    }

    public static function mainServerCreate(EventRegister $register)
    {
        $server = ServerManager::getInstance()->getSwooleServer();

        // 监听端口 9502
        self::listenSubHttpServer($server);

        // 监听端口 9503
        self::listenSubTcpServer($server);

        // 定时加分，每秒加 1 分
        Timer::getInstance()->loop(1 * 1000, function () use ($server) {
            TaskManager::getInstance()->async(IncreaseScore::class);
        });

        // 开启匹配进程
        $processConfig = new ProcessConfig();
        $processConfig->setProcessName(MatchProcess::class);
        $server->addProcess((new MatchProcess($processConfig))->getProcess());
    }

    public static function listenSubHttpServer($server)
    {
        $config = new \EasySwoole\Socket\Config();
        $config->setType($config::TCP);
        $config->setParser(new BeautyApiParser());
        $config->setOnExceptionHandler(function ($server, $exception, $raw, $client) {
            Logger::getInstance()->error($exception->getMessage() . '-' . $exception->getFile() . '-' . $exception->getLine() . '-' . $raw);

            $data = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ],
                'id' => $client->getFd(),
            ];

            $header = <<<HEADER
HTTP/1.1 400 Bad Request
Server: Beauty
Content-Type: application/json; charset=UTF-8


HEADER;

            $server->send($client->getFd(), $header . json_encode($data));
            $server->close($client->getFd());
        });
        $dispatch = new Dispatcher($config);

        $config = Config::getInstance()->getConf('HTTP_MATCH_SERVER');
        $subPort = $server->addlistener($config['LISTEN_ADDRESS'], $config['PORT'], $config['SOCK_TYPE']);
        $subPort->set($config['SETTING']);

        // 监听数据接收
        $subPort->on('receive', function ($server, int $fd, int $reactorId, string $data) use ($dispatch) {
            
            $message = '接收到客户端（' . $fd . '）的数据：'.$data;
            $logLevel = Logger::LOG_LEVEL_INFO;
            Logger::getInstance()->console($message, $logLevel);
            
            $dispatch->dispatch($server, $data, $fd, $reactorId);
        });

        // 监听连接请求
        $subPort->on('connect', function ($server, int $fd, int $reactorId) use ($dispatch) {
            $message = '客户端 FD ' . $fd . ' 已连接';
            $logLevel = Logger::LOG_LEVEL_INFO;
            Logger::getInstance()->console($message, $logLevel);
        });

        // 监听连接关闭
        $subPort->on('close', function ($server, int $fd, int $reactorId) use ($dispatch) {
            $message = '客户端 FD ' . $fd . ' 已关闭';
            $logLevel = Logger::LOG_LEVEL_INFO;
            Logger::getInstance()->console($message, $logLevel);
        });
    }

    public static function listenSubTcpServer($server)
    {
        $config = new \EasySwoole\Socket\Config();
        $config->setType($config::TCP);
        $config->setParser(new JsonRpcParser());
        $config->setOnExceptionHandler(function ($server, $exception, $raw, $client) {
            Logger::getInstance()->error($exception->getMessage() . '-' . $exception->getFile() . '-' . $exception->getLine() . '-' . $raw);

            $data = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ],
                'id' => $client->getFd(),
            ];
            $server->send($client->getFd(), json_encode($data));
        });
        $dispatch = new Dispatcher($config);

        $config = Config::getInstance()->getConf('MATCH_SERVER');
        $subPort = $server->addlistener($config['LISTEN_ADDRESS'], $config['PORT'], $config['SOCK_TYPE']);
        $subPort->set($config['SETTING']);

        // 监听数据接收
        $subPort->on('receive', function ($server, int $fd, int $reactorId, string $data) use ($dispatch) {
            $dispatch->dispatch($server, $data, $fd, $reactorId);
        });

        // 监听连接请求
        $subPort->on('connect', function ($server, int $fd, int $reactorId) use ($dispatch) {
            $data = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'ChatRadio.connect',
                'params' => [
                    'message' => '客户端 FD ' . $fd . ' 已连接',
                ],
                'id' => $fd,
            ]);
            $dispatch->dispatch($server, $data, $fd, $reactorId);
        });

        // 监听连接关闭
        $subPort->on('close', function ($server, int $fd, int $reactorId) use ($dispatch) {
            $data = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'ChatRadio.close',
                'params' => [
                    'message' => '客户端 FD ' . $fd . ' 已关闭',
                ],
                'id' => $fd,
            ]);
            $dispatch->dispatch($server, $data, $fd, $reactorId);
        });
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
    }
}