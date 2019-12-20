<?php

namespace App\Service;

use App\Util\Agora\RtcTokenBuilder;
use DateTime;
use DateTimeZone;
use EasySwoole\EasySwoole\Config;

class AgoraService
{
    public static function generateRtcToken($channel, $userId)
    {
        $config = Config::getInstance()->getConf('AGORA');
        $appId = $config['app_id'];
        $appCer = $config['app_cer'];
        $expireTimeInSeconds = 3600;
        $currentTimestamp = (new DateTime("now", new DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        return RtcTokenBuilder::buildTokenWithUid($appId, $appCer, $channel, 0, RtcTokenBuilder::RolePublisher, $privilegeExpiredTs);
    }
}