<?php

namespace App\Redis;

use Redis;

class UserRedisHelper
{
    public function __construct(
        protected Redis $redis
    ) {}

    public function setLikesForUser($likes, $userId)
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sAdd($cacheKey, ...$likes);
    }

    public function addLike($userId, $newsId)
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sAdd($cacheKey, $newsId);

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, 1);

        $action = ['user_id' => $userId, 'news_id' => $newsId];
        $this->redis->lPush('queue_like', json_encode($action));
    }

    public function removeLike($userId, $newsId)
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sRem($cacheKey, $newsId); 

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, -1);
    }

    public function getRequestCount($ip)
    {
        $key = "rate_limit:$ip";
        $now = microtime(true);
        $windowStart = $now - 60;

        $this->redis->zremrangebyscore($key, 0, $windowStart);
        $this->redis->zadd($key, $now, $now);
        $this->redis->expire($key, 61);
        $count = $this->redis->zcount($key, $windowStart, $now);

        return $count;
    }

    public function cleanDeadUsers()
    {
        $allUsers = $this->redis->sMembers('active_users');
    
        foreach ($allUsers as $userId) {
            $userKey = "user_active:$userId";
            if (!$this->redis->exists($userKey)) {
                $this->redis->sRem('active_users', $userId);
            }
        }
    }

    public function getOnlineCount()
    {
        $setKey = 'active_users';
        $onlineCount = $this->redis->sCard($setKey); 
        return $onlineCount;
    }

    public function getQueueTask()
    {
        $dataJson = $this->redis->brPop('queue_like', 0);
        return $dataJson;
    }
}