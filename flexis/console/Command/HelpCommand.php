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
 * Команда для отображения справочных данных команды.
 */
class HelpCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'help';

    /**
     * Команда обработки справки для
     *
     * @var    AbstractCommand|null
     */
    private ?AbstractCommand $command = null;

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription('Показывает справку по команде');
        $this->setHelp(
            <<<'EOF'
Команда <info>%command.name%</info> отображает справочную информацию по команде:

<info>php %command.full_name% list</info>

Чтобы отобразить список доступных команд, используйте команду <info>list</info>.
EOF
        );

        $this->addArgument('command_name', InputArgument::OPTIONAL, 'Имя команды', 'help');
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
        if (!$this->command) {
            $this->command = $this->getApplication()->getCommand($input->getArgument('command_name'));
        }

        $descriptor = new DescriptorHelper();

        $this->getHelperSet()?->set($descriptor);

        $descriptor->describe($output, $this->command);

        return 0;
    }

    /**
     * Устанавливает команду, помощь которой предоставляется.
     *
     * @param   AbstractCommand  $command  Команда для обработки справки.
     *
     * @return  void
     */
    public function setCommand(AbstractCommand $command): void {
        $this->command = $command;
    }
}
