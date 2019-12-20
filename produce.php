<?php

use EasySwoole\Redis\Config\RedisConfig;

return [
    'ENV' => "prod",
    'SERVER_NAME' => "Beauty",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9501,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER,
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 4,
            'reactor_num' => 2,
            'reload_async' => true,
            'max_wait_time' => 3,
        ],
        'TASK' => [
            'workerNum' => 1,
            'maxRunningNum' => 128,
            'timeout' => 15,
        ],
    ],
    'TEMP_DIR' => __DIR__ . '/temp',
    'LOG_DIR' => __DIR__ . '/log',
    'REDIS' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'auth' => '',
        'serialize' => RedisConfig::SERIALIZE_NONE,
    ],
    'AGORA' => [
        'app_id' => 'cd4176b7a45840c982b0b2165edcde92',
        'app_cer' => '2a35f5503050490190a36fec00171edc',
    ],
    'SOCIAL_API' => [
        'host' => 'http://api-beta-social.adnonstop.com',
        'key' => 'ASDFGHJKLQWERTYUIOPZXCVBNM',
    ],
    'HTTP_MATCH_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9502,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER,
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'open_length_check' => false,
            'heartbeat_check_interval' => 120,
            'heartbeat_idle_time' => 240,
            'reactor_num' => 2,
            'worker_num' => 4,
        ],
    ],
    'MATCH_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9503,
        'SOCK_TYPE' => SWOOLE_TCP,
        'SETTING' => [
            'open_length_check' => false,
            'heartbeat_check_interval' => 120,
            'heartbeat_idle_time' => 240,
            'reactor_num' => 2,
            'worker_num' => 4,
        ],
    ],
];
