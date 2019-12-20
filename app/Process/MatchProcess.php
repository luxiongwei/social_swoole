<?php

namespace App\Process;

use App\Service\MatchService;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Logger;
use Swoole\Lock;

/**
 * Class MatchProcess - 匹配进程
 *
 * @package App\Process
 */
class MatchProcess extends AbstractProcess
{
    /**
     * 进程启动执行的回调
     *
     * @param $arg
     */
    protected function run($arg)
    {
        Logger::getInstance()->info($this->getProcessName() . ' 匹配进程正在运行');

        while (true) {
            sleep(1);
            $lock = new Lock(SWOOLE_MUTEX);
            $matchService = new MatchService();

            $lock->lock();
            $matchService->matchChatRadioConnection();
            $lock->unlock();

            unset($lock);
            unset($matchService);
        }
    }

    /**
     * 进程抛出异常的回调
     *
     * @param  \Throwable  $throwable
     * @param  mixed  ...$args
     */
    protected function onException(\Throwable $throwable, ...$args)
    {
        Logger::getInstance()->error($this->getProcessName() . $throwable->getMessage());
    }
}