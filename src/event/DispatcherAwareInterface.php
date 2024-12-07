<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Интерфейс должен быть реализован классами в зависимости от диспетчера.
 */
interface DispatcherAwareInterface {
    /**
     * Устанавливает диспетчер для использования.
     *
     * @param   DispatcherInterface  $dispatcher  Диспетчер для использования.
     *
     * @return  DispatcherAwareInterface  Этот метод является цепочечным.
     */
    public function setDispatcher(DispatcherInterface $dispatcher);
}
