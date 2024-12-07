<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event\Command;

use Flexis\Console\Command\AbstractCommand;
use Flexis\Event\DispatcherAwareInterface;
use Flexis\Event\DispatcherAwareTrait;
use Flexis\Event\DispatcherInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда выводит информацию о диспетчере событий приложения.
 */
class DebugEventDispatcherCommand extends AbstractCommand implements DispatcherAwareInterface {
    use DispatcherAwareTrait;

    /**
     * Имя команды по умолчанию.
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'debug:event-dispatcher';

    /**
     * Создаёт экземпляр команды.
     *
     * @param DispatcherInterface $dispatcher Диспетчер событий приложения.
     *
     * @throws \ReflectionException
     */
    public function __construct(DispatcherInterface $dispatcher) {
        $this->setDispatcher($dispatcher);

        parent::__construct();
    }

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription("Отображает информацию о диспетчере событий приложения.");
        $this->addArgument('event', InputArgument::OPTIONAL, 'Показать слушателей определенного события');
        $this->setHelp(<<<'EOF'
Команда <info>%command.name%</info> выводит список всех зарегистрированных обработчиков событий в диспетчере событий приложения:

  <info>php %command.full_name%</info>

Чтобы получить конкретных слушателей события, укажите его имя:

  <info>php %command.full_name% application.before_execute</info>
EOF
        );
    }

    /**
     * Внутренняя функция для выполнения команды.
     *
     * @param InputInterface  $input  Входные данные для внедрения в команду.
     * @param OutputInterface $output Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды
     *
     * @throws \ReflectionException
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        if ($event = $input->getArgument('event')) {
            $listeners = $this->dispatcher->getListeners($event);

            if (empty($listeners)) {
                $io->warning(sprintf('У события "%s" нет зарегистрированных прослушивателей.', $event));

                return 0;
            }

            $io->title(sprintf('%s Зарегистрированные прослушиватели для события "%s"', $this->getApplication()->getName(), $event));

            $this->renderEventListenerTable($listeners, $io);

            return 0;
        }

        $listeners = $this->dispatcher->getListeners();

        if (empty($listeners)) {
            $io->comment('В диспетчере событий не зарегистрированы прослушиватели.');

            return 0;
        }

        $io->title(sprintf('%s Зарегистрированные слушатели, сгруппированные по событию', $this->getApplication()->getName()));

        ksort($listeners);

        foreach ($listeners as $subscribedEvent => $eventListeners) {
            $io->section(sprintf('"%s" событие', $subscribedEvent));

            $this->renderEventListenerTable((array)$eventListeners, $io);
        }

        return 0;
    }

    /**
     * Форматирует вызываемый ресурс для отображения в выводе консоли.
     *
     * @param   callable  $callable  Вызываемый ресурс для форматирования
     *
     * @return  string
     *
     * @throws  \ReflectionException
     *
     * @internal Этот метод основан на \Symfony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor::formatCallable()
     */
    private function formatCallable(callable $callable): string {
        if (\is_array($callable)) {
            if (\is_object($callable[0])) {
                return sprintf('%s::%s()', \get_class($callable[0]), $callable[1]);
            }

            return sprintf('%s::%s()', $callable[0], $callable[1]);
        }

        if (\is_string($callable)) {
            return sprintf('%s()', $callable);
        }

        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);

            if (str_contains($r->name, '{closure}')) {
                return 'Closure()';
            }

            if (null !== $class = $r->getClosureScopeClass()) {
                return sprintf('%s::%s()', $class->name, $r->name);
            }

            return $r->name . '()';
        }

        if (method_exists($callable, '__invoke')) {
            return sprintf('%s::__invoke()', \get_class((object) $callable));
        }

        throw new \InvalidArgumentException('Вызов не поддается описанию.');
    }

    /**
     * Отображает таблицу слушателей события.
     *
     * @param array         $eventListeners Слушатели на мероприятии.
     * @param SymfonyStyle  $io             Помощник ввода-вывода.
     *
     * @return  void
     *
     * @throws \ReflectionException
     */
    private function renderEventListenerTable(array $eventListeners, SymfonyStyle $io): void {
        $tableHeaders = ['Сортировка', 'Вызываемый'];
        $tableRows    = [];

        foreach ($eventListeners as $order => $listener) {
            $tableRows[] = [
                sprintf('#%d', $order + 1),
                $this->formatCallable($listener),
            ];
        }

        $io->table($tableHeaders, $tableRows);
    }
}
