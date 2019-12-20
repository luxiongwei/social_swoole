<?php

$clients = [];
for ($j = 0; $j < 10; $j++) {
    // fork 10 个子进程
    $pid = pcntl_fork();
    if ($pid > 0) {
        continue;
    } else {
        for ($i = 0; $i < 10; $i++) {
            // new 10 个同步阻塞的 TCP 连接
            $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
            $result = $client->connect('127.0.0.1', 9503, 0.5);

            // 判断是否连接成功
            if (!$result) {
                echo "#$i\tConnect fail.  errno=" . $client->errCode;
                die("\n");
            }
            $clients[] = $client;

            $userId = mt_rand(1, 999999999);
            $userGender = mt_rand(0, 2);
            $matchGender = mt_rand(0, 2);
            $request = <<<HTTP
GET  HTTP/1.1
Host: 127.0.0.1:9503
Connection: keep-alive
Keep-Alive: timeout=120
Content-Type: application/json
cache-control: no-cache

{
    "jsonrpc":"2.0",
    "method":"ChatRadio.join",
    "params":{
        "user_id":{$userId},
        "user_gender":{$userGender},
        "match_times":0,
        "match_score":0,
        "match_gender":{$matchGender}
    },
    "id":{$userId}
}            
HTTP;
            $client->send($request);
            usleep(10);
        }
        echo "Worker #" . posix_getpid() . " connect $i finish\n";
        sleep(1000);
        exit;
    }
}
sleep(1000);