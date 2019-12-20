<?php

namespace App\Service;

use App\TcpController\ChatRadio;
use App\Service\SocialApiService;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\EasySwooleEvent;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\ServerManager;
use Exception;
use Redis;
use Throwable;

/**
 * Class MatchService
 *
 * @package App\Service
 */
class MatchService
{
    private $redis;

    public function __construct()
    {
        $redis = new Redis();
        $config = Config::getInstance()->getConf('REDIS');
        $redis->connect($config['host'], $config['port'], 5);
        $this->redis = $redis;
    }

    /**
     * 匹配客户端
     */
    public function matchChatRadioConnection()
    {
        // 判断当前匹配池子是否存在两名以上的用户
        $count = $this->redis->zCard(ChatRadio::ZSET_USER_ALL);
        if ($count < 2) {
            if (EasySwooleEvent::$env === EasySwooleEvent::ENV_DEV) {
                Logger::getInstance()->waring('匹配连接数量不足');
            }

            return;
        }

        // 取出评分最高的两个用户
        $users = $this->getMatchUsersBy($this->redis);
        if (empty($users)) {
            return;
        }

        // 给指定的客户端发放 Token
        $this->sendTokenTo($users);

        return;
    }

    /**
     * 向用户发送 Token
     *
     * @param $users
     *
     * @throws Throwable
     */
    protected function sendTokenTo($users)
    {
        $fromUser = array_shift($users);
        $toUser = array_pop($users);
        $channel = base64_encode('21_chat_radio_match_' . $fromUser['user_id'] . '_with_' . $toUser['user_id']);

        $server = ServerManager::getInstance()->getSwooleServer();
        $this->responseHttpJson($server, $channel, $fromUser, $toUser);
    }

    /**
     * 响应 HTTP 协议 Json 格式数据
     *
     * @param $server
     * @param $channel
     * @param $fromUser
     * @param $toUser
     *
     * @throws Throwable
     */
    protected function responseHttpJson($server, $channel, $fromUser, $toUser)
    {
        if (empty($fromUser) || empty($toUser)) {
            return;
        }

        // 生成声网 Token
        list($fromToken, $fromBody) = $this->transformData($channel, $fromUser);
        list($toToken, $toBody) = $this->transformData($channel, $toUser);
        if (!$toToken || !$fromToken) {
            return;
        }
        
        $this->extraIsFriendShip($fromBody, $toBody, $fromUser, $toUser);
        $this->extraMatchUserInfo($fromBody, $toUser);
        $this->extraMatchUserInfo($toBody, $fromUser);

        $header = <<<HEADER
HTTP/1.1 200 OK
Server: Beauty
Content-Type: application/json; charset=UTF-8


HEADER;

        Logger::getInstance()->notice("匹配用户成功：" . json_encode([
                'from_user_id' => $fromUser['user_id'],
                'to_user_id' => $toUser['user_id'],
                'from_fd' => $fromUser['fd'],
                'to_fd' => $toUser['fd'],
            ]));

        $this->syncCleanUserCache($fromUser);
        $this->syncCleanUserCache($toUser);

        $server->send($fromUser['fd'], $header . json_encode($fromBody));
        $server->close($fromUser['fd']);
        $server->send($toUser['fd'], $header . json_encode($toBody));
        $server->close($toUser['fd']);

        return;
    }
    
    /**
     * 返回数据增加用户关系信息
     * 
     * @return void 
     */
    protected function extraIsFriendShip(&$fromBody, &$toBody, $fromUser, $toUser)
    {
        // 获取用户好友关系
        $relationResult = SocialApiService::userRelation($fromUser['user_id'], $toUser['user_id']);
        
        $fromBody['result']['relation'] = $relationResult[$fromUser['user_id']];
        $fromBody['result']['match_user_relation'] = $relationResult[$toUser['user_id']];
        
        $toBody['result']['relation'] = $relationResult[$toUser['user_id']];
        $toBody['result']['match_user_relation'] = $relationResult[$fromUser['user_id']];
    }
    
    /**
     * 返回数据增加对方信息
     * 
     * @return void 
     */
    protected function extraMatchUserInfo(&$body, $user)
    {
        $redis = $this->redis;
        
        $key = ChatRadio::HASH_USER_INFO . $user['user_id'];
        $userInfo = $redis->hGetAll($key);
        
        if (is_array($userInfo)) {
            foreach($userInfo as $key=>$item) {
                if (in_array($key, ['fd', 'score'])) {
                    unset($userInfo[$key]);
                    continue;
                }
                if (is_numeric($item)) {
                    $userInfo[$key] = (int)$item;
                }
            }
            
            $body['result']['match_user_info'] = $userInfo;
        } else {
            $body['result']['match_user_info'] = ['user_id' => $user['user_id']];
        }
    }

    /**
     * 格式化响应数据
     *
     * @param $channel
     * @param $user
     *
     * @return array
     */
    protected function transformData($channel, $user)
    {
        // 生成声网 Form User Token
        try {
            $token = AgoraService::generateRtcToken($channel, $user['user_id']);
            $body = [
                'jsonrpc' => '2.0',
                'result' => [
                    'token' => $token,
                    'channel' => $channel,
                    'user_id' => $user['user_id'],
                    'app_id' => Config::getInstance()->getConf('AGORA.app_id'),
                ],
                'id' => $user['fd'],
            ];
        } catch (Exception $e) {
            $token = null;
            $body = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => 32603,
                    'message' => '生成匹配用户 Token 失败',
                ],
                'id' => $user['fd'],
            ];
        }

        return [$token, $body];
    }

    /**
     * 同步删除缓存
     *
     * @param $user
     *
     * @throws Throwable
     */
    public function syncCleanUserCache($user)
    {
        if (empty($user) || !isset($user['user_id'])) {
            return;
        }

        $hashKey = ChatRadio::HASH_USER_INFO . $user['user_id'];
        $usersKey = ChatRadio::ZSET_USER_ALL;
        if ($user['user_gender'] == 1) {
            $genderKey = ChatRadio::ZSET_USER_MEN;
        } else {
            $genderKey = ChatRadio::ZSET_USER_WOMEN;
        }

        // 移除 Hash 表缓存
        $this->redis->del($hashKey);

        // 移除用户评分集合所在元素
        $this->redis->zRem($usersKey, $user['user_id']);

        // 移除性别评分集合所在元素
        $this->redis->zRem($genderKey, $user['user_id']);

        // 移除 user_id 和 fd 的映射关系
        $this->redis->hDel(ChatRadio::HASH_USER_FD, $user['user_id']);
        $this->redis->hDel(ChatRadio::HASH_FD_USER, $user['fd']);
    }

    /**
     * 匹配用户
     *
     * @param  Redis  $redis
     * @param  int  $start
     * @param  int  $stop
     *
     * @return array
     */
    protected function getMatchUsersBy(Redis $redis, $start = 0, $stop = 1)
    {
        if (empty($redis)) {
            return [];
        }

        $userIds = $redis->zRevRange(ChatRadio::ZSET_USER_ALL, $start, $stop);
        if (empty($userIds) || count((array)$userIds) < 2) {
            return [];
        }
        Logger::getInstance()->info('获取用户数据：' . json_encode($userIds));

        list($fromUserId, $toUserId) = $userIds;
        $fromUser = $redis->hGetAll(ChatRadio::HASH_USER_INFO . $fromUserId);
        $toUser = $redis->hGetAll(ChatRadio::HASH_USER_INFO . $toUserId);
        $users = $this->matchUsers($fromUser, $toUser);
        if ($users) {
            return $users;
        }

        if (empty($fromUser) || empty($toUser)) {
            return [];
        }

        switch ($fromUser['match_gender']) {
            // 用户甲乙匹配不成立时，用户甲不限性别，用户甲匹配按分数排序的丙、丁、戊、己、庚、辛....
            case 0:
            default:
                $subKey = ChatRadio::ZSET_USER_ALL;
                break;
            // 用户甲乙匹配不成立时，用户甲筛选性别，用户甲匹配按性别、分数排序的丙、丁、戊、己、庚、辛....
            case 1:
                $subKey = ChatRadio::ZSET_USER_MEN;
                break;
            case 2:
                $subKey = ChatRadio::ZSET_USER_WOMEN;
                break;
        }

        // 是否存在足够等待匹配的用户
        $userIds = $redis->zRevRange($subKey, 0, -1) ?: [];
        if (!$userIds) {
            return [];
        }
        Logger::getInstance()->info('遍历用户数据起始位置：' . json_encode(['start' => $start, 'userIds' => $userIds]));

        foreach ($userIds as $userId) {
            if (!isset($fromUser['user_id']) || !isset($toUser['user_id'])) {
                continue;
            }

            if (in_array($userId, [$fromUser['user_id'], $toUser['user_id']])) {
                continue;
            }

            $toUser = $redis->hGetAll(ChatRadio::HASH_USER_INFO . $userId);
            if (empty($toUser)) {
                continue;
            }

            $users = $this->matchUsers($fromUser, $toUser);
            if ($users) {
                return compact('fromUser', 'toUser');
            }
        }

        return $this->getMatchUsersBy($redis, ++$start, ++$stop);
    }

    /**
     * 用户两两匹配算法
     *
     * @param $fromUser
     * @param $toUser
     *
     * @return array
     */
    public function matchUsers($fromUser, $toUser)
    {
        if (empty($fromUser) || empty($toUser)) {
            return [];
        }
        Logger::getInstance()->info('匹配用户数据：' . json_encode([
                'fromUser' => $fromUser['user_id'],
                'toUser' => $toUser['user_id'],
                'fromFd' => $fromUser['fd'],
                'toFd' => $toUser['fd'],
            ]));

        // 如果用户甲乙都是不限性别
        if ($fromUser['match_gender'] == 0 && $toUser['match_gender'] == 0) {
            return compact('fromUser', 'toUser');
        }

        // 如果用户甲乙之间配对性别一致
        if ($fromUser['match_gender'] == $toUser['user_gender'] && $toUser['match_gender'] == $fromUser['user_gender']) {
            return compact('fromUser', 'toUser');
        }

        // 如果存在用户甲（乙）不限性别，用户（甲）乙配对性别
        if ($fromUser['match_gender'] == 0 && $toUser['match_gender'] == $fromUser['user_gender']) {
            return compact('fromUser', 'toUser');
        }
        if ($toUser['match_gender'] == 0 && $toUser['user_gender'] == $fromUser['match_gender']) {
            return compact('fromUser', 'toUser');
        }

        return [];
    }
}
