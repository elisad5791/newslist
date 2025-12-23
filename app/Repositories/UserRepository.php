<?php
namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(
        protected PDO $pdo
    ) {}

    public function getUserByName($name)
    {
        $sql = "SELECT * FROM users WHERE first_name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        $user = $stmt->fetch();

        return $user;
    }

    public function createUser($name)
    {
        $sql = "INSERT INTO users(first_name, last_name, email) VALUES(?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, $name, $name . '@mail.ru']);
        $userId = $this->pdo->lastInsertId();

        return $userId;
    }

    public function getLikes($userId)
    {
        $sql = "SELECT news_id FROM users_likes WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $likes = $stmt->fetchAll();
        $likes = array_column($likes, 'news_id');

        return $likes;
    }

    public function getLike($userId, $newsId)
    {
        $sql = "SELECT * FROM users_likes WHERE user_id = ? AND news_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);
        $like = $stmt->fetch();

        return $like;
    }

    public function addLike($userId, $newsId)
    {
        $sql = "INSERT INTO users_likes(user_id, news_id) VALUES(?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);
    }

    public function removeLike($userId, $newsId)
    {
        $sql = "DELETE FROM users_likes WHERE user_id = ? AND news_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $newsId]);
    }

    public function addLog($userId, $newsId)
    {
        $message = "User $userId add like to news $newsId";
        $sql = "INSERT INTO like_logs(message) VALUES(?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$message]);
    }
}