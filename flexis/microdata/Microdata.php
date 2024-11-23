<?php

/**
 * Часть пакета Flexis Microdata Framework.
 */

namespace Flexis\Microdata;

/**
 * Класс Flexis Framework для взаимодействия с семантикой микроданных.
 */
class Microdata {
    /**
     * Массив со всеми доступными типами и свойствами из словаря http://schema.org.
     *
     * @var    array|null
     */
    protected static ?array $types = null;

    /**
     * Тип микроданных.
     *
     * @var    string|null
     */
    protected ?string $type = null;

    /**
     * Свойства.
     *
     * @var    string|null
     */
    protected ?string $property = null;

    /**
     * Человеческий контент.
     *
     * @var    string|null
     */
    protected ?string $content = null;

    /**
     * Машинный контент.
     *
     * @var    string|null
     */
    protected ?string $machineContent = null;

    /**
     * Вспомогательный тип.
     *
     * @var    string|null
     */
    protected ?string $fallbackType = null;

    /**
     * Вспомогательное свойство.
     *
     * @var    string|null
     */
    protected ?string $fallbackProperty = null;

    /**
     * Используется для проверки того, включен или отключен вывод библиотеки.
     *
     * @var    boolean
     */
    protected bool $enabled = true;

    /**
     * Инициализация класса и установка `$type` по умолчанию.
     *
     * @param   string   $type  Необязательно, возврат к типу «Thing».
     * @param   boolean  $flag  Включить или отключить вывод библиотеки.
     */
    public function __construct(string $type = '', bool $flag = true) {
        if ($this->enabled = $flag) {
            if (!$type) {
                $type = 'Thing';
            }

            $this->setType($type);
        }
    }

    /**
     * Загружает все доступные типы и свойства из словаря http://schema.org, содержащегося в файле `types.json`.
     *
     * @return  void
     */
    protected static function loadTypes(): void {
        if (!static::$types) {
            $path          = __DIR__ . '/types.json';
            static::$types = json_decode(file_get_contents($path), true);
        }
    }

    /**
     * Сбрасывает все свойства класса.
     *
     * @return void
     */
    protected function resetParams(): void {
        $this->content          = null;
        $this->machineContent   = null;
        $this->property         = null;
        $this->fallbackProperty = null;
        $this->fallbackType     = null;
    }

    /**
     * Включить или отключить вывод библиотеки.
     *
     * @param   boolean  $flag  Включить или отключить вывод библиотеки.
     *
     * @return  Microdata  Экземпляр $this.
     */
    public function enable(bool $flag = true): self {
        $this->enabled = $flag;

        return $this;
    }

    /**
     * Возвращает «true», если вывод библиотеки включен.
     *
     * @return  boolean
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Устанавливает новый тип http://schema.org.
     *
     * @param   string  $type  Тип для настройки.
     *
     * @return  Microdata  Экземпляр $this.
     */
    public function setType(string $type): self {
        if (!$this->enabled) {
            return $this;
        }

        $this->type = static::sanitizeType($type);

        if (!static::isTypeAvailable($this->type)) {
            $this->type = 'Thing';
        }

        return $this;
    }

    /**
     * Возвращает текущий тип.
     *
     * @return  string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * Настройка свойств.
     *
     * @param   string  $property  Свойства.
     *
     * @return  Microdata  Экземпляр $this.
     */
    public function property(string $property): self {
        if (!$this->enabled) {
            return $this;
        }

        $property = static::sanitizeProperty($property);

        if (static::isPropertyInType($this->type, $property)) {
            $this->property = $property;
        }

        return $this;
    }

    /**
     * Возвращает текущее свойство.
     *
     * @return  string
     */
    public function getProperty(): string {
        return $this->property;
    }

    /**
     * Настраивает человеческий контент или контент для машин.
     *
     * @param   string|null  $content         Человеческий контент или машинный контент, который будет использоваться.
     * @param   string|null  $machineContent  Машинный контент.
     *
     * @return  Microdata  Экземпляр $this.
     */
    public function content(?string $content, ?string $machineContent = null): self {
        $this->content        = $content;
        $this->machineContent = $machineContent;

        return $this;
    }

    /**
     * Возвращает текущий $content.
     *
     * @return  string|null
     */
    public function getContent(): ?string {
        return $this->content;
    }

    /**
     * Возвращает текущий `$machineContent`.
     *
     * @return  string|null
     */
    public function getMachineContent(): ?string {
        return $this->machineContent;
    }

    /**
     * Настройка резервного типа и свойства.
     *
     * @param   string  $type      Вспомогательный тип.
     * @param   string  $property  Вспомогательное свойство.
     *
     * @return  Microdata  Экземпляр $this.
     */
    public function fallback(string $type, string $property): self {
        if (!$this->enabled) {
            return $this;
        }

        $this->fallbackType = static::sanitizeType($type);

        if (!static::isTypeAvailable($this->fallbackType)) {
            $this->fallbackType = 'Thing';
        }

        if (static::isPropertyInType($this->fallbackType, $property)) {
            $this->fallbackProperty = $property;
        } else {
            $this->fallbackProperty = null;
        }

        return $this;
    }

    /**
     * Возвращает текущий `$fallbackType`.
     *
     * @return  string|null
     */
    public function getFallbackType(): ?string {
        return $this->fallbackType;
    }

    /**
     * Возвращает текущий `$fallbackProperty`.
     *
     * @return  string|null
     */
    public function getFallbackProperty(): ?string {
        return $this->fallbackProperty;
    }

    /**
     * Эта функция управляет логикой отображения.
     *
     * Проверяет, доступны ли тип, свойство, если нет, проверяется наличие резервного варианта,
     * затем сбрасывает все параметры для следующего использования и возвращает HTML.
     *
     * @param   string   $displayType  Необязательный, `inline`, доступные варианты [`inline`|`span`|`div`|`meta`]
     * @param   boolean  $emptyOutput  Возвращает пустую строку, если вывод библиотеки отключен и имеется значение $content.
     *
     * @return  string
     */
    public function display(string $displayType = '', bool $emptyOutput = false): string {
        $html = (!is_null($this->content) && !$emptyOutput) ? $this->content : '';

        if (!$this->enabled) {
            $this->resetParams();

            return $html;
        }

        if ($this->property) {
            if ($displayType) {
                switch ($displayType) {
                    case 'span':
                        $html = static::htmlSpan($html, $this->property);
                        break;

                    case 'div':
                        $html = static::htmlDiv($html, $this->property);
                        break;

                    case 'meta':
                        $html = $this->machineContent ?? $html;
                        $html = static::htmlMeta($html, $this->property);
                        break;

                    default:
                        $html = static::htmlProperty($this->property);
                        break;
                }
            } else {
                switch (static::getExpectedDisplayType($this->type, $this->property)) {
                    case 'nested':
                        $nestedType     = static::getExpectedTypes($this->type, $this->property);
                        $nestedProperty = '';

                        if (\in_array($this->fallbackType, $nestedType)) {
                            $nestedType = $this->fallbackType;

                            if ($this->fallbackProperty) {
                                $nestedProperty = $this->fallbackProperty;
                            }
                        } else {
                            $nestedType = $nestedType[0];
                        }

                        if (!is_null($this->content)) {
                            if ($nestedProperty) {
                                $html = static::htmlSpan(
                                    $this->content,
                                    $nestedProperty
                                );
                            }

                            $html = static::htmlSpan(
                                $html,
                                $this->property,
                                $nestedType,
                                true
                            );
                        } else {
                            $html = static::htmlProperty($this->property) . ' ' . static::htmlScope($nestedType);

                            if ($nestedProperty) {
                                $html .= ' ' . static::htmlProperty($nestedProperty);
                            }
                        }

                        break;

                    case 'meta':
                        if (!is_null($this->content)) {
                            $html = $this->machineContent ?? $this->content;
                            $html = static::htmlMeta($html, $this->property) . $this->content;
                        } else {
                            $html = static::htmlProperty($this->property);
                        }

                        break;

                    default:
                        if (!is_null($this->content)) {
                            $html = static::htmlSpan($this->content, $this->property);
                        } else {
                            $html = static::htmlProperty($this->property);
                        }

                        break;
                }
            }
        } elseif ($this->fallbackProperty) {
            if ($displayType) {
                switch ($displayType) {
                    case 'span':
                        $html = static::htmlSpan($html, $this->fallbackProperty, $this->fallbackType);
                        break;

                    case 'div':
                        $html = static::htmlDiv($html, $this->fallbackProperty, $this->fallbackType);
                        break;

                    case 'meta':
                        $html = $this->machineContent ?? $html;
                        $html = static::htmlMeta($html, $this->fallbackProperty, $this->fallbackType);
                        break;

                    default:
                        $html = static::htmlScope($this->fallbackType) . ' ' . static::htmlProperty($this->fallbackProperty);
                        break;
                }
            } else {
                switch (static::getExpectedDisplayType($this->fallbackType, $this->fallbackProperty)) {
                    case 'meta':
                        if (!is_null($this->content)) {
                            $html = $this->machineContent ?? $this->content;
                            $html = static::htmlMeta($html, $this->fallbackProperty, $this->fallbackType);
                        } else {
                            $html = static::htmlScope($this->fallbackType) . ' ' . static::htmlProperty($this->fallbackProperty);
                        }

                        break;

                    default:
                        if (!is_null($this->content)) {
                            $html = static::htmlSpan($this->content, $this->fallbackProperty);
                            $html = static::htmlSpan($html, '', $this->fallbackType);
                        } else {
                            $html = static::htmlScope($this->fallbackType) . ' ' . static::htmlProperty($this->fallbackProperty);
                        }

                        break;
                }
            }
        } elseif (!is_null($this->fallbackProperty) && !is_null($this->fallbackType)) {
            $html = static::htmlScope($this->fallbackType);
        }

        $this->resetParams();

        return $html;
    }

    /**
     * Возвращает HTML текущей области.
     *
     * @return  string
     */
    public function displayScope(): string {
        if (!$this->enabled) {
            return '';
        }

        return static::htmlScope($this->type);
    }

    /**
     * Возвращает очищенный `$type`.
     *
     * @param   string  $type  Тип для очистки.
     *
     * @return  string
     */
    public static function sanitizeType(string $type): string {
        return ucfirst(trim($type));
    }

    /**
     * Возвращает очищенную `$property`.
     *
     * @param   string  $property  Объект, подлежащий очистке.
     *
     * @return  string
     */
    public static function sanitizeProperty(string $property): string {
        return lcfirst(trim($property));
    }

    /**
     * Возвращает массив со всеми доступными типами и свойствами из словаря http://schema.org.
     *
     * @return  array
     */
    public static function getTypes(): array {
        static::loadTypes();

        return static::$types;
    }

    /**
     * Возвращает массив со всеми доступными типами из словаря http://schema.org.
     *
     * @return  array
     */
    public static function getAvailableTypes(): array {
        static::loadTypes();

        return array_keys(static::$types);
    }

    /**
     * Возвращает ожидаемые типы данного свойства.
     *
     * @param   string  $type      Тип для обработки
     * @param   string  $property  Свойство для обработки
     *
     * @return  array
     */
    public static function getExpectedTypes(string $type, string $property): array {
        static::loadTypes();

        $tmp = static::$types[$type]['properties'];

        if (isset($tmp[$property])) {
            return $tmp[$property]['expectedTypes'];
        }

        $extendedType = static::$types[$type]['extends'];

        if (!empty($extendedType)) {
            return static::getExpectedTypes($extendedType, $property);
        }

        return [];
    }

    /**
     * Возвращает ожидаемый тип отображения: [`normal`|`nested`|`meta`]
     *
     * <pre>
     * Как отображаются свойства:
     * normal -> itemprop="name"
     * nested -> itemprop="director" itemscope itemtype="https://schema.org/Person"
     * meta   -> <meta itemprop="datePublished" content="1991-05-01">
     * </pre>
     *
     * @param   string  $type      Тип, где найти свойство.
     * @param   string  $property  Свойство для обработки.
     *
     * @return  string
     */
    protected static function getExpectedDisplayType(string $type, string $property): string {
        $expectedTypes = static::getExpectedTypes($type, $property);

        $type = $expectedTypes[0];

        if ($type === 'Date' || $type === 'DateTime' || $property === 'interactionCount') {
            return 'meta';
        }

        if ($type === 'Text' || $type === 'URL' || $type === 'Boolean' || $type === 'Number') {
            return 'normal';
        }

        return 'nested';
    }

    /**
     * Рекурсивная функция, контролирует, имеет ли данный тип заданное свойство.
     *
     * @param   string  $type      Тип, где проверить.
     * @param   string  $property  Свойство для проверки.
     *
     * @return  boolean
     */
    public static function isPropertyInType(string $type, string $property): bool {
        if (!static::isTypeAvailable($type)) {
            return false;
        }

        if (\array_key_exists($property, static::$types[$type]['properties'])) {
            return true;
        }

        $extendedType = static::$types[$type]['extends'];

        if (!empty($extendedType)) {
            return static::isPropertyInType($extendedType, $property);
        }

        return false;
    }

    /**
     * Проверяет, доступен ли данный класс типа.
     *
     * @param   string  $type  Тип для проверки.
     *
     * @return  boolean
     */
    public static function isTypeAvailable(string $type): bool {
        static::loadTypes();

        return \array_key_exists($type, static::$types);
    }

    /**
     * Возвращает семантику микроданных в теге `<meta>` с содержимым для компьютеров.
     *
     * @param   string   $content   Содержимое машины для отображения.
     * @param   string   $property  Свойство.
     * @param   string   $scope     Необязательно, область типа для отображения.
     * @param   boolean  $invert    Необязательный, default = false, инвертирует `$scope` с помощью `$property`.
     *
     * @return  string
     */
    public static function htmlMeta(
        string $content,
        string $property,
        string $scope = '',
        bool $invert = false
    ): string {
        return static::htmlTag('meta', $content, $property, $scope, $invert);
    }

    /**
     * Возвращает семантику микроданных в теге `<span>`.
     *
     * @param   string   $content   Человеческое содержание.
     * @param   string   $property  Необязательно, человеческий контент для отображения.
     * @param   string   $scope     Необязательно, область типа для отображения.
     * @param   boolean  $invert    Необязательный, default = false, инвертируйте `$scope` с помощью `$property`.
     *
     * @return  string
     */
    public static function htmlSpan(
        string $content,
        string $property = '',
        string $scope = '',
        bool $invert = false
    ): string {
        return static::htmlTag('span', $content, $property, $scope, $invert);
    }

    /**
     * Возвращает семантику микроданных в теге `<div>`.
     *
     * @param   string   $content   Человеческое содержание.
     * @param   string   $property  Необязательно, человеческий контент для отображения.
     * @param   string   $scope     Необязательно, область типа для отображения.
     * @param   boolean  $invert    Необязательный, default = false, инвертируйте `$scope` с помощью `$property`.
     *
     * @return  string
     */
    public static function htmlDiv(
        string $content,
        string $property = '',
        string $scope = '',
        bool $invert = false
    ): string {
        return static::htmlTag('div', $content, $property, $scope, $invert);
    }

    /**
     * Возвращает семантику микроданных в указанном теге.
     *
     * @param   string   $tag       HTML-тег.
     * @param   string   $content   Человеческое содержание.
     * @param   string   $property  Необязательно, человеческий контент для отображения.
     * @param   string   $scope     Необязательно, область типа для отображения.
     * @param   boolean  $invert    Необязательный, default = false, инвертируйте `$scope` с помощью `$property`.
     *
     * @return  string
     */
    public static function htmlTag(
        string $tag,
        string $content,
        string $property = '',
        string $scope = '',
        bool $invert = false
    ): string {
        if (!empty($property) && stripos($property, 'itemprop') !== 0) {
            $property = static::htmlProperty($property);
        }

        if (!empty($scope) && stripos($scope, 'itemscope') !== 0) {
            $scope = static::htmlScope($scope);
        }

        if ($invert) {
            $tmp = implode(' ', [$property, $scope]);
        } else {
            $tmp = implode(' ', [$scope, $property]);
        }

        $tmp = trim($tmp);
        $tmp = ($tmp) ? ' ' . $tmp : '';

        if ($tag === 'meta') {
            return "<meta$tmp content='$content'>";
        }

        return '<' . $tag . $tmp . '>' . $content . '</' . $tag . '>';
    }

    /**
     * Возвращает область HTML.
     *
     * @param   string  $scope  Сфера обработки.
     *
     * @return  string
     */
    public static function htmlScope(string $scope): string {
        return "itemscope itemtype='https://schema.org/" . static::sanitizeType($scope) . "'";
    }

    /**
     * Возвращает свойство HTML.
     *
     * @param   string  $property  Свойство для обработки.
     *
     * @return  string
     */
    public static function htmlProperty(string $property): string {
        return "itemprop='$property'";
    }
}
