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

    public function getTagData($tagId)
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

    public function getCategoryData($categoryId)
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

    public function getNewsPage($page)
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

    public function getPageCount()
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

    public function getPopular()
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

    public function getRecently($userId)
    {
        $recently = [];
        if (!empty($userId)) {
            $recently = $this->newsRedisHelper->getRecently($userId);
        }

        return $recently;
    }

    public function getNews($newsId)
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

    public function getViewsCount($newsId)
    {
        $viewsCount = $this->newsRedisHelper->getViewsCount($newsId);
        return $viewsCount;
    }

    public function getCurrentLike($userId, $newsId)
    {
        $like = $this->newsRedisHelper->getCurrentLike($userId, $newsId);
        return $like;
    }

    public function getLikeCount($newsId)
    {
        $likeCount = $this->newsRedisHelper->getLikeCount($newsId);
        return $likeCount;
    }

    public function getTagSimilar($newsId)
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

    public function getCategorySimilar($newsId)
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

    public function updateRecently($userId, $item)
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
}