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
            usleep(10);
        }
        echo "Worker #" . posix_getpid() . " connect $i finish\n";
        sleep(1000);
        exit;
    }
}
sleep(1000);
