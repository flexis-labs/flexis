<?php

/**
 * Часть пакета Flexis Filter Framework.
 */

namespace Flexis\Filter;

use Flexis\String\StringHelper;

/**
 * InputFilter — класс для фильтрации входных данных из любого источника данных.
 */
class InputFilter {
    /**
     * Определяет, что экземпляр InputFilter должен разрешать только предоставленный список тегов HTML.
     *
     * @var    integer
     */
    public const int ONLY_ALLOW_DEFINED_TAGS = 0;

    /**
     * Определяет, что экземпляр InputFilter должен блокировать определенный список тегов HTML и разрешать все остальные.
     *
     * @var    integer
     */
    public const int ONLY_BLOCK_DEFINED_TAGS = 1;

    /**
     * Определяет, что экземпляр InputFilter должен разрешать только предоставленный список атрибутов.
     *
     * @var    integer
     */
    public const int ONLY_ALLOW_DEFINED_ATTRIBUTES = 0;

    /**
     * Определяет, что экземпляр InputFilter должен блокировать определенный список атрибутов и разрешать все остальные.
     *
     * @var    integer
     */
    public const int ONLY_BLOCK_DEFINED_ATTRIBUTES = 1;

    /**
     * Массив разрешенных тегов.
     *
     * @var    array
     */
    public array $tagsArray;

    /**
     * Массив разрешенных атрибутов тега.
     *
     * @var    array
     */
    public array $attrArray;

    /**
     * Метод очистки тегов.
     *
     * @var    integer
     */
    public int $tagsMethod;

    /**
     * Метод очистки атрибутов.
     *
     * @var    integer
     */
    public int $attrMethod;

    /**
     * Флаг для проверок XSS.
     * Только самое необходимое для автоматической очистки основных элементов = 0,
     * разрешить очистку заблокированных тегов/атрибутов = 1.
     *
     * @var    integer
     */
    public int $xssAuto;

    /**
     * Список заблокированных тегов для экземпляра.
     *
     * @var    string[]
     */
    public array $blockedTags = [
        'applet',
        'body',
        'bgsound',
        'base',
        'basefont',
        'canvas',
        'embed',
        'frame',
        'frameset',
        'head',
        'html',
        'id',
        'iframe',
        'ilayer',
        'layer',
        'link',
        'meta',
        'name',
        'object',
        'script',
        'style',
        'title',
        'xml',
    ];

    /**
     * Список заблокированных атрибутов тега для экземпляра.
     *
     * @var    string[]
     */
    public array $blockedAttributes = [
        'action',
        'background',
        'codebase',
        'dynsrc',
        'formaction',
        'lowsrc',
    ];

    /**
     * Специальный список заблокированных символов.
     *
     * @var    string[]
     */
    private array $blockedChars = [
        '&tab;',
        '&space;',
        '&colon;',
        '&column;',
    ];

    /**
     * Конструктор класса InputFilter.
     *
     * @param   array    $tagsArray   Список разрешенных HTML-тегов.
     * @param   array    $attrArray   Список разрешенных атрибутов HTML-тегов.
     * @param   integer  $tagsMethod  Метод фильтрации тегов должен быть одной из констант `ONLY_*_DEFINED_TAGS`.
     * @param   integer  $attrMethod  Метод фильтрации атрибутов должен быть одной из констант `ONLY_*_DEFINED_ATTRIBUTES`.
     * @param   integer  $xssAuto     Только автоматическая очистка основных элементов = 0, разрешить очистку заблокированных тегов/атрибутов = 1.
     */
    public function __construct(
        array $tagsArray = [],
        array $attrArray = [],
        int $tagsMethod = self::ONLY_ALLOW_DEFINED_TAGS,
        int $attrMethod = self::ONLY_ALLOW_DEFINED_ATTRIBUTES,
        int $xssAuto = 1
    ) {

        $tagsArray = array_map('strtolower', $tagsArray);
        $attrArray = array_map('strtolower', $attrArray);

        $this->tagsArray  = $tagsArray;
        $this->attrArray  = $attrArray;
        $this->tagsMethod = $tagsMethod;
        $this->attrMethod = $attrMethod;
        $this->xssAuto    = $xssAuto;
    }

    /**
     * Очищает данный источник входных данных на основе конфигурации экземпляра и указанного типа данных.
     *
     * @param   string|array|object     $source  Входная строка/массив строк/объект для «очистки».
     * @param   string                  $type    Тип возвращаемого значения переменной:
     *                                           INT:       Целое число.
     *                                           UINT:      Беззнаковое целое число.
     *                                           FLOAT:     Число с плавающей запятой.
     *                                           BOOLEAN:   Логическое значение.
     *                                           WORD:      Строка [ru/en], содержащая только буквы A-Z или А-ЯЁ или символы подчеркивания (без учета регистра).
     *                                           ALNUM:     Строка [ru/en], содержащая только цифры или A-Z или А-ЯЁ или от 0 до 9 (без учета регистра).
     *                                           CMD:       Строка [en], содержащая цифры или A-Z, 0–9, символы подчеркивания, точки или дефисы (без учета регистра).
     *                                           BASE64:    Строка [en], содержащая цифры или A-Z, 0–9, косую черту, плюс или равно (без учета регистра).
     *                                           STRING:    Полностью декодированная и очищенная строка (по умолчанию).
     *                                           HTML:      Продезинфицированная строка.
     *                                           ARRAY:     Массив.
     *                                           PATH:      Очищенный путь к файлу.
     *                                           TRIM:      Строка, обрезанная из обычных, неразрывных и многобайтовых пробелов.
     *                                           USERNAME:  Не использовать (используйте фильтр для конкретного приложения).
     *                                           RAW:       Необработанная строка возвращается без фильтрации.
     *                                           unknown:   Неизвестный фильтр будет действовать как STRING. Если входные данные представляют собой массив, он вернет массив полностью декодированных и очищенных строк.
     *
     * @return  mixed  «Очищенная» версия параметра $source.
     */
    public function clean(string|array|object $source, string $type = 'string'): mixed {
        $type = ucfirst(strtolower($type));

        if ($type === 'Array') {
            return (array) $source;
        }

        if ($type === 'Raw') {
            return $source;
        }

        if (\is_array($source)) {
            return array_map(function ($value) use ($type) {
                return $this->clean($value, $type);
            }, $source);
        }

        if (\is_object($source)) {
            foreach (get_object_vars($source) as $key => $value) {
                $source->$key = $this->clean($value, $type);
            }

            return $source;
        }

        $method = 'clean' . $type;

        if (method_exists($this, $method)) {
            return $this->$method((string) $source);
        }

        if (\is_string($source) && !empty($source)) {
            return $this->cleanString($source);
        }

        return $source;
    }

    /**
     * Функция определения безопасности содержимого атрибута.
     *
     * @param   array  $attrSubSet  Массив из двух элементов для имени и значения атрибута.
     *
     * @return  boolean  True если обнаружен плохой код.
     */
    public static function checkAttribute(array $attrSubSet): bool {
        $attrSubSet[0] = strtolower($attrSubSet[0]);
        $attrSubSet[1] = html_entity_decode(strtolower($attrSubSet[1]), ENT_QUOTES | ENT_HTML401, 'UTF-8');

        return (str_contains($attrSubSet[1], 'expression') && $attrSubSet[0] === 'style')
            || preg_match('/(?:(?:java|vb|live)script|behaviour|mocha)(?::|&colon;|&column;)/', $attrSubSet[1]) !== 0;
    }

    /**
     * Внутренний метод итеративного удаления всех нежелательных тегов и атрибутов.
     *
     * @param   string  $source  Входная строка для «очистки».
     *
     * @return  string  «Очищенная» версия входного параметра.
     */
    protected function remove(string $source): string {
        do {
            $temp   = $source;
            $source = $this->cleanTags($source);
        } while ($temp !== $source);

        return $source;
    }

    /**
     * Внутренний метод для удаления строки запрещенных тегов.
     *
     * @param   string  $source  Входная строка для «очистки».
     *
     * @return  string  «Очищенная» версия входного параметра.
     */
    protected function cleanTags(string $source): string {
        $source       = $this->escapeAttributeValues($source);
        $preTag       = null;
        $postTag      = $source;
        $currentSpace = false;
        $attr         = '';
        $tagOpenStart = strpos($source, '<');

        while ($tagOpenStart !== false) {
            $preTag .= substr($postTag, 0, $tagOpenStart);
            $postTag     = substr($postTag, $tagOpenStart);
            $fromTagOpen = substr($postTag, 1);
            $tagOpenEnd  = strpos($fromTagOpen, '>');

            $nextOpenTag = (strlen($postTag) > $tagOpenStart) ? strpos($postTag, '<', $tagOpenStart + 1) : false;

            if (($nextOpenTag !== false) && ($nextOpenTag < $tagOpenEnd)) {
                $postTag      = substr($postTag, 0, $tagOpenStart) . substr($postTag, $tagOpenStart + 1);
                $tagOpenStart = strpos($postTag, '<');

                continue;
            }

            if ($tagOpenEnd === false) {
                $postTag      = substr($postTag, $tagOpenStart + 1);
                $tagOpenStart = strpos($postTag, '<');

                continue;
            }

            $tagOpenNested = strpos($fromTagOpen, '<');

            if (($tagOpenNested !== false) && ($tagOpenNested < $tagOpenEnd)) {
                $preTag .= substr($postTag, 1, $tagOpenNested);
                $postTag      = substr($postTag, ($tagOpenNested + 1));
                $tagOpenStart = strpos($postTag, '<');

                continue;
            }

            $tagOpenNested = (strpos($fromTagOpen, '<') + $tagOpenStart + 1);
            $currentTag    = substr($fromTagOpen, 0, $tagOpenEnd);
            $tagLength     = strlen($currentTag);
            $tagLeft       = $currentTag;
            $attrSet       = [];
            $currentSpace  = strpos($tagLeft, ' ');

            if (str_starts_with($currentTag, '/')) {
                $isCloseTag    = true;
                list($tagName) = explode(' ', $currentTag);
                $tagName       = substr($tagName, 1);
            } else {
                $isCloseTag    = false;
                list($tagName) = explode(' ', $currentTag);
            }

            if (
                (!preg_match('/^[a-z][a-z0-9]*$/i', $tagName))
                || (!$tagName)
                || ((\in_array(strtolower($tagName), $this->blockedTags)) && $this->xssAuto)
            ) {
                $postTag      = substr($postTag, ($tagLength + 2));
                $tagOpenStart = strpos($postTag, '<');

                continue;
            }

            while ($currentSpace !== false) {
                $attr        = '';
                $fromSpace   = substr($tagLeft, ($currentSpace + 1));
                $nextEqual   = strpos($fromSpace, '=');
                $nextSpace   = strpos($fromSpace, ' ');
                $openQuotes  = strpos($fromSpace, '"');
                $closeQuotes = strpos(substr($fromSpace, ($openQuotes + 1)), '"') + $openQuotes + 1;

                $startAtt         = '';
                $startAttPosition = 0;

                if (preg_match('#\s*=\s*\"#', $fromSpace, $matches, \PREG_OFFSET_CAPTURE)) {
                    $stringBeforeAttr = substr($fromSpace, 0, $matches[0][1]);
                    $startAttPosition = strlen($stringBeforeAttr);
                    $startAtt         = $matches[0][0];
                    $closeQuotePos    = strpos(
                        substr($fromSpace, ($startAttPosition + strlen($startAtt))),
                        '"'
                    );
                    $closeQuotes = $closeQuotePos + $startAttPosition + strlen($startAtt);
                    $nextEqual   = $startAttPosition + strpos($startAtt, '=');
                    $openQuotes  = $startAttPosition + strpos($startAtt, '"');
                    $nextSpace   = strpos(substr($fromSpace, $closeQuotes), ' ') + $closeQuotes;
                }

                if ($fromSpace !== '/' && (($nextEqual && $nextSpace && $nextSpace < $nextEqual) || !$nextEqual)) {
                    if (!$nextEqual) {
                        $attribEnd = strpos($fromSpace, '/') - 1;
                    } else {
                        $attribEnd = $nextSpace - 1;
                    }

                    if ($attribEnd > 0) {
                        $fromSpace = substr($fromSpace, $attribEnd + 1);
                    }
                }

                if (str_contains($fromSpace, '=')) {
                    if (
                        ($openQuotes !== false)
                        && (str_contains(substr($fromSpace, ($openQuotes + 1)), '"'))
                    ) {
                        $attr = substr($fromSpace, 0, ($closeQuotes + 1));
                    } else {
                        $attr = substr($fromSpace, 0, $nextSpace);
                    }
                } else {
                    if ($fromSpace !== '/') {
                        $attr = substr($fromSpace, 0, $nextSpace);
                    }
                }

                if (!$attr && $fromSpace !== '/') {
                    $attr = $fromSpace;
                }

                $attrSet[]    = $attr;
                $tagLeft      = substr($fromSpace, strlen($attr));
                $currentSpace = strpos($tagLeft, ' ');
            }

            $tagFound = \in_array(strtolower($tagName), $this->tagsArray);

            if ((!$tagFound && $this->tagsMethod) || ($tagFound && !$this->tagsMethod)) {
                if (!$isCloseTag) {
                    $attrSet = $this->cleanAttributes($attrSet);
                    $preTag .= '<' . $tagName;

                    for ($i = 0, $count = \count($attrSet); $i < $count; $i++) {
                        $preTag .= ' ' . $attrSet[$i];
                    }

                    if (strpos($fromTagOpen, '</' . $tagName)) {
                        $preTag .= '>';
                    } else {
                        $preTag .= ' />';
                    }
                } else {
                    $preTag .= '</' . $tagName . '>';
                }
            }

            $postTag      = substr($postTag, ($tagLength + 2));
            $tagOpenStart = strpos($postTag, '<');
        }

        if ($postTag !== '<') {
            $preTag .= $postTag;
        }

        return $preTag;
    }

    /**
     * Внутренний метод удаления из тега недопустимых атрибутов.
     *
     * @param   array  $attrSet  Массив пар атрибутов для фильтрации.
     *
     * @return  array  Отфильтрованный массив пар атрибутов.
     */
    protected function cleanAttributes(array $attrSet): array {
        $newSet = [];

        $count = \count($attrSet);

        for ($i = 0; $i < $count; $i++) {
            if (!$attrSet[$i]) {
                continue;
            }

            $attrSubSet    = explode('=', trim($attrSet[$i]), 2);
            $attrSubSet0   = explode(' ', trim($attrSubSet[0]));
            $attrSubSet[0] = array_pop($attrSubSet0);
            $attrSubSet[0] = strtolower($attrSubSet[0]);
            $quoteStyle    = \ENT_QUOTES | \ENT_HTML401;
            $attrSubSet[0] = html_entity_decode($attrSubSet[0], $quoteStyle, 'UTF-8');
            $attrSubSet[0] = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $attrSubSet[0]);
            $attrSubSet[0] = preg_replace('/\s+/u', '', $attrSubSet[0]);

            foreach ($this->blockedChars as $blockedChar) {
                $attrSubSet[0] = str_ireplace($blockedChar, '', $attrSubSet[0]);
            }

            $attrSubSet[0] = preg_replace('/[^\p{L}\p{N}\-\s]/u', '', $attrSubSet[0]);

            if (
                (!preg_match('/[a-z]*$/i', $attrSubSet[0]))
                || ($this->xssAuto && ((\in_array(strtolower($attrSubSet[0]), $this->blockedAttributes))
                || str_starts_with($attrSubSet[0], 'on')))
            ) {
                continue;
            }

            if (!isset($attrSubSet[1])) {
                continue;
            }

            foreach ($this->blockedChars as $blockedChar) {
                $attrSubSet[1] = str_ireplace($blockedChar, '', $attrSubSet[1]);
            }

            $attrSubSet[1] = trim($attrSubSet[1]);
            $attrSubSet[1] = str_replace('&#', '', $attrSubSet[1]);
            $attrSubSet[1] = preg_replace('/[\n\r]/', '', $attrSubSet[1]);
            $attrSubSet[1] = str_replace('"', '', $attrSubSet[1]);

            if ((str_starts_with($attrSubSet[1], "'")) && (substr($attrSubSet[1], (\strlen($attrSubSet[1]) - 1), 1) == "'")) {
                $attrSubSet[1] = substr($attrSubSet[1], 1, (\strlen($attrSubSet[1]) - 2));
            }

            $attrSubSet[1] = stripslashes($attrSubSet[1]);

            if (static::checkAttribute($attrSubSet)) {
                continue;
            }

            $attrFound = \in_array(strtolower($attrSubSet[0]), $this->attrArray);

            if ((!$attrFound && $this->attrMethod) || ($attrFound && !$this->attrMethod)) {
                if (empty($attrSubSet[1]) === false) {
                    $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[1] . '"';
                } elseif ($attrSubSet[1] === '0') {
                    $newSet[] = $attrSubSet[0] . '="0"';
                } else {
                    $newSet[] = $attrSubSet[0] . '=""';
                }
            }
        }

        return $newSet;
    }

    /**
     * Попробуйте преобразовать в открытый текст.
     *
     * @param   string  $source  Исходная строка.
     *
     * @return  string  Открытая текстовая строка.
     */
    protected function decode(string $source): string {
        return html_entity_decode($source, \ENT_QUOTES, 'UTF-8');
    }

    /**
     * Безопасные < > и " внутри значений атрибутов.
     *
     * @param   string  $source  Исходная строка.
     *
     * @return  string  Отфильтрованная строка.
     */
    protected function escapeAttributeValues(string $source): string {
        $alreadyFiltered = '';
        $remainder       = $source;
        $badChars        = ['<', '"', '>'];
        $escapedChars    = ['&lt;', '&quot;', '&gt;'];

        while (preg_match('#<[^>]*?=\s*?(\"|\')#s', $remainder, $matches, \PREG_OFFSET_CAPTURE)) {
            $stringBeforeTag = substr($remainder, 0, $matches[0][1]);
            $tagPosition     = strlen($stringBeforeTag);
            $nextBefore      = $tagPosition + strlen($matches[0][0]);
            $quote           = substr($matches[0][0], -1);
            $pregMatch       = ($quote == '"') ? '#(\"\s*/\s*>|\"\s*>|\"\s+|\"$)#' : "#(\'\s*/\s*>|\'\s*>|\'\s+|\'$)#";

            $attributeValueRemainder = substr($remainder, $nextBefore);

            if (preg_match($pregMatch, $attributeValueRemainder, $matches, \PREG_OFFSET_CAPTURE)) {
                $stringBeforeQuote = substr($attributeValueRemainder, 0, $matches[0][1]);
                $closeQuoteChars   = strlen($stringBeforeQuote);
                $nextAfter         = $nextBefore + $closeQuoteChars;
            } else {
                $nextAfter = strlen($remainder);
            }

            $attributeValue = substr($remainder, $nextBefore, $nextAfter - $nextBefore);

            $attributeValue = str_replace($badChars, $escapedChars, $attributeValue);
            $attributeValue = $this->stripCssExpressions($attributeValue);
            $alreadyFiltered .= substr($remainder, 0, $nextBefore) . $attributeValue . $quote;
            $remainder = substr($remainder, $nextAfter + 1);
        }

        return $alreadyFiltered . $remainder;
    }

    /**
     * Удаляет выражения CSS в виде <property>:expression(...)
     *
     * @param   string  $source  Исходная строка.
     *
     * @return  string  Отфильтрованная строка.
     */
    protected function stripCssExpressions(string $source): string {
        $test = preg_replace('#\/\*.*\*\/#U', '', $source);

        if (!stripos($test, ':expression')) {
            return $source;
        }

        if (preg_match_all('#:expression\s*\(#', $test, $matches)) {
            return str_ireplace(':expression', '', $test);
        }

        return $source;
    }

    /**
     * Int - Целочисленный фильтр.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  integer  Отфильтрованное значение.
     */
    private function cleanInt(string $source): int {
        $pattern = '/[-+]?[0-9]+/';

        preg_match($pattern, $source, $matches);

        return isset($matches[0]) ? (int) $matches[0] : 0;
    }

    /**
     * Integer - Псевдоним для CleanIng().
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  integer  Отфильтрованное значение.
     */
    private function cleanInteger(string $source): int {
        return $this->cleanInt($source);
    }

    /**
     * Uint - Беззнаковый целочисленный фильтр.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  integer  Отфильтрованное значение.
     */
    private function cleanUint(string $source): int {
        $pattern = '/[-+]?[0-9]+/';

        preg_match($pattern, $source, $matches);

        return isset($matches[0]) ? abs((int) $matches[0]) : 0;
    }

    /**
     * Float - фильтр.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  float  Отфильтрованное значение.
     */
    private function cleanFloat(string $source): float {
        $pattern = '/[-+]?[0-9]+(\.[0-9]+)?([eE][-+]?[0-9]+)?/';

        preg_match($pattern, $source, $matches);

        return isset($matches[0]) ? (float) $matches[0] : 0.0;
    }

    /**
     * Double - Псевдоним для cleanFloat().
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  float  Отфильтрованное значение.
     */
    private function cleanDouble(string $source): float {
        return $this->cleanFloat($source);
    }

    /**
     * Boolean - фильтр.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  boolean  Отфильтрованное значение.
     */
    private function cleanBool(string $source): bool {
        return (bool) $source;
    }

    /**
     * Псевдоним для cleanBool().
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  boolean  Отфильтрованное значение.
     */
    private function cleanBoolean(string $source): bool
    {
        return $this->cleanBool($source);
    }

    /**
     * Word - фильтр. Без учёта регистра.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanWord(string $source): string {
        $pattern = '/[^A-ZА-ЯЁ_]/i';

        return preg_replace($pattern, '', $source);
    }

    /**
     * Alnum - Буквенно-цифровой фильтр. Без учёта регистра.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanAlnum(string $source): string {
        $pattern = '/[^A-ZА-ЯЁ0-9]/i';

        return preg_replace($pattern, '', $source);
    }

    /**
     * Cmd - Фильтр команд. Без учёта регистра.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanCmd(string $source): string {
        $pattern = '/[^A-Z0-9_\.-]/i';

        $result = preg_replace($pattern, '', $source);
        $result = ltrim($result, '.');

        return $result;
    }

    /**
     * Base64 - Фильтр. Без учёта регистра.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanBase64(string $source): string {
        $pattern = '/[^A-Z0-9\/+=]/i';

        return preg_replace($pattern, '', $source);
    }

    /**
     * String - Строковый фильтр.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanString(string $source): string {
        return $this->remove($this->decode($source));
    }

    /**
     * HTML - фильтр.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanHtml(string $source): string {
        return $this->remove($source);
    }

    /**
     * Path - Фильтр пути.
     *
     * @param   string  $source  Строка для фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanPath(string $source): string {
        $linuxPattern = '/^[A-Za-zА-Яа-яЁё0-9_\/-]+[A-Za-zА-Яа-яЁё0-9_\.-]*([\\\\\/]+[A-Za-zА-Яа-яЁё0-9_-]+[A-Za-zА-Яа-яЁё0-9_\.-]*)*$/';

        if (preg_match($linuxPattern, $source)) {
            return preg_replace('~/+~', '/', $source);
        }

        $windowsPattern = '/^([A-Za-zА-Яа-яЁё]:(\\\\|\/))?[A-Za-zА-Яа-яЁё0-9_-]+[A-Za-zА-Яа-яЁё0-9_\.-]*((\\\\|\/)+[A-Za-zА-Яа-яЁё0-9_-]+[A-Za-zА-Яа-яЁё0-9_\.-]*)*$/';

        if (preg_match($windowsPattern, $source)) {
            return preg_replace('~(\\\\|\/)+~', '\\', $source);
        }

        return '';
    }

    /**
     * Trim - Обрезной фильтр.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanTrim(string $source): string {
        $result = trim($source);
        $result = StringHelper::trim($result, \chr(0xE3) . \chr(0x80) . \chr(0x80));

        return StringHelper::trim($result, \chr(0xC2) . \chr(0xA0));
    }

    /**
     * Username - Фильтр имени пользователя.
     *
     * @param   string  $source  Строка, подлежащая фильтрации.
     *
     * @return  string  Отфильтрованная строка.
     */
    private function cleanUsername(string $source): string {
        $pattern = '/[\x00-\x1F\x7F<>"\'%&]/';

        return preg_replace($pattern, '', $source);
    }
}
