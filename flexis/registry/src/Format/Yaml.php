<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry\Format;

use Flexis\Registry\FormatInterface;
use Symfony\Component\Yaml\Dumper as SymfonyYamlDumper;
use Symfony\Component\Yaml\Parser as SymfonyYamlParser;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Обработчик формата YAML для реестра.
 */
class Yaml implements FormatInterface {
    /**
     * Класс анализатора YAML.
     *
     * @var    SymfonyYamlParser
     */
    private SymfonyYamlParser $parser;

    /**
     * Класс дампера YAML.
     *
     * @var    SymfonyYamlDumper
     */
    private SymfonyYamlDumper $dumper;

    /**
     * Конструкт для настройки парсера и дампера
     */
    public function __construct() {
        if (!\class_exists(SymfonyYaml::class)) {
            throw new \RuntimeException(
                \sprintf(
                    'Не удалось найти класс «%s», убедитесь, что у вас установлен пакет «symfony/yaml».',
                    SymfonyYaml::class
                )
            );
        }

        $this->parser = new SymfonyYamlParser();
        $this->dumper = new SymfonyYamlDumper();
    }

    /**
     * Преобразует объект в строку формата YAML.
     * Мы используем json преобразование переданного объекта в массив.
     *
     * @param object   $object   Объект источника данных.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  string  Строка в формате YAML.
     */
    public function objectToString(object $object, array $options = []): string {
        $array = \json_decode(\json_encode($object), true);
        return $this->dumper->dump($array, 2, 0);
    }

    /**
     * Разберите строку в формате YAML и преобразуйте ее в объект.
     * Мы используем методы json для преобразования проанализированного массива YAML в объект.
     *
     * @param string $data     Строка в формате YAML для преобразования.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  object  Объект данных.
     */
    public function stringToObject(string $data, array $options = []): object {
        $array = $this->parser->parse(\trim($data));
        return (object) \json_decode(\json_encode($array));
    }
}
