<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Command;

use Flexis\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Базовый командный класс для приложения командной строки Flexis.
 */
abstract class AbstractCommand {
    /**
     * Имя команды по умолчанию
     *
     * @var    string|null
     */
    protected static ?string $defaultName = null;

    /**
     * Псевдонимы команды.
     *
     * @var    string[]
     */
    private array $aliases = [];

    /**
     * Приложение, выполняющее эту команду.
     *
     * @var    Application|null
     */
    private ?Application $application = null;

    /**
     * Флаг, было ли определение приложения объединено с этой командой.
     *
     * @var    boolean
     */
    private bool $applicationDefinitionMerged = false;

    /**
     * Флаг, было ли определение приложения с аргументами объединено с этой командой.
     *
     * @var    boolean
     */
    private bool $applicationDefinitionMergedWithArgs = false;

    /**
     * Определение ввода команды.
     *
     * @var    InputDefinition
     */
    private InputDefinition $definition;

    /**
     * Описание команды.
     *
     * @var    string
     */
    private string $description = '';

    /**
     * Помощь команды.
     *
     * @var    string
     */
    private string $help = '';

    /**
     * Набор помощников по вводу команды.
     *
     * @var    HelperSet|null
     */
    private ?HelperSet $helperSet = null;

    /**
     * Флаг отслеживания того, скрыта ли команда в списке команд.
     *
     * @var    boolean
     */
    private bool $hidden = false;

    /**
     * Имя команды.
     *
     * @var    string
     */
    private string $name;

    /**
     * Краткое содержание команды.
     *
     * @var    string[]
     */
    private array $synopsis = [];

    /**
     * Конструктор команд.
     *
     * @param string|null   $name       Название команды; если имя пусто и не установлено значение по умолчанию,
     *                                  имя должно быть установлено в методе configure().
     *
     * @throws \ReflectionException
     */
    public function __construct(?string $name = null) {
        $this->definition = new InputDefinition();

        if ($name !== null || null !== $name = static::getDefaultName()) {
            $this->setName($name);
        }

        $this->configure();
    }

    /**
     * Добавляет аргумент во входное определение.
     *
     * @param   string    $name         Имя аргумента.
     * @param   ?integer  $mode         Режим аргумента: InputArgument::REQUIRED или InputArgument::OPTIONAL.
     * @param   string    $description  Текст описания.
     * @param   mixed     $default      Значение по умолчанию (только для режима InputArgument::OPTIONAL).
     *
     * @return  $this
     */
    public function addArgument(string $name, ?int $mode = null, string $description = '', mixed $default = null): self {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));

        return $this;
    }

    /**
     * Добавляет параметр к определению ввода.
     *
     * @param   string              $name         Название опции.
     * @param   string|array|null   $shortcut     Ярлыки могут иметь значение null, строка ярлыков, разделенная | или массив ярлыков.
     * @param   ?integer            $mode         Режим опции: одна из констант VALUE_*.
     * @param   string              $description  Текст описания.
     * @param   ?mixed              $default      Значение по умолчанию (для InputOption::VALUE_NONE должно быть нулевым).
     *
     * @return  $this
     */
    public function addOption(
        string $name,
        string|array $shortcut = null,
        ?int $mode = null,
        string $description = '',
        mixed $default = null
    ): self {

        $this->definition->addOption(new InputOption($name, $shortcut, $mode, $description, $default));

        return $this;
    }

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {}

    /**
     * Внутренняя функция для выполнения команды.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды.
     */
    abstract protected function doExecute(InputInterface $input, OutputInterface $output): int;

    /**
     * Выполняет команду.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды.
     */
    public function execute(InputInterface $input, OutputInterface $output): int {
        $this->getSynopsis(true);
        $this->getSynopsis(false);

        $this->mergeApplicationDefinition();
        $input->bind($this->getDefinition());
        $this->initialise($input, $output);

        if ($input->hasArgument('command') && $input->getArgument('command') === null) {
            $input->setArgument('command', $this->getName());
        }

        $input->validate();

        return $this->doExecute($input, $output);
    }

    /**
     * Возвращает псевдонимы команд.
     *
     * @return  string[]
     */
    public function getAliases(): array {
        return $this->aliases;
    }

    /**
     * Возвращает объект приложения.
     *
     * @return  Application  Объект приложения.
     *
     * @throws  \UnexpectedValueException если приложение не установлено.
     */
    public function getApplication(): Application {
        if ($this->application) {
            return $this->application;
        }

        throw new \UnexpectedValueException('Приложение не установлено: ' . \get_class($this));
    }

    /**
     * Возвращает имя команды по умолчанию для этого класса.
     *
     * Это позволяет определять имя команды и использовать его без создания экземпляра полного класса команды.
     *
     * @return  string|null
     *
     * @throws \ReflectionException
     */
    public static function getDefaultName(): ?string {
        $class = \get_called_class();
        $r     = new \ReflectionProperty($class, 'defaultName');

        return $class === $r->class ? static::$defaultName : null;
    }

    /**
     * Возвращает InputDefinition, прикрепленный к этой команде.
     *
     * @return  InputDefinition
     */
    public function getDefinition(): InputDefinition {
        return $this->definition;
    }

    /**
     * Возвращает описание команды.
     *
     * @return  string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Возвращает помощь от командования.
     *
     * @return  string
     */
    public function getHelp(): string {
        return $this->help;
    }

    /**
     * Возвращает набор помощников по вводу команды.
     *
     * @return  HelperSet|null
     */
    public function getHelperSet(): ?HelperSet {
        return $this->helperSet;
    }

    /**
     * Возвращает имя команды.
     *
     * @return  string|null
     */
    public function getName(): ?string {
        return $this->name;
    }

    /**
     * Возвращает обработанную справку по команде.
     *
     * Этот метод используется для замены заполнителей в командах реальными значениями.
     * По умолчанию поддерживается `%command.name%` и `%command.full_name`.
     *
     * @return  string
     */
    public function getProcessedHelp(): string {
        $name = $this->getName();

        $placeholders = [
            '%command.name%',
            '%command.full_name%',
        ];

        $replacements = [
            $name,
            $_SERVER['PHP_SELF'] . ' ' . $name,
        ];

        return str_replace($placeholders, $replacements, $this->getHelp() ?: $this->getDescription());
    }

    /**
     * Возвращает краткий обзор команды.
     *
     * @param   boolean  $short  Флаг, указывающий, должна ли быть возвращена короткая или длинная версия синопсиса.
     *
     * @return  string
     */
    public function getSynopsis(bool $short = false): string {
        $key = $short ? 'short' : 'long';

        if (!isset($this->synopsis[$key])) {
            $this->synopsis[$key] = trim(sprintf('%s %s', $this->getName(), $this->getDefinition()->getSynopsis($short)));
        }

        return $this->synopsis[$key];
    }

    /**
     * Внутренний захватчик для инициализации команды после привязки ввода и до его проверки.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  void
     */
    protected function initialise(InputInterface $input, OutputInterface $output): void {}

    /**
     * Проверяет, включена ли команда в этой среде.
     *
     * @return  boolean
     */
    public function isEnabled(): bool {
        return true;
    }

    /**
     * Проверяет, скрыта ли команда в списке команд.
     *
     * @return  boolean
     */
    public function isHidden(): bool {
        return $this->hidden;
    }

    /**
     * Объединяет определение приложения с определением команды.
     *
     * @param   boolean  $mergeArgs  Флаг, указывающий, следует ли объединить аргументы определения приложения.
     *
     * @return  void
     *
     * @internal  На этот метод не следует полагаться как на часть общедоступного API.
     */
    final public function mergeApplicationDefinition(bool $mergeArgs = true): void {
        if (!$this->application || ($this->applicationDefinitionMerged && ($this->applicationDefinitionMergedWithArgs || !$mergeArgs))) {
            return;
        }

        $this->getDefinition()->addOptions($this->getApplication()->getDefinition()->getOptions());

        $this->applicationDefinitionMerged = true;

        if ($mergeArgs) {
            $currentArguments = $this->getDefinition()->getArguments();
            $this->getDefinition()->setArguments($this->getApplication()->getDefinition()->getArguments());
            $this->getDefinition()->addArguments($currentArguments);

            $this->applicationDefinitionMergedWithArgs = true;
        }
    }

    /**
     * Устанавливает псевдонимы команды.
     *
     * @param   string[]  $aliases  Псевдонимы команд.
     *
     * @return  void
     */
    public function setAliases(array $aliases): void {
        $this->aliases = $aliases;
    }

    /**
     * Устанавливает применение команды.
     *
     * @param   ?Application  $application  Приложение команды.
     *
     * @return  void
     */
    public function setApplication(?Application $application = null): void {
        $this->application = $application;

        if ($application) {
            $this->setHelperSet($application->getHelperSet());
        } else {
            $this->helperSet = null;
        }
    }

    /**
     * Устанавливает определение ввода для команды.
     *
     * @param   array|InputDefinition  $definition  Либо объект InputDefinition, либо массив объектов для записи в определение.
     *
     * @return  void
     */
    public function setDefinition(array|InputDefinition $definition): void {
        if ($definition instanceof InputDefinition) {
            $this->definition = $definition;
        } else {
            $this->definition->setDefinition($definition);
        }

        $this->applicationDefinitionMerged = false;
    }

    /**
     * Устанавливает описание команды.
     *
     * @param   string  $description  Описание для команды
     *
     * @return  void
     */
    public function setDescription(string $description): void {
        $this->description = $description;
    }

    /**
     * Устанавливает справку для команды.
     *
     * @param   string  $help Помощь для команды
     *
     * @return  void
     */
    public function setHelp(string $help): void {
        $this->help = $help;
    }

    /**
     * Устанавливает набор помощников по вводу команды.
     *
     * @param   HelperSet  $helperSet  Набор помощника.
     *
     * @return  void
     */
    public function setHelperSet(HelperSet $helperSet): void {
        $this->helperSet = $helperSet;
    }

    /**
     * Устанавливает, скрыта ли эта команда из списка команд.
     *
     * @param   boolean  $hidden  Флаг, если эта команда скрыта.
     *
     * @return  void
     */
    public function setHidden(bool $hidden): void {
        $this->hidden = $hidden;
    }

    /**
     * Устанавливает имя команды.
     *
     * @param   string  $name  Имя команды.
     *
     * @return  void
     */
    public function setName(string $name): void {
        $this->name = $name;
    }
}
