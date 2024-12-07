<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console;

use Flexis\Application\AbstractApplication;
use Flexis\Application\ApplicationEvents;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Console\Command\HelpCommand;
use Flexis\Console\Event\ApplicationErrorEvent;
use Flexis\Console\Event\BeforeCommandExecuteEvent;
use Flexis\Console\Event\CommandErrorEvent;
use Flexis\Console\Event\TerminateEvent;
use Flexis\Console\Exception\NamespaceNotFoundException;
use Flexis\Console\Loader\LoaderInterface;
use Flexis\Registry\Registry;
use Flexis\String\StringHelper;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Базовый класс приложения для приложения командной строки Flexis.
 */
class Application extends AbstractApplication {
    /**
     * Флаг, указывающий, что приложение должно автоматически завершить работу после выполнения команды.
     *
     * @var    boolean
     */
    private bool $autoExit = true;

    /**
     * Флаг, указывающий, что приложение должно перехватывать и обрабатывать Throwables.
     *
     * @var    boolean
     */
    private bool $catchThrowables = true;

    /**
     * Доступные команды.
     *
     * @var    AbstractCommand[]
     */
    private array $commands = [];

    /**
     * Загрузчик команд.
     *
     * @var    LoaderInterface|null
     */
    private ?LoaderInterface $commandLoader = null;

    /**
     * Обработчик консольного ввода.
     *
     * @var    InputInterface
     */
    private InputInterface $consoleInput;

    /**
     * Обработчик вывода консоли.
     *
     * @var    OutputInterface
     */
    private OutputInterface $consoleOutput;

    /**
     * Команда по умолчанию для приложения.
     *
     * @var    string
     */
    private string $defaultCommand = 'list';

    /**
     * Определение входных данных приложения.
     *
     * @var    InputDefinition|null
     */
    private ?InputDefinition $definition = null;

    /**
     * Набор помощников приложения.
     *
     * @var    HelperSet|null
     */
    private ?HelperSet $helperSet = null;

    /**
     * Флаг внутреннего отслеживания, если хранилище команд было инициализировано.
     *
     * @var    boolean
     */
    private bool $initialised = false;

    /**
     * Имя приложения.
     *
     * @var    string
     */
    private string $name = '';

    /**
     * Ссылка на выполняющуюся в данный момент команду.
     *
     * @var    AbstractCommand|null
     */
    private ?AbstractCommand $runningCommand = null;

    /**
     * Помощник консольного терминала.
     *
     * @var    Terminal
     */
    private Terminal $terminal;

    /**
     * Версия приложения.
     *
     * @var    string
     */
    private string $version = '';

    /**
     * Флаг внутреннего отслеживания, если пользователь обращается за помощью по данной команде.
     *
     * @var    boolean
     */
    private bool $wantsHelp = false;

    /**
     * Конструктор класса.
     *
     * @param   ?InputInterface   $input    Необязательный аргумент, обеспечивающий внедрение зависимостей для входного объекта приложения.
     *                                      Если аргументом является объект интерфейса ввода, этот объект станет входным объектом приложения,
     *                                      иначе создаётся входной объект по умолчанию.
     * @param   ?OutputInterface  $output   Необязательный аргумент, обеспечивающий внедрение зависимостей для выходного объекта приложения.
     *                                      Если аргументом является объект интерфейса вывода, этот объект станет выходным объектом приложения,
     *                                      иначе создаётся выходной объект по умолчанию.
     * @param   ?Registry         $config   Необязательный аргумент, обеспечивающий внедрение зависимостей для объекта конфигурации приложения.
     *                                      Если аргументом является объект реестра, этот объект станет объектом конфигурации приложения,
     *                                      иначе создаётся объект конфигурации по умолчанию.
     */
    public function __construct(?InputInterface $input = null, ?OutputInterface $output = null, ?Registry $config = null) {
        if (!\defined('STDOUT') || !\defined('STDIN') || !isset($_SERVER['argv'])) {
            $this->close();
        }

        $this->consoleInput  = $input ?: new ArgvInput();
        $this->consoleOutput = $output ?: new ConsoleOutput();
        $this->terminal      = new Terminal();

        parent::__construct($config);
    }

    /**
     * Добавляет объект команды.
     *
     * Если команда с таким именем уже существует, она будет переопределена.
     * Если команда не активирована, она не будет добавлена.
     *
     * @param   AbstractCommand  $command  Команда для добавления в приложение.
     *
     * @return  AbstractCommand
     * @throws  LogicException
     */
    public function addCommand(AbstractCommand $command): AbstractCommand {
        $this->initCommands();

        if (!$command->isEnabled()) {
            return $command;
        }

        $command->setApplication($this);

        try {
            $command->getDefinition();
        } catch (\TypeError $exception) {
            throw new LogicException(sprintf('Класс команды «%s» инициализирован неправильно.', \get_class($command)), 0, $exception);
        }

        if (!$command->getName()) {
            throw new LogicException(sprintf('У класса команды «%s» нет имени.', \get_class($command)));
        }

        $this->commands[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }

        return $command;
    }

    /**
     * Настраивает экземпляры консольного ввода и вывода для процесса.
     *
     * @return  void
     */
    protected function configureIO(): void {
        if ($this->consoleInput->hasParameterOption(['--ansi'], true)) {
            $this->consoleOutput->setDecorated(true);
        } elseif ($this->consoleInput->hasParameterOption(['--no-ansi'], true)) {
            $this->consoleOutput->setDecorated(false);
        }

        if ($this->consoleInput->hasParameterOption(['--no-interaction', '-n'], true)) {
            $this->consoleInput->setInteractive(false);
        }

        if ($this->consoleInput->hasParameterOption(['--quiet', '-q'], true)) {
            $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            $this->consoleInput->setInteractive(false);
        } else {
            if (
                $this->consoleInput->hasParameterOption('-vvv', true)
                || $this->consoleInput->hasParameterOption('--verbose=3', true)
                || $this->consoleInput->getParameterOption('--verbose', false, true) === 3
            ) {
                $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            } elseif (
                $this->consoleInput->hasParameterOption('-vv', true)
                || $this->consoleInput->hasParameterOption('--verbose=2', true)
                || $this->consoleInput->getParameterOption('--verbose', false, true) === 2
            ) {
                $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
            } elseif (
                $this->consoleInput->hasParameterOption('-v', true)
                || $this->consoleInput->hasParameterOption('--verbose=1', true)
                || $this->consoleInput->hasParameterOption('--verbose', true)
                || $this->consoleInput->getParameterOption('--verbose', false, true)
            ) {
                $this->consoleOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
            }
        }
    }

    /**
     * Метод для запуска подпрограмм приложения.
     *
     * @return  integer  Код выхода приложения
     *
     * @throws  \Throwable
     */
    protected function doExecute(): int {
        $input  = $this->consoleInput;
        $output = $this->consoleOutput;

        if ($input->hasParameterOption(['--version', '-V'], true)) {
            $output->writeln($this->getLongVersion());

            return 0;
        }

        try {
            $input->bind($this->getDefinition());
        } catch (ExceptionInterface $e) {
            // Ошибки следует игнорировать, полная привязка/проверка происходит позже, когда команда известна.
        }

        $name = $this->getCommandName($input);

        if ($input->hasParameterOption(['--help', '-h'], true)) {
            if (!$name) {
                $name  = 'help';
                $input = new ArrayInput(['command_name' => $this->defaultCommand]);
            } else {
                $this->wantsHelp = true;
            }
        }

        if (!$name) {
            $name       = $this->defaultCommand;
            $definition = $this->getDefinition();

            $definition->setArguments(
                array_merge(
                    $definition->getArguments(),
                    [
                        'command' => new InputArgument(
                            'command',
                            InputArgument::OPTIONAL,
                            $definition->getArgument('command')->getDescription(),
                            $name
                        ),
                    ]
                )
            );
        }

        try {
            $this->runningCommand = null;

            $command = $this->getCommand($name);
        } catch (\Throwable $e) {
            if ($e instanceof CommandNotFoundException && !($e instanceof NamespaceNotFoundException)) {
                (new SymfonyStyle($input, $output))->block(sprintf("\nКоманда \"%s\" не определена.\n", $name), null, 'error');
            }

            $event = new CommandErrorEvent($e, $this);

            $this->dispatchEvent(ConsoleEvents::COMMAND_ERROR, $event);

            if ($event->getExitCode() === 0) {
                return 0;
            }

            throw $event->getError();
        }

        $this->runningCommand = $command;
        $exitCode             = $this->runCommand($command, $input, $output);
        $this->runningCommand = null;

        return $exitCode;
    }

    /**
     * Выполняет приложение.
     *
     * @return  void
     * @throws  \Throwable
     */
    public function execute(): void {
        putenv('LINES=' . $this->terminal->getHeight());
        putenv('COLUMNS=' . $this->terminal->getWidth());

        $this->configureIO();

        $renderThrowable = function (\Throwable $e) {
            $this->renderThrowable($e);
        };

        if ($phpHandler = set_exception_handler($renderThrowable)) {
            restore_exception_handler();

            if (!\is_array($phpHandler) || !$phpHandler[0] instanceof ErrorHandler) {
                $errorHandler = true;
            } elseif ($errorHandler = $phpHandler[0]->setExceptionHandler($renderThrowable)) {
                $phpHandler[0]->setExceptionHandler($errorHandler);
            }
        }

        try {
            $this->dispatchEvent(ApplicationEvents::BEFORE_EXECUTE);

            $exitCode = $this->doExecute();

            $this->dispatchEvent(ApplicationEvents::AFTER_EXECUTE);
        } catch (\Throwable $throwable) {
            if (!$this->shouldCatchThrowables()) {
                throw $throwable;
            }

            $renderThrowable($throwable);

            $event = new ApplicationErrorEvent($throwable, $this, $this->runningCommand);

            $this->dispatchEvent(ConsoleEvents::APPLICATION_ERROR, $event);

            $exitCode = $event->getExitCode();

            if (is_numeric($exitCode)) {
                $exitCode = (int) $exitCode;

                if ($exitCode === 0) {
                    $exitCode = 1;
                }
            } else {
                $exitCode = 1;
            }
        } finally {
            if (!$phpHandler) {
                if (set_exception_handler($renderThrowable) === $renderThrowable) {
                    restore_exception_handler();
                }

                restore_exception_handler();
            } elseif (!$errorHandler) {
                $finalHandler = $phpHandler[0]->setExceptionHandler(null);

                if ($finalHandler !== $renderThrowable) {
                    $phpHandler[0]->setExceptionHandler($finalHandler);
                }
            }

            if ($this->shouldAutoExit() && isset($exitCode)) {
                $exitCode = min($exitCode, 255);
                $this->close($exitCode);
            }
        }
    }

    /**
     * Находит зарегистрированное пространство имен по имени.
     *
     * @param   string  $namespace  Пространство имен для поиска
     *
     * @return  string
     * @throws  NamespaceNotFoundException Когда пространство имен неверно или неоднозначно
     */
    public function findNamespace(string $namespace): string {
        $allNamespaces = $this->getNamespaces();

        $expr = preg_replace_callback(
            '{([^:]+|)}',
            function ($matches) {
                return preg_quote($matches[1]) . '[^:]*';
            },
            $namespace
        );

        $namespaces = preg_grep('{^' . $expr . '}', $allNamespaces);

        if (empty($namespaces)) {
            throw new NamespaceNotFoundException(sprintf('В пространстве имен «%s» не определены команды.', $namespace));
        }

        $exact = \in_array($namespace, $namespaces, true);

        if (\count($namespaces) > 1 && !$exact) {
            throw new NamespaceNotFoundException(sprintf('Пространство имен «%s» неоднозначно.', $namespace));
        }

        return $exact ? $namespace : reset($namespaces);
    }

    /**
     * Возвращает все команды, включая те, которые доступны через загрузчик команд,
     * при необходимости отфильтрованные по пространству имен команд.
     *
     * @param   string  $namespace  Необязательное пространство имен команд для фильтрации.
     *
     * @return  AbstractCommand[]
     */
    public function getAllCommands(string $namespace = ''): array {
        $this->initCommands();

        if ($namespace === '') {
            $commands = $this->commands;

            if (!$this->commandLoader) {
                return $commands;
            }

            foreach ($this->commandLoader->getNames() as $name) {
                if (!isset($commands[$name])) {
                    $commands[$name] = $this->getCommand($name);
                }
            }

            return $commands;
        }

        $commands = array_filter($this->commands, function ($name) use ($namespace) {
            return $namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1);
        }, ARRAY_FILTER_USE_KEY);

        if ($this->commandLoader) {
            foreach ($this->commandLoader->getNames() as $name) {
                if (!isset($commands[$name]) && $namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1)) {
                    $commands[$name] = $this->getCommand($name);
                }
            }
        }

        return $commands;
    }

    /**
     * Возвращает зарегистрированную команду по имени или псевдониму.
     *
     * @param   string  $name  Имя или псевдоним команды
     *
     * @return  AbstractCommand
     * @throws  CommandNotFoundException
     */
    public function getCommand(string $name): AbstractCommand {
        $this->initCommands();

        if (!$this->hasCommand($name)) {
            throw new CommandNotFoundException(sprintf('Команда «%s» не существует.', $name));
        }

        if (!isset($this->commands[$name]) && $this->commandLoader) {
            $this->addCommand($this->commandLoader->get($name));
        }

        $command = $this->commands[$name];

        if ($this->wantsHelp) {
            $this->wantsHelp = false;

            /** @var HelpCommand $helpCommand */
            $helpCommand = $this->getCommand('help');
            $helpCommand->setCommand($command);

            return $helpCommand;
        }

        return $command;
    }

    /**
     * Возвращает имя команды для запуска.
     *
     * @param   InputInterface  $input  Входные данные для чтения аргумента из.
     *
     * @return  string|null
     */
    protected function getCommandName(InputInterface $input): ?string {
        return $input->getFirstArgument();
    }

    /**
     * Возвращает зарегистрированные команды.
     *
     * Этот метод извлекает только те команды, которые были явно зарегистрированы.
     * Чтобы получить все команды, включая команды из загрузчика команд, используйте метод getAllCommands().
     *
     * @return  AbstractCommand[]
     */
    public function getCommands(): array {
        return $this->commands;
    }

    /**
     * Возвращает обработчик ввода консоли.
     *
     * @return  InputInterface
     */
    public function getConsoleInput(): InputInterface {
        return $this->consoleInput;
    }

    /**
     * Возвращает обработчик вывода консоли.
     *
     * @return  OutputInterface
     */
    public function getConsoleOutput(): OutputInterface {
        return $this->consoleOutput;
    }

    /**
     * Возвращает команды, которые по умолчанию должны быть зарегистрированы в приложении.
     *
     * @return  AbstractCommand[]
     */
    protected function getDefaultCommands(): array {
        return [
            new Command\ListCommand(),
            new Command\HelpCommand(),
        ];
    }

    /**
     * Создаёт определение ввода по умолчанию.
     *
     * @return  InputDefinition
     */
    protected function getDefaultInputDefinition(): InputDefinition {
        return new InputDefinition(
            [
                new InputArgument('command', InputArgument::REQUIRED, 'Команда для выполнения'),
                new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Отображение справочной информации'),
                new InputOption('--quiet', '-q', InputOption::VALUE_NONE, 'Флаг, указывающий, что весь вывод должен быть отключен.'),
                new InputOption(
                    '--verbose',
                    '-v|vv|vvv',
                    InputOption::VALUE_NONE,
                    'Увеличивает уровень детализации сообщений: 1 для обычного вывода, 2 для более подробного вывода и 3 для отладки.'
                ),
                new InputOption('--version', '-V', InputOption::VALUE_NONE, 'Отображает версию приложения'),
                new InputOption('--ansi', '', InputOption::VALUE_NONE, 'Принудительный вывод ANSI'),
                new InputOption('--no-ansi', '', InputOption::VALUE_NONE, 'Отключить вывод ANSI'),
                new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Флаг для отключения взаимодействия с пользователем'),
            ]
        );
    }

    /**
     * Создаёт вспомогательный набор по умолчанию.
     *
     * @return  HelperSet
     */
    protected function getDefaultHelperSet(): HelperSet {
        return new HelperSet(
            [
                new FormatterHelper(),
                new DebugFormatterHelper(),
                new ProcessHelper(),
                new QuestionHelper(),
            ]
        );
    }

    /**
     * Возвращает InputDefinition, связанный с этим приложением.
     *
     * @return  InputDefinition
     */
    public function getDefinition(): InputDefinition {
        if (!$this->definition) {
            $this->definition = $this->getDefaultInputDefinition();
        }

        return $this->definition;
    }

    /**
     * Возвращает вспомогательный набор, связанный с приложением.
     *
     * @return  HelperSet
     */
    public function getHelperSet(): HelperSet {
        if (!$this->helperSet) {
            $this->helperSet = $this->getDefaultHelperSet();
        }

        return $this->helperSet;
    }

    /**
     * Возвращает строку версии приложения.
     *
     * Обычно это имя и версия приложения, которые используются в выводе справки по приложению.
     *
     * @return  string
     */
    public function getLongVersion(): string {
        $name = $this->getName();

        if ($name === '') {
            $name = 'Консольное приложение Flexis';
        }

        if ($this->getVersion() !== '') {
            return sprintf('%s <info>%s</info>', $name, $this->getVersion());
        }

        return $name;
    }

    /**
     * Возвращает имя приложения.
     *
     * @return  string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Возвращает массив всех уникальных пространств имен, используемых зарегистрированными в данный момент командами.
     *
     * Обратите внимание: сюда не входит глобальное пространство имен, которое существует всегда.
     *
     * @return  string[]
     */
    public function getNamespaces(): array {
        $namespaces = [];

        foreach ($this->getAllCommands() as $command) {
            $namespaces = array_merge($namespaces, $this->extractAllNamespaces($command->getName()));

            foreach ($command->getAliases() as $alias) {
                $namespaces = array_merge($namespaces, $this->extractAllNamespaces($alias));
            }
        }

        return array_values(array_unique(array_filter($namespaces)));
    }

    /**
     * Возвращает версию приложения.
     *
     * @return  string
     */
    public function getVersion(): string {
        return $this->version;
    }

    /**
     * Проверяет, есть ли в приложении команда с данным именем.
     *
     * @param   string  $name  Имя команды, существование которой необходимо проверить.
     *
     * @return  boolean
     */
    public function hasCommand(string $name): bool {
        $this->initCommands();

        if (isset($this->commands[$name])) {
            return true;
        }

        if (!$this->commandLoader) {
            return false;
        }

        return $this->commandLoader->has($name);
    }

    /**
     * Пользовательский метод инициализации.
     *
     * @return  void
     */
    protected function initialise(): void {
        $this->set('cwd', getcwd());
    }

    /**
     * Отображает сообщение об ошибке для объекта Throwable.
     *
     * @param   \Throwable  $throwable  Объект Throwable, для которого требуется отобразить сообщение.
     *
     * @return  void
     */
    public function renderThrowable(\Throwable $throwable): void {
        $output = $this->consoleOutput instanceof ConsoleOutputInterface ? $this->consoleOutput->getErrorOutput() : $this->consoleOutput;

        $output->writeln('', OutputInterface::VERBOSITY_QUIET);

        $this->doRenderThrowable($throwable, $output);

        if (null !== $this->runningCommand) {
            $output->writeln(
                sprintf(
                    '<info>%s</info>',
                    sprintf($this->runningCommand->getSynopsis(), $this->getName())
                ),
                OutputInterface::VERBOSITY_QUIET
            );

            $output->writeln('', OutputInterface::VERBOSITY_QUIET);
        }
    }

    /**
     * Обрабатывает рекурсивную отрисовку сообщений об ошибках для Throwable и всех предыдущих Throwables, содержащихся в нем.
     *
     * @param   \Throwable       $throwable  Объект Throwable, для которого требуется отобразить сообщение.
     * @param   OutputInterface  $output     Выходной объект, которому нужно отправить сообщение.
     *
     * @return  void
     */
    protected function doRenderThrowable(\Throwable $throwable, OutputInterface $output): void {
        do {
            $message = trim($throwable->getMessage());

            if ($message === '' || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $class = \get_class($throwable);

                if ($class[0] === 'c' && str_starts_with($class, "class@anonymous\0")) {
                    $class = get_parent_class($class) ?: key(class_implements($class));
                }

                $title = sprintf('  [%s%s]  ', $class, ($code = $throwable->getCode()) !== 0 ? ' (' . $code . ')' : '');
                $len   = StringHelper::strlen($title);
            } else {
                $len = 0;
            }

            if (str_contains($message, "class@anonymous\0")) {
                $message = preg_replace_callback(
                    '/class@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)[0-9a-fA-F]++/',
                    function ($m) {
                        return class_exists($m[0], false) ? (get_parent_class($m[0]) ?: key(class_implements($m[0]))) . '@anonymous' : $m[0];
                    },
                    $message
                );
            }

            $width = $this->terminal->getWidth() ? $this->terminal->getWidth() - 1 : PHP_INT_MAX;
            $lines = [];

            foreach ($message !== '' ? preg_split('/\r?\n/', $message) : [] as $line) {
                foreach ($this->splitStringByWidth($line, $width - 4) as $line) {
                    $lineLength = StringHelper::strlen($line) + 4;
                    $lines[]    = [$line, $lineLength];
                    $len        = max($lineLength, $len);
                }
            }

            $messages = [];

            if (!$throwable instanceof ExceptionInterface || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $messages[] = sprintf(
                    '<comment>%s</comment>',
                    OutputFormatter::escape(
                        sprintf(
                            'In %s line %s:',
                            basename($throwable->getFile()) ?: 'n/a',
                            $throwable->getLine() ?: 'n/a'
                        )
                    )
                );
            }

            $messages[] = $emptyLine = sprintf('<error>%s</error>', str_repeat(' ', $len));

            if ($message === '' || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $messages[] = sprintf('<error>%s%s</error>', $title, str_repeat(' ', max(0, $len - StringHelper::strlen($title))));
            }

            foreach ($lines as $line) {
                $messages[] = sprintf('<error>  %s  %s</error>', OutputFormatter::escape($line[0]), str_repeat(' ', $len - $line[1]));
            }

            $messages[] = $emptyLine;
            $messages[] = '';

            $output->writeln($messages, OutputInterface::VERBOSITY_QUIET);

            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln('<comment>Трассировка исключений:</comment>', OutputInterface::VERBOSITY_QUIET);

                $trace = $throwable->getTrace();
                array_unshift(
                    $trace,
                    [
                        'function' => '',
                        'file'     => $throwable->getFile() ?: 'n/a',
                        'line'     => $throwable->getLine() ?: 'n/a',
                        'args'     => [],
                    ]
                );

                for ($i = 0, $count = \count($trace); $i < $count; ++$i) {
                    $class    = $trace[$i]['class'] ?? '';
                    $type     = $trace[$i]['type'] ?? '';
                    $function = $trace[$i]['function'] ?? '';
                    $file     = $trace[$i]['file'] ?? 'n/a';
                    $line     = $trace[$i]['line'] ?? 'n/a';

                    $output->writeln(
                        sprintf(
                            ' %s%s at <info>%s:%s</info>',
                            $class,
                            $function ? $type . $function . '()' : '',
                            $file,
                            $line
                        ),
                        OutputInterface::VERBOSITY_QUIET
                    );
                }

                $output->writeln('', OutputInterface::VERBOSITY_QUIET);
            }
        } while ($throwable = $throwable->getPrevious());
    }

    /**
     * Разбивает строку на заданную ширину для использования в выходных данных.
     *
     * @param   string   $string  Строка, которую нужно разделить.
     * @param   integer  $width   Максимальная ширина вывода.
     *
     * @return  string[]
     */
    private function splitStringByWidth(string $string, int $width): array {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return str_split($string, $width);
        }

        $utf8String = mb_convert_encoding($string, 'utf8', $encoding);
        $lines      = [];
        $line       = '';
        $offset     = 0;

        while (preg_match('/.{1,10000}/u', $utf8String, $m, 0, $offset)) {
            $offset += \strlen($m[0]);

            foreach (preg_split('//u', $m[0]) as $char) {
                if (mb_strwidth($line . $char, 'utf8') <= $width) {
                    $line .= $char;

                    continue;
                }

                $lines[] = str_pad($line, $width);
                $line    = $char;
            }
        }

        $lines[] = \count($lines) ? str_pad($line, $width) : $line;
        mb_convert_variables($encoding, 'utf8', $lines);

        return $lines;
    }

    /**
     * Запускает данную команду.
     *
     * @param   AbstractCommand  $command  Команда для запуска.
     * @param   InputInterface   $input    Входные данные для внедрения в команду.
     * @param   OutputInterface  $output   Вывод для внедрения в команду.
     *
     * @return  integer
     * @throws  \Throwable
     */
    protected function runCommand(AbstractCommand $command, InputInterface $input, OutputInterface $output): int {
        if ($command->getHelperSet() !== null) {
            foreach ($command->getHelperSet() as $helper) {
                if ($helper instanceof InputAwareInterface) {
                    $helper->setInput($input);
                }
            }
        }

        try {
            $this->getDispatcher();
        } catch (\UnexpectedValueException $exception) {
            return $command->execute($input, $output);
        }

        try {
            $command->mergeApplicationDefinition();
            $input->bind($command->getDefinition());
        } catch (ExceptionInterface $e) {
            // Пока игнорируем недопустимые параметры/аргументы.
        }

        $event     = new BeforeCommandExecuteEvent($this, $command);
        $exception = null;

        try {
            $this->dispatchEvent(ConsoleEvents::BEFORE_COMMAND_EXECUTE, $event);

            if ($event->isCommandEnabled()) {
                $exitCode = $command->execute($input, $output);
            } else {
                $exitCode = BeforeCommandExecuteEvent::RETURN_CODE_DISABLED;
            }
        } catch (\Throwable $exception) {
            $event = new CommandErrorEvent($exception, $this, $command);

            $this->dispatchEvent(ConsoleEvents::COMMAND_ERROR, $event);

            $exception = $event->getError();
            $exitCode  = $event->getExitCode();

            if ($exitCode === 0) {
                $exception = null;
            }
        }

        $event = new TerminateEvent($exitCode, $this, $command);

        $this->dispatchEvent(ConsoleEvents::TERMINATE, $event);

        if ($exception !== null) {
            throw $exception;
        }

        return $event->getExitCode();
    }

    /**
     * Устанавливает, должно ли приложение автоматически завершать работу.
     *
     * @param   boolean  $autoExit  Состояние автоматического выхода.
     *
     * @return  void
     */
    public function setAutoExit(bool $autoExit): void {
        $this->autoExit = $autoExit;
    }

    /**
     * Устанавливает, должно ли приложение перехватывать Throwables.
     *
     * @param   boolean  $catchThrowables  Состояние catch Throwables.
     *
     * @return  void
     */
    public function setCatchThrowables(bool $catchThrowables): void {
        $this->catchThrowables = $catchThrowables;
    }

    /**
     * Устанавливает загрузчик команд.
     *
     * @param   Loader\LoaderInterface  $loader  Новый загрузчик команд.
     *
     * @return  void
     */
    public function setCommandLoader(Loader\LoaderInterface $loader): void {
        $this->commandLoader = $loader;
    }

    /**
     * Устанавливает вспомогательный набор приложения.
     *
     * @param   HelperSet  $helperSet  Новый набор помощников.
     *
     * @return  void
     */
    public function setHelperSet(HelperSet $helperSet): void {
        $this->helperSet = $helperSet;
    }

    /**
     * Устанавливает имя приложения.
     *
     * @param   string  $name  Новое имя приложения.
     *
     * @return  void
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * Устанавливает версию приложения.
     *
     * @param   string  $version  Новая версия приложения.
     *
     * @return  void
     */
    public function setVersion(string $version): void {
        $this->version = $version;
    }

    /**
     * Возвращает состояние автоматического выхода приложения.
     *
     * @return  boolean
     */
    public function shouldAutoExit(): bool {
        return $this->autoExit;
    }

    /**
     * Возвращает состояние catch Throwables приложения.
     *
     * @return  boolean
     */
    public function shouldCatchThrowables(): bool {
        return $this->catchThrowables;
    }

    /**
     * Возвращает все пространства имен имени команды.
     *
     * @param   string  $name  Полное название команды
     *
     * @return  string[]
     */
    private function extractAllNamespaces(string $name): array {
        $parts      = explode(':', $name, -1);
        $namespaces = [];

        foreach ($parts as $part) {
            if (\count($namespaces)) {
                $namespaces[] = end($namespaces) . ':' . $part;
            } else {
                $namespaces[] = $part;
            }
        }

        return $namespaces;
    }

    /**
     * Возвращает часть пространства имен имени команды.
     *
     * @param   string    $name   Имя команды для обработки
     * @param   ?integer  $limit  Максимальное количество частей пространства имен
     *
     * @return  string
     */
    private function extractNamespace(string $name, ?int $limit = null): string {
        $parts = explode(':', $name);
        array_pop($parts);

        return implode(':', $limit === null ? $parts : \array_slice($parts, 0, $limit));
    }

    /**
     * Внутренняя функция для инициализации хранилища команд,
     * позволяющая отложенно загружать хранилище только при необходимости.
     *
     * @return  void
     */
    private function initCommands(): void {
        if ($this->initialised) {
            return;
        }

        $this->initialised = true;

        foreach ($this->getDefaultCommands() as $command) {
            $this->addCommand($command);
        }
    }
}
