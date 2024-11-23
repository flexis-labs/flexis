<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Command;

use Flexis\Archive\Archive;
use Flexis\Archive\Zip;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Database\DatabaseDriver;
use Flexis\Database\Exception\UnsupportedAdapterException;
use Flexis\Filesystem\File;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Консольная команда для экспорта базы данных
 */
class ExportCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'database:export';

    /**
     * Коннектор базы данных
     *
     * @var    DatabaseDriver
     */
    private DatabaseDriver $db;

    /**
     * Создаёт экземпляр команды.
     *
     * @param DatabaseDriver $db Коннектор базы данных
     *
     * @throws \ReflectionException
     */
    public function __construct(DatabaseDriver $db) {
        $this->db = $db;

        parent::__construct();
    }

    /**
     * Внутренняя функция для выполнения команды.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды.
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->title('Экспорт базы данных');

        $totalTime = microtime(true);

        if (!class_exists(File::class)) {
            $symfonyStyle->error('Пакет Composer "flexis/filesystem" не установлен, не удается создать экспорт.');

            return 1;
        }

        try {
            $exporter = $this->db->getExporter()
                ->withStructure();
        } catch (UnsupportedAdapterException $e) {
            $symfonyStyle->error(sprintf('Драйвер базы данных «%s» не поддерживает экспорт данных.', $this->db->getName()));

            return 1;
        }

        $folderPath = $input->getOption('folder');
        $tableName  = $input->getOption('table');
        $zip        = $input->getOption('zip');

        $zipFile = $folderPath . '/data_exported_' . date("Y-m-d\TH-i-s") . '.zip';
        $tables  = $this->db->getTableList();
        $prefix  = $this->db->getPrefix();

        if ($tableName) {
            if (!\in_array($tableName, $tables)) {
                $symfonyStyle->error(sprintf('Таблица %s не существует в базе данных.', $tableName));

                return 1;
            }

            $tables = [$tableName];
        }

        if ($zip) {
            if (!class_exists(Archive::class)) {
                $symfonyStyle->error('Пакет Composer «flexis/archive» не установлен, это не позволяет создавать ZIP-файлы.');

                return 1;
            }

            /** @var Zip $zipArchive */
            $zipArchive = (new Archive())->getAdapter('zip');

            $filenames = [];
            $zipFilesArray = [];
        }

        foreach ($tables as $table) {
            if (strlen($prefix) === 0 || str_contains(substr($table, 0, strlen($prefix)), $prefix)) {
                $taskTime = microtime(true);
                $filename = $folderPath . '/' . $table . '.xml';

                $symfonyStyle->text(sprintf('Обработка таблицы %s', $table));

                $data = (string) $exporter->from($table)->withData(true);

                if (file_exists($filename)) {
                    File::delete($filename);
                }

                File::write($filename, $data);

                if ($zip) {
                    $zipFilesArray[] = ['name' => $table . '.xml', 'data' => $data];
                    $filenames[] = $filename;
                }

                $symfonyStyle->text(sprintf('Экспортированы данные для %s за %d сек.', $table, round(microtime(true) - $taskTime, 3)));
            }
        }

        if ($zip) {
            $zipArchive->create($zipFile, $zipFilesArray);
            foreach ($filenames as $fname) {
                File::delete($fname);
            }
        }

        $symfonyStyle->success(sprintf('Экспорт завершен через %d сек.', round(microtime(true) - $totalTime, 3)));

        return 0;
    }

    /**
     * Настройка команды.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription('Экспортировать базу данных');
        $this->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'Путь для записи файлов экспорта', '.');
        $this->addOption('table', null, InputOption::VALUE_REQUIRED, 'Имя таблицы базы данных для экспорта.');
        $this->addOption('zip', null, InputOption::VALUE_NONE, 'Флаг, указывающий, что экспорт будет сохранен в ZIP-архив.');
    }
}
