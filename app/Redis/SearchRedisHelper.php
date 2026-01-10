<?php

namespace App\Redis;

use Ehann\RedisRaw\PhpRedisAdapter;
use Ehann\RediSearch\Index;

class SearchRedisHelper
{
    protected $newsIndex;

    public function __construct(protected PhpRedisAdapter $redisAdapter)
    {
        $this->newsIndex = new Index($this->redisAdapter, 'news_search');
        $this->newsIndex
            ->addTextField('title')
            ->addTextField('content')
            ->addTextField('created_at')
            ->addTagField('newsid');
    }

    public function createIndex(): void
    {
        $allIndices = $this->redisAdapter->rawCommand('FT._LIST', []); 

        if (!in_array('news_search', $allIndices)) {
            $this->newsIndex->create();
        }
    }

    public function addNews(array $item): void
    {
        $allIndices = $this->redisAdapter->rawCommand('FT._LIST', []); 

        if (!in_array('news_search', $allIndices)) {
            return;
        }

        $count = $this->newsIndex->tagFilter('newsid', [$item['id']])->count(); 
        $keyExists = $count > 0;
        if ($keyExists) {
            return;
        }

        $createdAt = date('d.m.Y, H:i', strtotime($item['created_at']));
        $this->newsIndex->add([
            'title' => $item['title'],
            'content' => $item['content'],
            'created_at' => $createdAt,
            'newsid' => $item['id'],
        ]);
    }

    public function getSearchNews(string $q): array
    {
        $allIndices = $this->redisAdapter->rawCommand('FT._LIST', []); 

        if (!in_array('news_search', $allIndices)) {
            return [];
        }

        $result = $this->newsIndex->search($q, true);
        $documents = $result->getDocuments(); 
        return $documents;
    }
}
