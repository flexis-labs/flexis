<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Определяет признак для класса, поддерживающего работу с диспетчером.
 */
trait DispatcherAwareTrait {
    /**
     * Диспетчер событий.
     *
     * @var    DispatcherInterface|null
     */
    private ?DispatcherInterface $dispatcher = null;

    /**
     * Возвращает диспетчер событий.
     *
     * @return  DispatcherInterface
     *
     * @throws  \UnexpectedValueException Может быть выброшен, если диспетчер не был установлен.
     */
    public function getDispatcher(): DispatcherInterface {
        if ($this->dispatcher) {
            return $this->dispatcher;
        }

        throw new \UnexpectedValueException('Диспетчер не установлен в ' . __CLASS__);
    }

    /**
     * Устанавливает диспетчер для использования.
     *
     * @param   DispatcherInterface  $dispatcher  Диспетчер для использования.
     *
     * @return  $this
     *
     */
    public function setDispatcher(DispatcherInterface $dispatcher): static {
        $this->dispatcher = $dispatcher;

        return $this;
    }
}
