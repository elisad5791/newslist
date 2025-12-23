<?php

namespace App\Console;

use App\Redis\NewsRedisHelper;
use App\Repositories\NewsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'views:sync', description: 'Перенос данных просмотров из Redis в MySQL')]
class ViewsSyncCommand extends Command
{
    public function __construct(protected NewsRepository $newsRepository, protected NewsRedisHelper $newsRedisHelper)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Синхронизация просмотров из Redis в MySQL');
            
            $keys = $this->newsRedisHelper->getViewsKeys();
            
            if (empty($keys)) {
                $io->success('Нет данных для синхронизации');
                return Command::SUCCESS;
            }
            
            $io->text(sprintf('Найдено %d записей для обработки', count($keys)));
            
            $data = [];
            foreach ($keys as $key) {
                $newsId = str_replace('views:news_', '', $key);
                $viewsCount = $this->newsRedisHelper->getKey($key);
                
                if ($viewsCount > 0) {
                    $data[] = ['id' => $newsId, 'views' => $viewsCount];
                }
            }

            if (empty($data)) {
                $this->newsRepository->updateViews($data);
            }
            
            $io->success('Синхронизация завершена.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Критическая ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}