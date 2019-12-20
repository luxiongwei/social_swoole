<?php

namespace App\TcpController;

use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\RedisPool\Redis;

/**
 * Class ChatRadio - 聊天陪伴电台
 *
 * @package App\TcpController
 */
class ChatRadio extends Controller
{
    /**
     * user_id 映射 fd
     */
    const HASH_USER_FD = 'chat_radio_user_fd';
    /**
     * fd 映射 user_Id
     */
    const HASH_FD_USER = 'chat_radio_fd_user';
    /**
     * 单个用户信息
     */
    const HASH_USER_INFO = 'chat_radio_match_user_';
    /**
     * 所有用户集合
     */
    const ZSET_USER_ALL = 'chat_radio_match_all';
    /**
     * 所有男性用户集合
     */
    const ZSET_USER_MEN = 'chat_radio_match_men';
    /**
     * 所有女性用户集合
     */
    const ZSET_USER_WOMEN = 'chat_radio_match_women';

    /**
     * 执行连接操作
     */
    public function connect()
    {
        $params = $this->caller()->getArgs();
        Logger::getInstance()->console($params['message'], Logger::LOG_LEVEL_INFO);
    }

    /**
     * 执行关闭连接操作
     */
    public function close()
    {
        $params = $this->caller()->getArgs();
        $client = $this->caller()->getClient();
        go(function () use ($client) {
            $redisPool = Redis::getInstance()->get('redis');
            $redis = $redisPool->getObj();
            $userId = $redis->hGet(ChatRadio::HASH_FD_USER, $client->getFd());
            $user = $redis->hGetAll(self::HASH_USER_INFO . $userId);
            $redisPool->recycleObj($redis);
            $this->cleanUserCache($user);
        });
        Logger::getInstance()->console($params['message'], Logger::LOG_LEVEL_INFO);
    }

    /**
     * 客户端发送 HTTP 头部
     */
    public function httpConnect()
    {
        $params = $this->caller()->getArgs();
        Logger::getInstance()->console($params['message'], Logger::LOG_LEVEL_INFO);
    }

    /**
     * 客户端加入匹配池子
     */
    public function join()
    {
        $server = ServerManager::getInstance()->getSwooleServer();
        $client = $this->caller()->getClient();
        $params = $this->caller()->getArgs();

        $params['fd'] = $client->getFd();
        $params['score'] = $this->getScoreBy($params['match_times'], $params['match_score']);

        $hashKey = self::HASH_USER_INFO . $params['user_id'];
        $usersKey = self::ZSET_USER_ALL;
        if ($params['user_gender'] == 1) {
            $genderKey = self::ZSET_USER_MEN;
        } else {
            $genderKey = self::ZSET_USER_WOMEN;
        }

        $redisPool = Redis::getInstance()->get('redis');
        $redis = $redisPool->getObj();

        // 加入 user_id 映射 fd 的 Hash
        $redis->hSet(self::HASH_USER_FD, $params['user_id'], $client->getFd());
        $redis->hSet(self::HASH_FD_USER, $client->getFd(), $params['user_id']);

        // 加入 Hash 表缓存
        $redis->hMSet($hashKey, $params);

        // 加入所有用户评分集合
        $redis->zAdd($usersKey, $params['score'], $params['user_id']);

        // 根据性别进入对应评分集合
        $redis->zAdd($genderKey, $params['score'], $params['user_id']);
        $redisPool->recycleObj($redis);

        // 延迟 120 秒自动断开当前连接
        $this->delayClose($server, $client, $params);
    }

    /**
     * 延迟关闭连接
     *
     * @param $server
     * @param $client
     * @param $params
     */
    public function delayClose($server, $client, $params)
    {
        Timer::getInstance()->after(120 * 1000, function () use ($server, $client, $params) {
            $fd = $client->getFd();
            $header = <<<EOF
HTTP/1.1 200 OK
Server: Beauty
Content-Type: application/json; charset=UTF-8


EOF;
            $body = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => 32800,
                    'message' => '没有匹配到合适的用户',
                ],
                'id' => $fd,
            ];
            $server->send($fd, $header . json_encode($body));
            $server->close($fd);

            // 清除用户缓存数据
            $this->cleanUserCache($params);
        });
    }

    /**
     * 清除用户缓存
     *
     * @param $user
     */
    protected function cleanUserCache($user)
    {
        if (empty($user) || !isset($user['user_id'])) {
            return;
        }

        go(function () use ($user) {
            $hashKey = self::HASH_USER_INFO . $user['user_id'];
            $usersKey = self::ZSET_USER_ALL;
            if ($user['user_gender'] == 1) {
                $genderKey = self::ZSET_USER_MEN;
            } else {
                $genderKey = self::ZSET_USER_WOMEN;
            }

            $redisPool = Redis::getInstance()->get('redis');
            $redis = $redisPool->getObj();

            // 移除 Hash 表缓存
            if ($redis->exists($hashKey)) {
                $redis->del($hashKey);
            }

            // 移除用户评分集合所在元素
            if ($redis->exists($usersKey)) {
                $redis->zRem($usersKey, $user['user_id']);
            }

            // 移除性别评分集合所在元素
            if ($redis->exists($genderKey)) {
                $redis->zRem($genderKey, $user['user_id']);
            }

            // 移除 user_id 和 fd 的映射关系
            if ($redis->exists(self::HASH_USER_FD)) {
                $redis->hDel(self::HASH_USER_FD, $user['user_id']);
            }
            if ($redis->exists(self::HASH_FD_USER)) {
                $redis->hDel(self::HASH_FD_USER, $user['fd']);
            }

            $redisPool->recycleObj($redis);
        });
    }

    /**
     * 计算分数
     *
     * @param $matchTimes
     * @param $matchScore
     *
     * @return float|int
     */
    protected function getScoreBy($matchTimes, $matchScore)
    {
        if ($matchTimes == 0 && $matchScore == 0) {
            return 100;
        } elseif ($matchTimes > 0 && $matchScore <= 0) {
            return $matchScore;
        } else {
            return round($matchScore / $matchTimes);
        }
    }
}