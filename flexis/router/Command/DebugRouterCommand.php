<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router\Command;

use Flexis\Console\Command\AbstractCommand;
use Flexis\Router\RouterInterface;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда выводит информацию о маршрутизаторе приложения.
 */
class DebugRouterCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'debug:router';

    /**
     * Маршрутизатор приложений.
     *
     * @var    RouterInterface
     */
    private RouterInterface $router;

    /**
     * Создаёт экземпляр команды.
     *
     * @param RouterInterface $router Маршрутизатор приложений.
     *
     * @throws \ReflectionException
     */
    public function __construct(RouterInterface $router) {
        $this->router = $router;

        parent::__construct();
    }

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription("Отображает информацию о маршрутах приложения");
        $this->addOption('show-controllers', null, InputOption::VALUE_NONE, 'Показать контроллер маршрута в обзоре');
        $this->setHelp(
            <<<'EOF'
Команда <info>%command.name%</info> выводит список всех маршрутов приложения:

  <info>php %command.full_name%</info>

Чтобы показать контроллеры, обрабатывающие каждый маршрут, используйте опцию <info>--show-controllers</info>:

  <info>php %command.full_name% --show-controllers</info>
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
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $showControllers = $input->getOption('show-controllers');

        $io->title(sprintf('%s Информация о маршрутизаторе', $this->getApplication()->getName()));

        if (empty($this->router->getRoutes())) {
            $io->warning('Маршрутизатор не имеет маршрутов.');

            return 0;
        }

        $tableHeaders = [
            'Методы',
            'Шаблон',
            'Правила',
        ];

        $tableRows = [];

        if ($showControllers) {
            $tableHeaders[] = 'Controller';
        }

        foreach ($this->router->getRoutes() as $route) {
            $row   = [];
            $row[] = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $row[] = $route->getPattern();

            $rules = $route->getRules();

            if (empty($rules)) {
                $row[] = 'N/A';
            } else {
                ksort($rules);

                $rulesAsString = '';

                foreach ($rules as $key => $value) {
                    $rulesAsString .= sprintf("%s: %s\n", $key, $this->formatValue($value));
                }

                $row[] = new TableCell(rtrim($rulesAsString), ['rowspan' => count($rules)]);
            }

            if ($showControllers) {
                $row[] = $this->formatCallable($route->getController());
            }

            $tableRows[] = $row;
        }

        $io->table($tableHeaders, $tableRows);

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

            if ($class = $r->getClosureScopeClass()) {
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
     * Форматирует значение как строку.
     *
     * @param   mixed  $value  Значение для форматирования
     *
     * @return  string
     *
     * @internal Этот метод основан на \Symfony\Bundle\FrameworkBundle\Console\Descriptor\Descriptor::formatValue()
     */
    private function formatValue(mixed $value): string {
        if (\is_object($value)) {
            return sprintf('object(%s)', \get_class($value));
        }

        if (\is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }
}
