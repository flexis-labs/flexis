<?php

/**
 * Часть пакета Flexis Framework Database.
 */

// phpcs:disable Generic.PHP.DeprecatedFunctions.Deprecated

namespace Flexis\Database\Command;

use Flexis\Archive\Archive;
use Flexis\Archive\Exception\UnknownArchiveException;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Database\DatabaseDriver;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\UnsupportedAdapterException;
use Flexis\Filesystem\Exception\FilesystemException;
use Flexis\Filesystem\File;
use Flexis\Filesystem\Folder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Консольная команда для импорта базы данных.
 */
class ImportCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию.
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'database:import';

    /**
     * Коннектор базы данных.
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
     * Проверяет, содержит ли zip-файл файлы экспорта базы данных.
     *
     * @param   string  $archive  ZIP-архив для анализа
     *
     * @return  void
     * @throws  \RuntimeException
     */
    private function checkZipFile(string $archive): void {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('Расширение PHP zip не установлено или отключено.');
        }

        $zip = zip_open($archive);

        if (!\is_resource($zip)) {
            throw new \RuntimeException('Невозможно открыть архив');
        }

        while ($file = @zip_read($zip)) {
            if (!str_contains(zip_entry_name($file), $this->db->getPrefix())) {
                zip_entry_close($file);
                @zip_close($zip);

                throw new \RuntimeException('Не удалось найти префикс базы данных, соответствующий таблице.');
            }

            zip_entry_close($file);
        }

        @zip_close($zip);
    }

    /**
     * Внутренняя функция для выполнения команды.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->title('Импорт базы данных');

        $totalTime = microtime(true);

        try {
            $importer = $this->db->getImporter()
                ->withStructure()
                ->asXml();
        } catch (UnsupportedAdapterException $e) {
            $symfonyStyle->error(sprintf('Драйвер базы данных «%s» не поддерживает импорт данных.', $this->db->getName()));

            return 1;
        }

        $folderPath = $input->getOption('folder');
        $tableName  = $input->getOption('table');
        $zipFile    = $input->getOption('zip');

        if ($zipFile) {
            if (!class_exists(File::class)) {
                $symfonyStyle->error('Пакет Composer "flexis/filesystem" не установлен и не может обрабатывать ZIP-файлы.');

                return 1;
            }

            if (!class_exists(Archive::class)) {
                $symfonyStyle->error('Пакет Composer "flexis/archive" не установлен и не может обрабатывать ZIP-файлы.');

                return 1;
            }

            $zipPath = $folderPath . '/' . $zipFile;

            try {
                $this->checkZipFile($zipPath);
            } catch (\RuntimeException $e) {
                $symfonyStyle->error($e->getMessage());

                return 1;
            }

            $folderPath .= File::stripExt($zipFile);

            try {
                Folder::create($folderPath);
            } catch (FilesystemException $e) {
                $symfonyStyle->error($e->getMessage());

                return 1;
            }

            try {
                (new Archive())->extract($zipPath, $folderPath);
            } catch (UnknownArchiveException $e) {
                $symfonyStyle->error($e->getMessage());
                Folder::delete($folderPath);

                return 1;
            }
        }

        if ($tableName) {
            $tables = [$tableName . '.xml'];
        } else {
            $tables = Folder::files($folderPath, '\.xml$');
        }

        foreach ($tables as $table) {
            $taskTime = microtime(true);
            $percorso = $folderPath . '/' . $table;

            if (!file_exists($percorso)) {
                $symfonyStyle->error(sprintf('Файл %s не существует.', $table));

                return 1;
            }

            $tableName = str_replace('.xml', '', $table);
            $symfonyStyle->text(sprintf('Импорт %1$s из %2$s', $tableName, $table));

            $importer->from(file_get_contents($percorso));

            $symfonyStyle->text(sprintf('Обработка таблицы %s', $tableName));

            try {
                $this->db->dropTable($tableName, true);
            } catch (ExecutionFailureException $e) {
                $symfonyStyle->error(sprintf('Ошибка выполнения инструкции DROP TABLE для %1$s: %2$s', $tableName, $e->getMessage()));

                return 1;
            }

            try {
                $importer->mergeStructure();
            } catch (\Exception $e) {
                $symfonyStyle->error(sprintf('Ошибка при объединении структуры для %1$s: %2$s', $tableName, $e->getMessage()));

                return 1;
            }

            try {
                $importer->importData();
            } catch (\Exception $e) {
                $symfonyStyle->error(sprintf('Ошибка импорта данных для %1$s: %2$s', $tableName, $e->getMessage()));

                return 1;
            }

            $symfonyStyle->text(sprintf('Импортированы данные для %s за %d сек.', $table, round(microtime(true) - $taskTime, 3)));
        }

        if ($zipFile) {
            Folder::delete($folderPath);
        }

        $symfonyStyle->success(sprintf('Импорт завершен через %d сек.', round(microtime(true) - $totalTime, 3)));

        return 0;
    }

    /**
     * Настройка команды.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription('Импортируйте базу данных');
        $this->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'Путь к папке, содержащей файлы для импорта', '.');
        $this->addOption('zip', null, InputOption::VALUE_REQUIRED, 'Имя ZIP-файла для импорта');
        $this->addOption('table', null, InputOption::VALUE_REQUIRED, 'Имя таблицы базы данных для импорта.');
    }
}
