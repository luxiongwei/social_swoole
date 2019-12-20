<?php

namespace App\Service;

use EasySwoole\EasySwoole\Config;
use App\Util\SocialApi\HttpClient;

class SocialApiService
{
    /**
     * 获取社交用户好友关系
     * 
     * @param int $userId1
     * @param int $userId2
     */
    public static function isFriendShip($userId1, $userId2)
    {
        $config = Config::getInstance()->getConf('SOCIAL_API');
        $http = new HttpClient();
        $http->host = $config['host'];
        $http->key = $config['key'];
        $uri = 'index.php';
        $queryParams = ['r' => 'server/friend/is-friend', 'userId1' => $userId1, 'userId2' => $userId2];
        
        $queryResult = $http->get($uri, $queryParams);
        
        return (is_array($queryResult) && $queryResult['code'] == 0 && $queryResult['data']['is_friend'] == true);
    }
    
    /**
     * 获取社交用户关系
     * 
     * @param int $userId1
     * @param int $userId2
     */
    public static function userRelation($userId1, $userId2)
    {
        $config = Config::getInstance()->getConf('SOCIAL_API');
        $http = new HttpClient();
        $http->host = $config['host'];
        $http->key = $config['key'];
        $uri = 'index.php';
        $queryParams = ['r' => 'server/friend/relation', 'userId1' => $userId1, 'userId2' => $userId2];
        
        $queryResult = $http->get($uri, $queryParams);
        $queryResultData = ($queryResult['code'] === 0 && isset($queryResult['data'])) ? $queryResult['data'] : [];
        
        $result = [];
        $result[$userId1] = isset($queryResultData['relation'][$userId1]) ? $queryResultData['relation'][$userId1] : 0;
        $result[$userId2] = isset($queryResultData['relation'][$userId2]) ? $queryResultData['relation'][$userId2] : 0;
        
        return $result;
    }
}