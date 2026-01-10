<?php

namespace App\Repositories;

use PDO;

class NewsRepository
{
    const NEWS_FOR_PAGE = 9;

    public function __construct(
        protected PDO $pdo
    ) {}

    public function getTagNews(int $tagId): array
    {
        $sql = "SELECT n.id, n.title, n.created_at 
            FROM news n
            LEFT JOIN news_tags nt ON nt.news_id = n.id
            WHERE nt.tag_id = ? 
            ORDER BY n.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tagId]);
        $news = $stmt->fetchAll();

        return $news;
    }

    public function getTagTitle(int $tagId): string
    {
        $sql = "SELECT title FROM tags WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tagId]);
        $data = $stmt->fetch();
        $title = $data['title'];

        return $title;
    }

    public function getCategoryNews(int $categoryId): array
    {
        $sql = "SELECT id, title, created_at FROM news WHERE category_id = ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        $news = $stmt->fetchAll();

        return $news;
    }

    public function getCategoryTitle(int $categoryId): string
    {
        $sql = "SELECT title FROM categories WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        $data = $stmt->fetch();
        $title = $data['title'];

        return $title;
    }

    public function getNewsCount(): int
    {
        $sql = "SELECT count(*) AS count FROM news";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetch();
        $count = $res['count'];

        return $count;
    }

    public function getNewsPage(int $page): array
    {
        $limit = self::NEWS_FOR_PAGE;
        $offset = self::NEWS_FOR_PAGE * ($page - 1);

        $sql = "SELECT 
                n.id, 
                n.title, 
                n.created_at,
                c.id AS category_id, 
                c.title AS category_title, 
                GROUP_CONCAT(t.title SEPARATOR ',') AS tag_titles,
                GROUP_CONCAT(t.id SEPARATOR ',') AS tag_ids
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            LEFT JOIN news_tags nt ON nt.news_id = n.id
            LEFT JOIN tags t ON t.id = nt.tag_id
            GROUP BY n.id, c.title
            ORDER BY n.created_at DESC 
            LIMIT $limit 
            OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $news = $stmt->fetchAll();

        return $news;
    }

    public function getCategorySimilar(int $newsId): array
    {
        $sql = "SELECT n2.id, n2.title, n2.created_at, c.title AS category_title
            FROM news n1
            JOIN news n2 ON n2.category_id = n1.category_id AND n2.id != n1.id
            LEFT JOIN categories c ON c.id = n1.category_id
            WHERE n1.id = ?
            ORDER BY n2.created_at DESC
            LIMIT 5";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newsId]);
        $categorySimilar = $stmt->fetchAll();

        return $categorySimilar;
    }

    public function getTagSimilarIds(int $newsId): array
    {
        $sql = "SELECT nt2.news_id, COUNT(*) as common_tags
            FROM news_tags nt1
            JOIN news_tags nt2 ON nt2.tag_id = nt1.tag_id
            WHERE nt1.news_id = ? AND nt2.news_id != ?
            GROUP BY nt2.news_id
            HAVING common_tags > 0
            ORDER BY common_tags DESC
            LIMIT 5";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newsId, $newsId]);
        $data = $stmt->fetchAll();

        return $data;
    }

    public function getTagSimilar(array $ids): array
    {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $orderField = 'FIELD(id, ' . str_repeat('?,', count($ids) - 1) . '?)';
        $sql = "SELECT id, title, created_at 
            FROM news 
            WHERE id IN ($placeholders) 
            ORDER BY $orderField";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$ids, ...$ids]);
        $tagSimilar = $stmt->fetchAll();

        return $tagSimilar;
    }

    public function getNews(int $newsId): array
    {
        $sql = "SELECT 
                n.id, 
                n.title, 
                n.content,
                n.created_at,
                c.id AS category_id, 
                c.title AS category_title, 
                GROUP_CONCAT(t.title SEPARATOR ',') AS tag_titles,
                GROUP_CONCAT(t.id SEPARATOR ',') AS tag_ids
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            LEFT JOIN news_tags nt ON nt.news_id = n.id
            LEFT JOIN tags t ON t.id = nt.tag_id
            WHERE n.id = ?
            GROUP BY n.id, c.title";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newsId]);
        $item = $stmt->fetch();

        return $item;
    }

    public function updateViews(array $data): void
    {
        $sql = "TRUNCATE TABLE news_views";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $values = [];
        $params = [];

        foreach ($data as $item) {
            $values[] = "(?, ?)";
            $params[] = $item['id'];
            $params[] = $item['views'];
        }

        $sql = "INSERT INTO news_views (news_id, views) VALUES " . implode(", ", $values);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function getRandomNewsFromCategory($categoryId, $count): array
    {
        $sql = "SELECT id, title, created_at
            FROM news
            WHERE category_id = ?
            LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId, $count]);
        $news = $stmt->fetchAll();

        return $news;
    }
}