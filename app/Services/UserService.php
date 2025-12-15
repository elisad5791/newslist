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
}
