<?php

namespace App\Redis;

use Redis;

class UserRedisHelper
{
    public function __construct(
        protected Redis $redis
    ) {}

    public function setLikesForUser(array $likes, int $userId): void
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sAdd($cacheKey, ...$likes);
    }

    public function addLike(int $userId, int $newsId): void
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sAdd($cacheKey, $newsId);

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, 1);

        $action = ['user_id' => $userId, 'news_id' => $newsId];
        $this->redis->lPush('queue_like', json_encode($action));
    }

    public function removeLike(int $userId, int $newsId): void
    {
        $cacheKey = "likes:user_$userId";
        $this->redis->sRem($cacheKey, $newsId); 

        $cacheKey = 'likes:news';
        $this->redis->hIncrBy($cacheKey, $newsId, -1);
    }

    public function getRequestCount(string $ip): int
    {
        $key = "rate_limit:$ip";
        $now = microtime(true);
        $windowStart = $now - 60;

        $this->redis->zAdd($key, $now, $now);
        $this->redis->expire($key, 60);
        $this->redis->zRemRangeByScore($key, 0, $windowStart);
        $count = $this->redis->zCount($key, $windowStart, $now);

        return $count;
    }

    public function cleanDeadUsers(): void
    {
        $allUsers = $this->redis->sMembers('active_users');
    
        foreach ($allUsers as $userId) {
            $userKey = "user_active:$userId";
            if (!$this->redis->exists($userKey)) {
                $this->redis->sRem('active_users', $userId);
            }
        }
    }

    public function getOnlineCount(): int
    {
        $setKey = 'active_users';
        $onlineCount = $this->redis->sCard($setKey); 
        return $onlineCount;
    }

    public function getQueueTask(): array
    {
        $data = $this->redis->brPop('queue_like', 0);
        return $data;
    }

    public function trackUserActivity(int $userId, float $lat, float $lon): void
    {
        $userKey = "user_active:{$userId}";
        $this->redis->setex($userKey, 300, 'active');
        $userKey = "user_geo_active:{$userId}";
        $this->redis->setex($userKey, 24 * 60 * 60, 'active');
        $setKey = "active_users";
        $this->redis->sAdd($setKey, $userId);
        $this->redis->expire($setKey, 300);

        if (!empty($lat) && !empty($lon)) {
            $key = 'userplaces';
            $this->redis->geoAdd($key, $lon, $lat, $userId);
            $this->redis->expire($key, 24 * 60 * 60);
        }

        $this->trackUserQuantity($userId);
    }

    public function trackUserQuantity(int $userId): void
    {
        $key = "user:day:active";
        $ttl = 24 * 60 * 60;
        $now = time();
        $windowStart = $now - $ttl;

        $this->redis->zAdd($key, $now, $userId);
        $this->redis->zRemRangeByScore($key, 0, $windowStart);
        $this->redis->expire($key, $ttl);
    }

    public function geoUserExists(int $userId): bool
    {
        $key = "user_geo_active:{$userId}";
        $result = $this->redis->get($key);
        return (bool) $result;
    }

    public function getLastDayUsers(): int
    {
        $key = "user:day:active";
        $ttl = 24 * 60 * 60;
        $now = time();
        $windowStart = $now - $ttl;

        $this->redis->zRemRangeByScore($key, 0, $windowStart);
        $this->redis->expire($key, $ttl);
        
        $count = $this->redis->zCard($key);
        return $count;
    }

    public function getLastHourUsers(): int
    {
        $key = "user:day:active";
        $window = 60 * 60;
        $now = time();
        $windowStart = $now - $window;

        $users = $this->redis->zRangeByScore($key, $windowStart, $now);
        $count = count($users);

        return $count;
    }
}