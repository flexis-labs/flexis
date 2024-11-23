<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

/**
 * Flexis Framework Application Interface
 */
interface ApplicationInterface {
    /**
     * Способ закрытия приложения.
     *
     * @param   integer  $code  Код выхода (необязательно; по умолчанию — 0).
     *
     * @return  void
     */
    public function close(int $code = 0): void;

    /**
     * Выполняет приложение.
     *
     * @return  void
     */
    public function execute(): void;
}
