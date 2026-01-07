<?php
namespace App\Services;

use App\Redis\UserRedisHelper;

class UserService
{
    public function __construct(
        protected UserRedisHelper $userRedisHelper
    ) {}
    
    public function checkRequestRate(string $ip): int
    {
        if (empty($ip)) {
            return 0;
        }

        $count = $this->userRedisHelper->getRequestCount($ip);
        return $count;
    }

    public function getActiveCount(): int
    {
        $this->userRedisHelper->cleanDeadUsers();
        $onlineCount = $this->userRedisHelper->getOnlineCount();
        return $onlineCount;
    }

    public function trackUserActivity(int $userId, string $ip): void
    {
        $lat = 0;
        $lon = 0;
        $geo = $this->userRedisHelper->geoUserExists($userId);

        if (!$geo && $ip == '127.0.0.1') {
            $res = file_get_contents('https://api.ipify.org');
            $res = trim($res);
            $res = filter_var($res, FILTER_VALIDATE_IP);
            $ip = !empty($res) ? $res : '';
        }

        if (!empty($ip) && !$geo) { 
            $url = "http://ip-api.com/json/{$ip}?fields=status,lat,lon";
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            if ($data['status'] == 'success') {
                $lat = $data['lat'];
                $lon = $data['lon'];
            }
        }

        $this->userRedisHelper->trackUserActivity($userId, $lat, $lon);
    }
}
