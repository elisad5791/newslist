<?php
namespace App\Services;

use Exception;
use Redis;

class UserService
{
    protected $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }
    
    public function checkRequestRate($ip)
    {
        if (empty($ip)) {
            return 0;
        }

        try {
            $key = "rate_limit:$ip";
            $now = microtime(true);
            $windowStart = $now - 60;

            $this->redis->zremrangebyscore($key, 0, $windowStart);
            $this->redis->zadd($key, $now, $now);
            $this->redis->expire($key, 61);
            $count = $this->redis->zcount($key, $windowStart, $now);

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getActiveCount()
    {
        $setKey = 'active_users';
        
        $this->cleanDeadUsers();
        $onlineCount = $this->redis->sCard($setKey);        
        
        return $onlineCount;
    }

    protected function cleanDeadUsers()
    {
        $allUsers = $this->redis->sMembers('active_users');
    
        foreach ($allUsers as $userId) {
            $userKey = "user_active:$userId";
            if (!$this->redis->exists($userKey)) {
                $this->redis->sRem('active_users', $userId);
            }
        }
    }
}
