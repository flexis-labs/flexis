<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Command;

use Flexis\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Команда со списком всех доступных команд.
 */
class ListCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию.
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'list';

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription("Список доступных команд приложения");
        $this->addArgument('namespace', InputArgument::OPTIONAL, 'Имя пространства имен');
        $this->setHelp(
            <<<'EOF'
Команда <info>%command.name%</info> выводит список всех команд приложения:

  <info>php %command.full_name%</info>
EOF
        );
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
        $descriptor = new DescriptorHelper();

        if ($this->getHelperSet() !== null) {
            $this->getHelperSet()->set($descriptor);
        }

        $descriptor->describe(
            $output,
            $this->getApplication(),
            [
                'namespace' => $input->getArgument('namespace'),
            ]
        );

        return 0;
    }
}
