<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use PDO;
use Redis;

#[AsCommand(name: 'views:sync', description: 'Перенос данных просмотров из Redis в MySQL')]
class ViewsSyncCommand extends Command
{
    protected $pdo;
    protected $redis;

    public function __construct(PDO $pdo, Redis $redis)
    {
        parent::__construct();
        $this->pdo = $pdo;
        $this->redis = $redis;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Синхронизация просмотров из Redis в MySQL');
            
            $keys = $this->redis->keys('news:views:page_*');
            
            if (empty($keys)) {
                $io->success('Нет данных для синхронизации');
                return Command::SUCCESS;
            }
            
            $io->text(sprintf('Найдено %d записей для обработки', count($keys)));
            
            $processed = 0;
            $failed = 0;
            
            foreach ($keys as $key) {
                try {
                    $pageId = str_replace('news:views:page_', '', $key);
                    $viewsCount = (int) $this->redis->get($key);
                    
                    if ($viewsCount > 0) {
                        $this->updateViews($pageId, $viewsCount);
                        
                        $processed++;
                        if ($processed % 100 === 0) {
                            $io->text(sprintf('Обработано %d записей...', $processed));
                        }
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $io->warning(sprintf('Ошибка при обработке ключа %s: %s', $key, $e->getMessage()));
                }
            }
            
            $io->success(sprintf('Синхронизация завершена. Обработано: %d, Ошибок: %d', $processed, $failed));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Критическая ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function updateViews($pageId, $viewsCount): void
    {
        $sql = "SELECT * FROM pages_views WHERE page_id = :page_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['page_id' => $pageId]);
        $res = $stmt->fetch();

        $sql = empty($res)
            ? "INSERT INTO pages_views (page_id, views) VALUES (:page_id, :views)"
            : "UPDATE pages_views SET views = :views WHERE page_id = :page_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['page_id' => $pageId, 'views' => $viewsCount]);
    }
}