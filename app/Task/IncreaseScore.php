<?php

namespace App\Task;

use App\TcpController\ChatRadio;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\RedisPool\Redis;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class IncreaseScore implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $server = ServerManager::getInstance()->getSwooleServer();

        $redisPool = Redis::getInstance()->get('redis');
        $redis = $redisPool->getObj();

        $start_fd = 0;
        while (true) {
            $conn_list = $server->getClientList($start_fd, 100);
            if ($conn_list === false or count($conn_list) === 0) {
                break;
            }
            $start_fd = end($conn_list);
            foreach ($conn_list as $fd) {
                if (!$redis->hExists(ChatRadio::HASH_FD_USER, $fd)) {
                    continue;
                }

                $userId = $redis->hGet(ChatRadio::HASH_FD_USER, $fd);
                $redis->zInCrBy(ChatRadio::ZSET_USER_ALL, 1, $userId);

                $user = $redis->hGetAll(ChatRadio::HASH_USER_INFO . $userId);
                if (empty($user)) {
                    continue;
                }

                if (isset($user['user_gender']) && $user['user_gender'] == 1) {
                    $redis->zInCrBy(ChatRadio::ZSET_USER_MEN, 1, $userId);
                } else {
                    $redis->zInCrBy(ChatRadio::ZSET_USER_WOMEN, 1, $userId);
                }
            }
        }
        
        $redisPool->recycleObj($redis);

        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        echo $throwable->getMessage();
    }
}