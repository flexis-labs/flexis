<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Helper;

use Flexis\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Описывает объект.
 */
class DescriptorHelper extends Helper {
    /**
     * Описывает объект, если он поддерживается.
     *
     * @param   OutputInterface  $output   Выходной объект для использования.
     * @param   object           $object   Объект для описания.
     * @param   array            $options  Опции дескриптора.
     *
     * @return  void
     */
    public function describe(OutputInterface $output, $object, array $options = []): void {
        (new TextDescriptor())->describe($output, $object, $options);
    }

    /**
     * Возвращает каноническое имя этого помощника.
     *
     * @return  string  Каноническое имя
     */
    public function getName(): string {
        return 'descriptor';
    }
}
