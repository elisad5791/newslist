<?php

namespace App\Redis;

use Redis;

class NewsRedisHelper
{
    public function __construct(
        protected Redis $redis
    ) {}

    public function getTagNews(int $tagId): string
    {
        $cacheKey = "list:tag_$tagId";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setTagNews(array $news, int $tagId): void
    {
        $cacheKey = "list:tag_$tagId";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getTagTitle(int $tagId): string
    {
        $cacheKey = "title:tag_$tagId";
        $title = $this->redis->get($cacheKey);

        return $title;
    }

    public function setTagTitle(string $tagTitle, int $tagId): void
    {
        $cacheKey = "title:tag_$tagId";
        $this->redis->set($cacheKey, $tagTitle);
    }

    public function getCategoryNews(int $categoryId): string
    {
        $cacheKey = "list:category_$categoryId";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setCategoryNews(array $news, int $categoryId): void
    {
        $cacheKey = "list:category_$categoryId";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getCategoryTitle(int $categoryId): string
    {
        $cacheKey = "title:category_$categoryId";
        $title = $this->redis->get($cacheKey);

        return $title;
    }

    public function setCategoryTitle(string $categoryTitle, int $categoryId): void
    {
        $cacheKey = "title:category_$categoryId";
        $this->redis->set($cacheKey, $categoryTitle);
    }

    public function getPopular(): array
    {
        $popular = $this->redis->zRevRange('news:top', 0, 2, ['WITHSCORES' => true]);
        return $popular;
    }

    public function getCityPopular(int $userId): array
    {
        $key = "city_popular:user_{$userId}";
        $data = $this->redis->get($key);
        if (!empty($data)) {
            $data = json_decode($data, true);
        } else {
            $data = [];
        }
        return $data;
    }

    public function setCityPopular(array $data, int $userId): void
    {
        if (empty($data) || empty($userId)) {
            return;
        }

        $key = "city_popular:user_{$userId}";
        $data = json_encode($data);
        $this->redis->set($key, $data);
        $this->redis->expire($key, 600);
    }

    public function getClosestUsers(int $userId): array
    {
        $key = 'userplaces';
        $options = ['count' => 10];
        $data = $this->redis->geoRadiusByMember($key, $userId, 50, 'km', $options);
        $closestUsers = array_values(array_filter($data, fn($item) => $item != $userId));
        return $closestUsers;
    }

    public function getItem(int $id): string
    {
        $cacheKey = "item:news_$id";
        $item = $this->redis->get($cacheKey);

        return $item;
    }

    public function getNewsCount(): int
    {
        $cacheKey = 'list:count';
        $count = $this->redis->get($cacheKey);

        return $count;
    }

    public function setNewsCount(int $count): void
    {
        $cacheKey = 'list:count';
        $this->redis->set($cacheKey, $count);
    }

    public function getNewsPage(int $page): string
    {
        $cacheKey = "list:page_$page";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setNewsPage(array $news, int $page): void
    {
        $cacheKey = "list:page_$page";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getViews(int $id): int
    {
        $views = $this->redis->zScore('news:top', $id);
        return $views;
    }

    public function getRecently(int $userId): array
    {
        $cacheKey = "recent:user_{$userId}";
        $data = $this->redis->lRange($cacheKey, 0, -1);

        $recently = [];
        foreach ($data as $item) {
            $recently[] = json_decode($item, true);
        }

        return $recently;
    }

    public function updateRecently(int $userId, array $shortItem): void
    {
        $key = "recent:user_{$userId}";
        $val = json_encode($shortItem);
        $this->redis->lPush($key, $val);
        $this->redis->lTrim($key, 0, 2);
        $this->redis->expire($key, 24 * 60 * 60);
    }

    public function getRecommendations($userId): array
    {
        $key = "recommend:user_$userId";
        $data = $this->redis->get($key);
        $recommmend = !empty($data) ? json_decode($data, true) : [];
        return $recommmend;
    }

    public function updateRecommendations(int $userId, array $prefs, int $ttl): void
    {
        $key = "recommend:user_$userId";
        $this->redis->set($key, json_encode($prefs));
        $this->redis->expire($key, $ttl);
    }

    public function updatePrefs(int $userId, int $categoryId): void
    {
        $key = "prefs:user_{$userId}";
        $this->redis->zIncrBy($key, 1, $categoryId);
        $this->redis->expire($key, 24 * 60 * 60);
    }

    public function getPrefs(int $userId): array
    {
        $key = "prefs:user_{$userId}";
        $categoryIds = $this->redis->zRevRange($key, 0, 2);
        return $categoryIds;
    }

    public function getLikeCount(int $newsId): int
    {
        $cacheKey = 'likes:news';
        $likeCount = $this->redis->hGet($cacheKey, $newsId);
        if (empty($likeCount)) {
            $likeCount = 0;
        }

        return $likeCount;
    }

    public function getCurrentLike(int $userId, int $newsId): bool
    {
        $like = false;
        if (!empty($userId)) {
            $cacheKey = "likes:user_$userId";
            $likes = $this->redis->sMembers($cacheKey);
            $like = in_array($newsId, $likes);
        }

        return $like;
    } 

    public function getViewsCount(int $newsId): int
    {
        $viewsCount = $this->redis->zIncrBy('news:top', 1, $newsId);
        return $viewsCount;
    }

    public function getCategorySimilar(int $newsId): array
    {
        $cacheKey = "category:similar:news_$newsId";
        $data = $this->redis->lRange($cacheKey, 0, -1);
        return $data;
    }

    public function setCategorySimilar(int $newsId, array $news): void
    {
        $cacheKey = "category:similar:news_$newsId";
        $val = json_encode($news);
        $this->redis->lPush($cacheKey, $val);
    }

    public function getTagSimilar(int $newsId): array
    {
        $cacheKey = "tag:similar:news_$newsId";
        $data = $this->redis->zRevRange($cacheKey, 0, -1);
        return $data;
    }

    public function setTagSimilar(int $newsId, int $count, array $news): void
    {
        $cacheKey = "tag:similar:news_$newsId";
        $val = json_encode($news);
        $this->redis->zAdd($cacheKey, $count, $val);
    }

    public function getNews(int $newsId): string
    {
        $cacheKey = "item:news_$newsId";
        $news = $this->redis->get($cacheKey);
        if (empty($news)) {
            $news = '';
        }
        return $news;
    }

    public function setNews(int $newsId, array $item): void
    {
        $cacheKey = "item:news_$newsId";
        $this->redis->set($cacheKey, json_encode($item));
    }

    public function getViewsData(): array
    {
        $key = 'news:top';
        $newsIds = $this->redis->zRange($key, 0, -1);
        if (empty($newsIds)) {
            return [];
        }
        $viewsCounts = $this->redis->zMscore($key, ...$newsIds);

        $viewsData = [];
        foreach ($newsIds as $ind => $newsId) {
            $viewsData[] = ['id' => $newsId, 'views' => $viewsCounts[$ind]];
        }
        return $viewsData;
    }

    public function getPopularCategories(): array
    {
        $keys = $this->redis->keys('views:category_*');
        $data = [];
        $windowStart = floor(microtime(true)) - 3600;
        foreach ($keys as $key) {
            $categoryId = (int) str_replace('views:category_', '', $key);
            $this->redis->zRemRangeByScore($key, 0, $windowStart);
            $count = (int) $this->redis->zCard($key);
            $data[$categoryId] = $count;
        }

        arsort($data, SORT_NUMERIC);
        $ids = array_keys($data);
        $categoryIds = array_slice($ids, 0, 5);

        $result = [];
        foreach ($categoryIds as $categoryId) {
            $key = "views:title:category_$categoryId";
            $title = $this->redis->get($key) ?: '';
            $result[] = ['id' => $categoryId, 'title' => $title];
        }

        return $result;
    }

    public function getPopularTags(): array
    {
        $keys = $this->redis->keys('views:tag_*');
        $data = [];
        $windowStart = floor(microtime(true)) - 3600;
        foreach ($keys as $key) {
            $tagId = (int) str_replace('views:tag_', '', $key);
            $this->redis->zRemRangeByScore($key, 0, $windowStart);
            $count = (int) $this->redis->zCard($key);
            $data[$tagId] = $count;
        }

        arsort($data, SORT_NUMERIC);
        $ids = array_keys($data);
        $tagIds = array_slice($ids, 0, 5);

        $result = [];
        foreach ($tagIds as $tagId) {
            $key = "views:title:tag_$tagId";
            $title = $this->redis->get($key) ?: '';
            $result[] = ['id' => $tagId, 'title' => $title];
        }

        return $result;
    }

    public function getPopularNews(): array
    {
        $keys = $this->redis->keys('views:news_*');
        $data = [];
        $windowStart = floor(microtime(true)) - 3600;
        foreach ($keys as $key) {
            $newsId = (int) str_replace('views:news_', '', $key);
            $this->redis->zRemRangeByScore($key, 0, $windowStart);
            $count = (int) $this->redis->zCard($key);
            $data[$newsId] = $count;
        }

        arsort($data, SORT_NUMERIC);
        $ids = array_keys($data);
        $newsIds = array_slice($ids, 0, 5);

        $result = [];
        foreach ($newsIds as $newsId) {
            $key = "item:news_$newsId";
            $news = $this->redis->get($key) ?: '';
            $news = !empty($news) ? json_decode($news, true) : [];
            $createdAt = $news['created_at'] ?? '';
            $result[] = [
                'id' => $newsId, 
                'title' => $news['title'] ?? '',
                'created_at' => !empty($createdAt) ? date('d.m.Y, H:i', strtotime($createdAt)) : '',
            ];
        }

        return $result;
    }

    public function updateCategoryViews(int $categoryId, string $categoryTitle): void
    {
        $key = "views:category_$categoryId";
        $now = floor(microtime(true));
        $this->redis->zAdd($key, $now, $now);

        $key = "views:title:category_$categoryId";
        $this->redis->set($key, $categoryTitle);
    }

    public function updateTagsViews(array $tags): void
    {
        foreach ($tags as $tag) {
            $tagId = $tag['id'];
            $tagTitle = $tag['title'];

            $key = "views:tag_$tagId";
            $now = floor(microtime(true));
            $this->redis->zAdd($key, $now, $now);

            $key = "views:title:tag_$tagId";
            $this->redis->set($key, $tagTitle);
        }
    }

    public function updateNewsViews(int $newsId): void
    {
        $key = "views:news_$newsId";
        $now = floor(microtime(true));
        $this->redis->zAdd($key, $now, $now);
    }
}