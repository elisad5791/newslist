<?php
namespace App\Services;

use Redis;
use PDO;

class LikeService
{
    protected $redis;
    protected $pdo;

    public function __construct(PDO $pdo, Redis $redis)
    {
        $this->redis = $redis;
        $this->pdo = $pdo;
    }
    
    public function runReceiver()
    {
        while (true) {
            $dataJson = $this->redis->brPop('queue_like', 0);
            $data = json_decode($dataJson[1], true); 
            
            $userId = $data['user_id'];
            $newsId = $data['news_id'];

            $this->addLog($userId, $newsId);
        }
    }

    public function addLog($userId, $newsId)
    {
        $message = "User $userId add like to news $newsId";
        $sql = "INSERT INTO like_logs(message) VALUES(?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$message]);
    }
}
