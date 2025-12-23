<?php
namespace App\Services;

use App\Redis\UserRedisHelper;

class UserService
{
    public function __construct(
        protected UserRedisHelper $userRedisHelper
    ) {}
    
    public function checkRequestRate($ip)
    {
        if (empty($ip)) {
            return 0;
        }

        $count = $this->userRedisHelper->getRequestCount($ip);
        return $count;
    }

    public function getActiveCount()
    {
        $this->userRedisHelper->cleanDeadUsers();
        $onlineCount = $this->userRedisHelper->getOnlineCount();
        return $onlineCount;
    }

    public function trackUserActivity($userId)
    {
        $this->userRedisHelper->trackUserActivity($userId);
    }
}
