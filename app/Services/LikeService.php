<?php
namespace App\Services;

use App\Redis\UserRedisHelper;
use App\Repositories\UserRepository;

class LikeService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected UserRedisHelper $userRedisHelper
    ) {}

    public function addLike(int $userId, int $newsId): void
    {
        $like = $this->userRepository->getLike($userId, $newsId);
        if (!empty($like)) {
            return;
        }

        $this->userRepository->addLike($userId, $newsId);
        $this->userRedisHelper->addLike($userId, $newsId);
    }

    public function removeLike(int $userId, int $newsId): void
    {
        $like = $this->userRepository->getLike($userId, $newsId);
        if (empty($like)) {
            return;
        }

        $this->userRepository->removeLike($userId, $newsId);
        $this->userRedisHelper->removeLike($userId, $newsId);
    }
}
