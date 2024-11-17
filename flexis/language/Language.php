<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Класс обработчика языков/перевода
 */
class Language {
    /**
     * Язык отладки. Если установлено значение true, выделяется, если строка не найдена.
     *
     * @var    boolean
     */
    protected bool $debug = false;

    /**
     * Язык по умолчанию, используемый, когда языковой файл на запрошенном языке не существует.
     *
     * @var    string
     */
    protected string $default = 'ru-RU';

    /**
     * Массив потерянного текста.
     *
     * @var    array
     */
    protected array $orphans = [];

    /**
     * Массив, содержащий метаданные языка.
     *
     * @var    array
     */
    protected array $metadata;

    /**
     * Массив, содержащий языковой стандарт или логическое значение NULL, если его нет.
     *
     * @var    array|boolean
     */
    protected array|bool $locale;

    /**
     * Язык для загрузки.
     *
     * @var    string
     */
    protected string $lang;

    /**
     * Вложенный массив загруженных языковых файлов.
     *
     * @var    array
     */
    protected array $paths = [];

    /**
     * Список языковых файлов, находящихся в состоянии ошибки
     *
     * @var    array
     */
    protected array $errorfiles = [];

    /**
     * Массив используемого текста, используемого во время отладки.
     *
     * @var    array
     */
    protected array $used = [];

    /**
     * Счетчик количества загрузок.
     *
     * @var    integer
     */
    protected int $counter = 0;

    /**
     * Массив, используемый для хранения переопределений.
     *
     * @var    array
     */
    protected array $override = [];

    /**
     * Объект локализации.
     *
     * @var    LocaliseInterface
     */
    protected LocaliseInterface $localise;

    /**
     * Объект LanguageHelper.
     *
     * @var    LanguageHelper
     */
    protected LanguageHelper $helper;

    /**
     * Базовый путь к языковой папке.
     *
     * @var    string
     */
    protected string $basePath;

    /**
     * Объект каталога сообщений.
     *
     * @var    MessageCatalogue
     */
    protected MessageCatalogue $catalogue;

    /**
     * Реестр языкового парсера.
     *
     * @var    ParserRegistry
     */
    protected ParserRegistry $parserRegistry;

    /**
     * Конструктор, активирующий информацию о языке по умолчанию.
     *
     * @param   ParserRegistry  $parserRegistry  Реестр, содержащий поддерживаемые парсеры файлов.
     * @param   string          $path            Базовый путь к языковой папке.
     * @param   string          $lang            Язык.
     * @param   boolean         $debug           Указывает, включена ли языковая отладка.
     */
    public function __construct(
        ParserRegistry $parserRegistry,
        string $path,
        string $lang = '',
        bool $debug = false
    ) {
        if (empty($path)) {
            throw new \InvalidArgumentException(
                'Переменная $path не может быть пустой при создании нового объекта языка.'
            );
        }

        $this->basePath = $path;
        $this->helper   = new LanguageHelper();

        $this->lang = $lang ?: $this->default;

        $this->metadata = $this->helper->getMetadata($this->lang, $this->basePath);
        $this->setDebug($debug);

        $this->parserRegistry = $parserRegistry;

        $basePath = $this->helper->getLanguagePath($this->basePath);

        $filename = $basePath . "/overrides/$lang.override.ini";

        if (file_exists($filename) && $contents = $this->parse($filename)) {
            if (\is_array($contents)) {
                ksort($contents, SORT_STRING);
                $this->override = $contents;
            }

            unset($contents);
        }

        $this->localise  = (new LanguageFactory())->getLocalise($lang, $path);
        $this->catalogue = new MessageCatalogue($this->lang);

        $this->load();
    }

    /**
     * Функция перевода имитирует функцию php gettext.
     *
     * Функция проверяет, истинно ли значение $jsSafe, а затем значение $interpretBackslashes.
     *
     * @param   string   $string                Строка для перевода.
     * @param   boolean  $jsSafe                Сделайте результат безопасным на JavaScript.
     * @param   boolean  $interpretBackSlashes  Интерпретировать \t и \n.
     *
     * @return  string  Перевод строки
     */
    public function translate(string $string, bool $jsSafe = false, bool $interpretBackSlashes = true): string {
        if ($string == '') {
            return '';
        }

        $key = strtoupper($string);

        if ($this->catalogue->hasMessage($key)) {
            $string = $this->debug ? '**' . $this->catalogue->getMessage($key) . '**' : $this->catalogue->getMessage($key);

            if ($this->debug) {
                $caller = $this->getCallerInfo();

                if (!array_key_exists($key, $this->used)) {
                    $this->used[$key] = [];
                }

                $this->used[$key][] = $caller;
            }
        } else {
            if ($this->debug) {
                $caller           = $this->getCallerInfo();
                $caller['string'] = $string;

                if (!array_key_exists($key, $this->orphans)) {
                    $this->orphans[$key] = [];
                }

                $this->orphans[$key][] = $caller;

                $string = '??' . $string . '??';
            }
        }

        if ($jsSafe) {
            $string = addslashes($string);
        } elseif ($interpretBackSlashes) {
            if (str_contains($string, '\\')) {
                $string = str_replace(['\\\\', '\t', '\n'], ['\\', "\t", "\n"], $string);
            }
        }

        return $string;
    }

    /**
     * Транслитерационная функция.
     *
     * Этот метод обрабатывает строку и заменяет все акцентированные символы UTF-8 безударными «эквивалентами» ASCII-7.
     *
     * @param   string  $string  Строка для транслитерации.
     *
     * @return  string  Транслитерация строки.
     * @throws  \RuntimeException
     */
    public function transliterate(string $string): string {
        $string = $this->localise->transliterate($string);

        if ($string === false) {
            throw new \RuntimeException('В строке обнаружен недопустимый код UTF-8 "%s"', $string);
        }

        return $string;
    }

    /**
     * Возвращает массив суффиксов для правил множественного числа.
     *
     * @param   integer  $count  Номер счета, для которого предназначено правило.
     *
     * @return  array  Массив суффиксов.
     */
    public function getPluralSuffixes(int $count): array {
        return $this->localise->getPluralSuffixes($count);
    }

    /**
     * Загружает один языковой файл и добавляет результаты к существующим строкам.
     *
     * @param   string   $extension  Расширение, для которого следует загрузить языковой файл.
     * @param   string   $basePath   Базовый путь, который нужно использовать.
     * @param   ?string  $lang       Язык для загрузки, значение по умолчанию для текущего языка равно нулю.
     * @param   boolean  $reload     Флаг, который заставит язык перезагрузиться, если установлено значение true.
     *
     * @return  boolean  True если файл успешно загрузился.
     */
    public function load(
        string $extension = 'flexis',
        string $basePath = '',
        ?string $lang = null,
        bool$reload = false
    ): bool {

        $lang     = $lang ?: $this->lang;
        $basePath = $basePath ?: $this->basePath;

        $path = $this->helper->getLanguagePath($basePath, $lang);

        $internal = $extension == 'flexis' || $extension == '';
        $filename = $internal ? $lang : $lang . '.' . $extension;
        $filename = "$path/$filename.ini";

        if (isset($this->paths[$extension][$filename]) && !$reload) {
            return $this->paths[$extension][$filename];
        }

        return $this->loadLanguage($filename, $extension);
    }

    /**
     * Загружает языковой файл.
     *
     * Этот метод не будет отмечать успешную загрузку файла — вместо этого используйте load().
     *
     * @param   string  $filename   Имя файла.
     * @param   string  $extension  Имя расширения.
     *
     * @return  boolean  True если в язык были добавлены новые строки.
     *
     * @see     Language::load()
     */
    protected function loadLanguage(string $filename, string $extension = 'unknown'): bool {
        $this->counter++;

        $result  = false;
        $strings = false;

        if (file_exists($filename)) {
            $strings = $this->parse($filename);
        }

        if ($strings) {
            if (\count($strings)) {
                $this->catalogue->addMessages(array_replace($strings, $this->override));
                $result = true;
            }
        }

        if (!isset($this->paths[$extension])) {
            $this->paths[$extension] = [];
        }

        $this->paths[$extension][$filename] = $result;

        return $result;
    }

    /**
     * Анализирует языковой файл.
     *
     * @param   string  $filename  Имя файла.
     *
     * @return  array  Массив анализируемых строк.
     */
    protected function parse(string $filename): array {
        if ($this->debug) {
            // See https://www.php.net/manual/ru/reserved.variables.phperrormsg.php
            $php_errormsg = null;
            $trackErrors  = ini_get('track_errors');
            ini_set('track_errors', true);
        }

        try {
            $strings = $this->parserRegistry->get(pathinfo($filename, PATHINFO_EXTENSION))->loadFile($filename);
        } catch (\RuntimeException $exception) {
            $strings = [];
        }

        if ($this->debug) {
            ini_set('track_errors', $trackErrors);

            $this->debugFile($filename);
        }

        return $strings;
    }

    /**
     * Отлаживает языковой файл
     *
     * @param   string  $filename  Абсолютный путь к файлу для отладки
     *
     * @return  integer  Подсчет количества ошибок синтаксического анализа
     */
    public function debugFile(string $filename): int {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException(
                sprintf('Невозможно найти файл «%s» для отладки.', $filename)
            );
        }

        $debug        = $this->setDebug(false);
        $php_errormsg = null;

        $parser = $this->parserRegistry->get(pathinfo($filename, PATHINFO_EXTENSION));

        if (!($parser instanceof DebugParserInterface)) {
            return 0;
        }

        $errors = $parser->debugFile($filename);

        if (\count($errors)) {
            $this->errorfiles[$filename] = $filename . ' - error(s) ' . implode(', ', $errors);
        } elseif ($php_errormsg) {
            $this->errorfiles['PHP' . $filename] = 'PHP parser errors -' . $php_errormsg;
        }

        $this->setDebug($debug);

        return \count($errors);
    }

    /**
     * Возвращает свойство языка метаданных.
     *
     * @param   string  $property  Название объекта недвижимости.
     * @param   mixed   $default   Значение по умолчанию.
     *
     * @return  mixed  Стоимость недвижимости.
     */
    public function get(string $property, mixed $default = null): mixed {
        return $this->metadata[$property] ?? $default;
    }

    /**
     * Возвращает базовый путь к экземпляру.
     *
     * @return  string
     */
    public function getBasePath(): string {
        return $this->basePath;
    }

    /**
     * Определяет, кто вызвал Язык или Текст.
     *
     * @return  array|null  Информация о вызывающем или значение null, если оно недоступно.
     */
    protected function getCallerInfo(): ?array {
        if (!\function_exists('debug_backtrace')) {
            return null;
        }

        $backtrace = debug_backtrace();
        $info      = [];
        $continue  = true;

        while ($continue && next($backtrace)) {
            $step  = current($backtrace);
            $class = @ $step['class'];

            // Ищем за пределами language.php
            if ($class != __CLASS__ && $class != Text::class) {
                $info['function'] = @ $step['function'];
                $info['class']    = $class;
                $info['step']     = prev($backtrace);
                // Определяем файл и имя файла
                $info['file'] = @ $step['file'];
                $info['line'] = @ $step['line'];

                $continue = false;
            }
        }

        return $info;
    }

    /**
     * Возвращает название элемента языка.
     *
     * @return  string  Официальное название элемента языка.
     */
    public function getName(): string {
        return $this->metadata['name'];
    }

    /**
     * Возвращает список языковых файлов, которые были загружены.
     *
     * @param   ?string  $extension  Необязательное имя расширения.
     *
     * @return  array|null
     */
    public function getPaths(string $extension = null): ?array {
        if (isset($extension)) {
            return $this->paths[$extension] ?? null;
        }

        return $this->paths;
    }

    /**
     * Возвращает список языковых файлов, находящихся в состоянии ошибки.
     *
     * @return  array
     */
    public function getErrorFiles(): array {
        return $this->errorfiles;
    }

    /**
     * Возвращает языковой тег (как определено в RFC 3066).
     *
     * @return  string  Языковой тег.
     */
    public function getTag(): string {
        return $this->metadata['tag'];
    }

    /**
     * Возвращает свойство RTL.
     *
     * @return  boolean  True это язык RTL.
     */
    public function isRtl(): bool {
        return (bool) $this->metadata['rtl'];
    }

    /**
     * Устанавливает свойство Debug.
     *
     * @param   boolean  $debug  Настройка отладки.
     *
     * @return  boolean  Предыдущее значение.
     */
    public function setDebug(bool $debug): bool {
        $previous    = $this->debug;
        $this->debug = $debug;

        return $previous;
    }

    /**
     * Возвращает свойство Debug.
     *
     * @return  boolean  True находится в режиме отладки.
     */
    public function getDebug(): bool {
        return $this->debug;
    }

    /**
     * Возвращает код языка по умолчанию.
     *
     * @return  string  Код языка.
     */
    public function getDefault(): string {
        return $this->default;
    }

    /**
     * Устанавливает код языка по умолчанию.
     *
     * @param   string  $lang  Языковой код.
     *
     * @return  string  Предыдущее значение.
     */
    public function setDefault(string $lang): string {
        $previous      = $this->default;
        $this->default = $lang;

        return $previous;
    }

    /**
     * Возвращает список потерянных строк, если они отслеживаются.
     *
     * @return  array  Потерянный текст.
     */
    public function getOrphans(): array {
        return $this->orphans;
    }

    /**
     * Возвращает список используемых строк.
     *
     * Используемые строки — это строки, запрошенные и найденные в виде строки или константы.
     *
     * @return  array  Использованные струны.
     */
    public function getUsed(): array {
        return $this->used;
    }

    /**
     * Определяет, существует ли ключ.
     *
     * @param   string  $string  Ключ для проверки.
     *
     * @return  boolean  True, если ключ существует.
     */
    public function hasKey(string $string): bool {
        return $this->catalogue->hasMessage($string);
    }

    /**
     * Возвращает текущий код языка.
     *
     * @return  string  Код языка
     */
    public function getLanguage(): string {
        return $this->lang;
    }

    /**
     * Возвращает каталог сообщений для данного языка.
     *
     * @return  MessageCatalogue
     */
    public function getCatalogue(): MessageCatalogue {
        return $this->catalogue;
    }

    /**
     * Устанавливает каталог сообщений для языка.
     *
     * @param   MessageCatalogue  $catalogue  Каталог сообщений, который нужно использовать.
     *
     * @return  void
     */
    public function setCatalogue(MessageCatalogue $catalogue): void {
        $this->catalogue = $catalogue;
    }

    /**
     * Возвращает языковую локаль на основе текущего языка.
     *
     * @return  array  Локаль в соответствии с языком.
     */
    public function getLocale(): array {
        if (!isset($this->locale)) {
            $locale = str_replace(' ', '', $this->metadata['locale'] ?? '');

            $this->locale = $locale ? explode(',', $locale) : false;
        }

        return $this->locale;
    }

    /**
     * Возвращает первый день недели для этого языка.
     *
     * @return  integer  Первый день недели в зависимости от языка
     */
    public function getFirstDay(): int {
        return (int) ($this->metadata['firstDay'] ?? 1);
    }

    /**
     * Возвращает выходные дни для этого языка.
     *
     * @return  string  Выходные дни недели, разделенные запятой в зависимости от языка.
     */
    public function getWeekEnd(): string {
        return $this->metadata['weekEnd'] ?? '0,6';
    }
}
