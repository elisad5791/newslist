<?php

namespace App\Redis;

use Redis;

class NewsRedisHelper
{
    public function __construct(
        protected Redis $redis
    ) {}

    public function getTagNews($tagId)
    {
        $cacheKey = "list:tag_$tagId";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setTagNews($news, $tagId)
    {
        $cacheKey = "list:tag_$tagId";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getTagTitle($tagId)
    {
        $cacheKey = "title:tag_$tagId";
        $title = $this->redis->get($cacheKey);

        return $title;
    }

    public function setTagTitle($tagTitle, $tagId)
    {
        $cacheKey = "title:tag_$tagId";
        $this->redis->set($cacheKey, $tagTitle);
    }

    public function getCategoryNews($categoryId)
    {
        $cacheKey = "list:category_$categoryId";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setCategoryNews($news, $categoryId)
    {
        $cacheKey = "list:category_$categoryId";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getCategoryTitle($categoryId)
    {
        $cacheKey = "title:category_$categoryId";
        $title = $this->redis->get($cacheKey);

        return $title;
    }

    public function setCategoryTitle($categoryTitle, $categoryId)
    {
        $cacheKey = "title:category_$categoryId";
        $this->redis->set($cacheKey, $categoryTitle);
    }

    public function getRecently($userId)
    {
        $cacheKey = "recent:user_{$userId}";
        $data = $this->redis->lRange($cacheKey, 0, -1);

        $recently = [];
        foreach ($data as $item) {
            $recently[] = json_decode($item, true);
        }

        return $recently;
    }

    public function getPopular()
    {
        $popular = $this->redis->zrevrange('news:top', 0, 4, ['WITHSCORES' => true]);
        return $popular;
    }

    public function getItem($id)
    {
        $cacheKey = "item:news_$id";
        $item = $this->redis->get($cacheKey);

        return $item;
    }

    public function getNewsCount()
    {
        $cacheKey = 'list:count';
        $count = $this->redis->get($cacheKey);

        return $count;
    }

    public function setNewsCount($count)
    {
        $cacheKey = 'list:count';
        $this->redis->set($cacheKey, $count);
    }

    public function getNewsPage($page)
    {
        $cacheKey = "list:page_$page";
        $news = $this->redis->get($cacheKey);

        return $news;
    }

    public function setNewsPage($news, $page)
    {
        $cacheKey = "list:page_$page";
        $this->redis->set($cacheKey, json_encode($news));
    }

    public function getViews($id)
    {
        $views = $this->redis->zscore('news:top', $id);
        return $views;
    }

    public function updateRecently($userId, $shortItem)
    {
        $key = "recent:user_{$userId}";
        $val = json_encode($shortItem);
        $this->redis->lPush($key, $val);
        $this->redis->lTrim($key, 0, 4);
    }

    public function getLikeCount($newsId)
    {
        $cacheKey = 'likes:news';
        $likeCount = $this->redis->hGet($cacheKey, $newsId);
        if (empty($likeCount)) {
            $likeCount = 0;
        }

        return $likeCount;
    }

    public function getCurrentLike($userId, $newsId)
    {
        $like = false;
        if (!empty($userId)) {
            $cacheKey = "likes:user_$userId";
            $likes = $this->redis->sMembers($cacheKey);
            $like = in_array($newsId, $likes);
        }

        return $like;
    }

    public function getViewsCount($newsId)
    {
        $viewsCount = $this->redis->zincrby('news:top', 1, $newsId);
        return $viewsCount;
    }

    public function getCategorySimilar($newsId)
    {
        $cacheKey = "category:similar:news_$newsId";
        $data = $this->redis->lRange($cacheKey, 0, -1);
        return $data;
    }

    public function setCategorySimilar($newsId, $news)
    {
        $cacheKey = "category:similar:news_$newsId";
        $val = json_encode($news);
        $this->redis->lPush($cacheKey, $val);
    }

    public function getTagSimilar($newsId)
    {
        $cacheKey = "tag:similar:news_$newsId";
        $data = $this->redis->zRevRange($cacheKey, 0, -1);
        return $data;
    }

    public function setTagSimilar($newsId, $count, $news)
    {
        $cacheKey = "tag:similar:news_$newsId";
        $val = json_encode($news);
        $this->redis->zAdd($cacheKey, $count, $val);
    }

    public function getNews($newsId)
    {
        $cacheKey = "item:news_$newsId";
        $news = $this->redis->get($cacheKey);
        return $news;
    }

    public function setNews($newsId, $item)
    {
        $cacheKey = "item:news_$newsId";
        $this->redis->set($cacheKey, json_encode($item));
    }
}