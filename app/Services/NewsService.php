<?php

namespace App\Services;

use App\Redis\NewsRedisHelper;
use App\Repositories\NewsRepository;

class NewsService
{
    const NEWS_FOR_PAGE = 9;
    
    public function __construct(
        protected NewsRepository $newsRepository,
        protected NewsRedisHelper $newsRedisHelper
    ) {}

    public function getTagData(int $tagId): array
    {
        $cachedNews = $this->newsRedisHelper->getTagNews($tagId);
        
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $news = $this->newsRepository->getTagNews($tagId);
            $this->newsRedisHelper->setTagNews($news, $tagId);
            $message = 'бд';
        }

        $cachedTitle = $this->newsRedisHelper->getTagTitle($tagId);
        if ($cachedTitle) {
            $tagTitle =  $cachedTitle;
        } else {
            $tagTitle = $this->newsRepository->getTagTitle($tagId);
            $this->newsRedisHelper->setTagTitle($tagTitle, $tagId);
        }

        $result = ['news' => $news, 'message' => $message, 'tag_title' => $tagTitle];
        return $result;
    }

    public function getCategoryData(int $categoryId): array
    {
        $cachedNews = $this->newsRedisHelper->getCategoryNews($categoryId);
        
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $news = $this->newsRepository->getCategoryNews($categoryId);
            $this->newsRedisHelper->setCategoryNews($news, $categoryId);
            $message = 'бд';
        }

        $cachedTitle = $this->newsRedisHelper->getCategoryTitle($categoryId);
        if ($cachedTitle) {
            $categoryTitle =  $cachedTitle;
        } else {
            $categoryTitle = $this->newsRepository->getCategoryTitle($categoryId);
            $this->newsRedisHelper->setCategoryTitle($categoryTitle, $categoryId);
        }

        $result = ['news' => $news, 'message' => $message, 'category_title' => $categoryTitle];
        return $result;
    }

    public function getNewsPage(int $page): array
    { 
        $cachedNews = $this->newsRedisHelper->getNewsPage($page);
        $message = '';
        if ($cachedNews) {
            $news =  json_decode($cachedNews, true);
            $message = 'кеш';
        } else {
            $newsData = $this->newsRepository->getNewsPage($page);

            $news = [];
            foreach ($newsData as $item) {
                $titles = explode(',', $item['tag_titles']);
                $ids = explode(',', $item['tag_ids']);
                $tags = array_map(function($el1, $el2) {
                    return ['id' => $el1, 'title' => $el2];
                }, $ids, $titles);
                unset($item['tag_titles']);
                unset($item['tag_ids']);
                $item['tags'] = $tags;
                $news[] = $item;
            }

            $this->newsRedisHelper->setNewsPage($news, $page);
            $message = 'бд';
        }

        $newsList = [];
        foreach ($news as $item) {
            $views = $this->newsRedisHelper->getViews($item['id']);
            $item['views'] = $views ?: 0;
            $newsList[] = $item;
        }

        $result = ['news' => $newsList, 'message' => $message];
        return $result;
    }

    public function getPageCount(): int
    {
        $cachedCount = $this->newsRedisHelper->getNewsCount();
        if ($cachedCount) {
            $count =  $cachedCount;
        } else {
            $count = $this->newsRepository->getNewsCount();
            $this->newsRedisHelper->setNewsCount($count);
        }
        $pages = ceil($count / self::NEWS_FOR_PAGE);

        return $pages;
    }

    public function getPopular(): array
    {
        $popularIds = $this->newsRedisHelper->getPopular();
        $popular = [];
        foreach ($popularIds as $id => $score) {
            $cachedItem = $this->newsRedisHelper->getItem($id);
            if ($cachedItem) {
                $item = json_decode($cachedItem, true);
                $item['score'] = round($score);
                $popular[] = $item;
            }
        }

        return $popular;
    }

    public function getCityPopular(int $userId): array
    {
        if (empty($userId)) {
            return [];
        }

        $cachedCityPopular = $this->newsRedisHelper->getCityPopular($userId);
        if (!empty($cachedCityPopular)) {
            return $cachedCityPopular;
        }

        $closestUsers = $this->newsRedisHelper->getClosestUsers($userId);
        $recentlyArray = [];
        foreach ($closestUsers as $currentUser) {
            $currentRecently = $this->newsRedisHelper->getRecently($currentUser);
            $recentlyArray = [...$recentlyArray, ...$currentRecently];
        }

        $aggregated = array_reduce($recentlyArray, function($acc, $item) {
            $id = $item['id'];
            if (!empty($acc[$id])) {
                $acc[$id]['count']++;
            } else {
                $acc[$id] = ['count' => 1, 'item' => $item];
            }
            return $acc;
        }, []);
        usort($aggregated, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $result = array_column($aggregated, 'item');
        $result = array_slice($result, 0, 3);

        $this->newsRedisHelper->setCityPopular($result, $userId);

        return $result;
    }

    public function getNews(int $newsId): array
    {
        $cachedItem = $this->newsRedisHelper->getNews($newsId);
        $message = '';
        if ($cachedItem) {
            $item =  json_decode($cachedItem, true);
            $message = 'кеш';
        } else {
            $item = $this->newsRepository->getNews($newsId);
            $titles = explode(',', $item['tag_titles']);
            $ids = explode(',', $item['tag_ids']);
            $tags = array_map(function($el1, $el2) {
                return ['id' => $el1, 'title' => $el2];
            }, $ids, $titles);
            unset($item['tag_titles']);
            unset($item['tag_ids']);
            $item['tags'] = $tags;

            $this->newsRedisHelper->setNews($newsId, $item);
            $message = 'бд';
        }

        $result = ['item' => $item, 'message' => $message];
        return $result;
    }

    public function getViewsCount(int $newsId): int
    {
        $viewsCount = $this->newsRedisHelper->getViewsCount($newsId);
        return $viewsCount;
    }

    public function getCurrentLike(int $userId, int $newsId): bool
    {
        $like = $this->newsRedisHelper->getCurrentLike($userId, $newsId);
        return $like;
    }

    public function getLikeCount(int $newsId): int
    {
        $likeCount = $this->newsRedisHelper->getLikeCount($newsId);
        return $likeCount;
    }

    public function getTagSimilar(int $newsId): array
    {
        $data = $this->newsRedisHelper->getTagSimilar($newsId);
        if (!empty($data)) {
            $tagSimilar = [];
            foreach ($data as $news) {
                $tagSimilar[] = json_decode($news, true);
            }
        } else {
            $data = $this->newsRepository->getTagSimilarIds($newsId);
            $ids = array_column($data, 'news_id');
            $counts = array_column($data, 'common_tags', 'news_id');

            $tagSimilar = $this->newsRepository->getTagSimilar($ids);
            foreach ($tagSimilar as $news) {
                $this->newsRedisHelper->setTagSimilar($newsId, $counts[$news['id']], $news);
            }
        }

        return $tagSimilar;
    }

    public function getCategorySimilar(int $newsId): array
    {
        $data = $this->newsRedisHelper->getCategorySimilar($newsId);
        if (!empty($data)) {
            $categorySimilar = [];
            foreach ($data as $news) {
                $categorySimilar[] = json_decode($news, true);
            }
        } else {
            $categorySimilar = $this->newsRepository->getCategorySimilar($newsId);
            foreach ($categorySimilar as $news) {
                $this->newsRedisHelper->setCategorySimilar($newsId, $news);
            }
        }

        return $categorySimilar;
    }

    public function getRecently(int $userId): array
    {
        $recently = [];
        if (!empty($userId)) {
            $recently = $this->newsRedisHelper->getRecently($userId);
        }

        return $recently;
    }

    public function updateRecently(int $userId, array $item): void
    {
        if (!empty($userId)) {
            $shortItem = [
                'id' => $item['id'],
                'title' => $item['title'],
                'created_at' => $item['created_at'],
            ];
            $this->newsRedisHelper->updateRecently($userId, $shortItem);
        }
    }

    public function getPrefs(int $userId): array
    {
        if (empty($userId)) {
            return [];
        }

        $prefs = $this->newsRedisHelper->getRecommendations($userId);
        if (empty($prefs)) {
            $categoryIds = $this->newsRedisHelper->getPrefs($userId);
            if (empty($categoryIds)) {
                return [];
            }

            $count = count($categoryIds);
            $category0 = 0;
            $category1 = 0;
            $category2 = 0;
            $limit0 = 0;
            $limit1 = 0;
            $limit2 = 0;
            $news0 = [];
            $news1 = [];
            $news2 = [];

            switch ($count) {
                case 1:
                    $category0 = $categoryIds[0];
                    $limit0 = 3;
                    break;
                case 2:
                    $category0 = $categoryIds[0];
                    $category1 = $categoryIds[1];
                    $limit0 = 2;
                    $limit1 = 1;
                    break;
                case 3:
                    $category0 = $categoryIds[0];
                    $category1 = $categoryIds[1];
                    $category2 = $categoryIds[2];
                    $limit0 = 1;
                    $limit1 = 1;
                    $limit2 = 1;
                    break;
            }
            if (!empty($category0)) {
                $news0 = $this->newsRepository->getRandomNewsFromCategory($category0, $limit0);
            }
            if (!empty($category1)) {
                $news1 = $this->newsRepository->getRandomNewsFromCategory($category1, $limit1);
            }
            if (!empty($category2)) {
                $news2 = $this->newsRepository->getRandomNewsFromCategory($category2, $limit2);
            }
            $prefs = [...$news0, ...$news1, ...$news2];
            $ttl = $count * 100;
            $this->newsRedisHelper->updateRecommendations($userId, $prefs, $ttl);
        }

        return $prefs;
    }

    public function updatePrefs(int $userId, int $categoryId): void
    {
        if (!empty($userId)) {
            $this->newsRedisHelper->updatePrefs($userId, $categoryId);
        }
    }

    public function updatePopularViews(array $item): void
    {
        $this->newsRedisHelper->updateCategoryViews($item['category_id'], $item['category_title']);
        $this->newsRedisHelper->updateTagsViews($item['tags']);
        $this->newsRedisHelper->updateNewsViews($item['id']);
    }
}