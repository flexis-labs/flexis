<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry\Format;

use Flexis\Registry\FormatInterface;
use SimpleXMLElement;

/**
 * Обработчик формата XML для реестра.
 */
class Xml implements FormatInterface {
    /**
     * Преобразует объект в строку формата XML.
     *
     * @param  object  $object   Объект источника данных.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  string  Строка в формате XML.
     */
    public function objectToString(object $object, array $options = []): string {
        $rootName = $options['name'] ?? 'registry';
        $nodeName = $options['nodeName'] ?? 'node';
        // Создаём корневой узел.
        $root = \simplexml_load_string('<' . $rootName . ' />');

        // Перебираем элементы объекта.
        $this->getXmlChildren($root, $object, $nodeName);

        return $root->asXML();
    }

    /**
     * Разберите строку в формате XML и преобразуйте её в объект.
     *
     * @param string $data     Строка в формате XML для преобразования.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  object   Объект данных.
     */
    public function stringToObject(string $data, array $options = []): object {
        $obj = new \stdClass();
        // Разбираем XML-строку.
        $xml = \simplexml_load_string($data);

        foreach ($xml->children() as $node) {
            $obj->{$node['name']} = $this->getValueFromNode($node);
        }

        return $obj;
    }

    /**
     * Метод для получения собственного значения PHP для объекта SimpleXMLElement. --вызывается рекурсивно
     *
     * @param SimpleXMLElement $node  Объект SimpleXMLElement, для которого нужно получить собственное значение.
     *
     * @return  mixed  Собственное значение объекта SimpleXMLElement.
     */
    protected function getValueFromNode(SimpleXMLElement $node): mixed {
        switch ($node['type']) {
            case 'integer':
                $value = (string) $node;

                return (int) $value;

            case 'string':
                return (string) $node;

            case 'boolean':
                $value = (string) $node;

                return (bool) $value;

            case 'double':
                $value = (string) $node;

                return (float) $value;

            case 'array':
                $value = [];

                foreach ($node->children() as $child) {
                    $value[(string) $child['name']] = $this->getValueFromNode($child);
                }

                break;

            default:
                $value = new \stdClass();

                foreach ($node->children() as $child) {
                    $value->{$child['name']} = $this->getValueFromNode($child);
                }

                break;
        }

        return $value;
    }

    /**
     * Метод построения уровня строки XML, вызываемый рекурсивно.
     *
     * @param  SimpleXMLElement  $node      Объект SimpleXMLElement для присоединения дочерних элементов.
     * @param  object            $var       Объект, представляющий узел XML-документа.
     * @param  string            $nodeName  Имя, которое будет использоваться для элементов узла.
     *
     * @return  void
     */
    protected function getXmlChildren(SimpleXMLElement $node, object $var, string $nodeName): void {
        // Перебираем элементы объекта.
        foreach ((array) $var as $k => $v) {
            if (\is_scalar($v)) {
                $n = $node->addChild($nodeName, $v);
                $n->addAttribute('name', $k);
                $n->addAttribute('type', \gettype($v));
            } else {
                $n = $node->addChild($nodeName);
                $n->addAttribute('name', $k);
                $n->addAttribute('type', \gettype($v));

                $this->getXmlChildren($n, $v, $nodeName);
            }
        }
    }
}
