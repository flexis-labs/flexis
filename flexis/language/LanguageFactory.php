<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Фабрика языковых пакетов
 */
class LanguageFactory {
    /**
     * Язык приложения по умолчанию
     *
     * @var    string
     */
    private string $defaultLanguage = 'ru-RU';

    /**
     * Путь к каталогу, содержащему языковую папку приложения.
     *
     * @var    string
     */
    private string $languageDirectory = '';

    /**
     * Возвращает язык приложения по умолчанию.
     *
     * @return  string
     */
    public function getDefaultLanguage(): string {
        return $this->defaultLanguage;
    }

    /**
     * Создаёт новый экземпляр языка на основе заданных параметров.
     *
     * @param   string   $lang   Язык, который нужно использовать.
     * @param   string   $path   Базовый путь к языковой папке.  Это необходимо при создании нового экземпляра.
     * @param   boolean  $debug  Режим отладки.
     *
     * @return  Language
     */
    public function getLanguage(string $lang = '', string $path = '', bool $debug = false): Language {
        $path = $path ?: $this->getLanguageDirectory();
        $lang = $lang ?: $this->getDefaultLanguage();

        $loaderRegistry = new ParserRegistry();
        $loaderRegistry->add(new Parser\IniParser());

        return new Language($loaderRegistry, $path, $lang, $debug);
    }

    /**
     * Возвращает путь к каталогу, содержащему языковую папку приложения.
     *
     * @return  string
     */
    public function getLanguageDirectory(): string {
        return $this->languageDirectory;
    }

    /**
     * Создаёт новый экземпляр LocaliseInterface для этого языка.
     *
     * @param   string  $lang      Язык для проверки.
     * @param   string  $basePath  Базовый путь к языковой папке.
     *
     * @return  LocaliseInterface
     */
    public function getLocalise(string $lang, string $basePath = ''): LocaliseInterface {
        $class = str_replace('-', '_', $lang . 'Localise');

        if (class_exists($class)) {
            return new $class();
        }

        $paths = [];
        $basePath = $basePath ?: $this->getLanguageDirectory();
        $basePath = (new LanguageHelper())->getLanguagePath($basePath);

        $paths[0] = $basePath . "/overrides/$lang.localise.php";
        $paths[1] = $basePath . "/$lang/$lang.localise.php";

        ksort($paths);
        $path = reset($paths);

        while (!class_exists($class) && $path) {
            if (file_exists($path)) {
                require_once $path;
            }

            $path = next($paths);
        }

        if (class_exists($class)) {
            return new $class();
        }

        return new Localise\Ru_RULocalise();
    }

    /**
     * Создаёт новый экземпляр StemmerInterface для запрошенного адаптера.
     *
     * @param   string  $adapter  Тип стеммера для загрузки.
     *
     * @return  StemmerInterface
     * @throws  \RuntimeException
     */
    public function getStemmer(string $adapter): StemmerInterface {
        $class = __NAMESPACE__ . '\\Stemmer\\' . ucfirst(trim($adapter));

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('Недействительный тип %s', $class));
        }

        return new $class();
    }

    /**
     * Возвращает новый объект Text для экземпляра языка.
     *
     * @param   Language|null  $language  Необязательный объект языка для внедрения, иначе загружается объект по умолчанию.
     *
     * @return  Text
     */
    public function getText(?Language $language = null): Text {
        $language = $language ?: $this->getLanguage();

        return new Text($language);
    }

    /**
     * Устанавливает язык приложения по умолчанию
     *
     * @param   string  $language  Код языка для языка приложения по умолчанию.
     *
     * @return  $this
     */
    public function setDefaultLanguage(string $language): self {
        $this->defaultLanguage = $language;

        return $this;
    }

    /**
     * Задаёт путь к каталогу, содержащему языковую папку приложения.
     *
     * @param   string  $directory  Путь к языковой папке приложения
     *
     * @return  $this
     */
    public function setLanguageDirectory(string $directory): self {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Невозможно установить языковой каталог на «%s», поскольку этот каталог не существует.',
                    $directory
                )
            );
        }

        $this->languageDirectory = $directory;

        return $this;
    }
}
