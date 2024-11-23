<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive\Exception;

use Throwable;

/**
 * Класс исключений, определяющий неподдерживаемый адаптер архива.
 */
class UnsupportedArchiveException extends \InvalidArgumentException {
    /**
     * Неподдерживаемое имя адаптера архива.
     *
     * @var    string
     */
    protected string $adapterType = '';

    /**
     * Конструктор.
     *
     * @param   string              $adapterType  Неподдерживаемый тип адаптера.
     * @param   string              $message      Сообщение об исключении, которое нужно выдать.
     * @param   int                 $code         Код исключения.
     * @param   Throwable|null      $previous     Предыдущий объект throw, используемый для цепочки исключений.
     *
     */
    public function __construct(string $adapterType, string $message = '', int $code = 0, ?Throwable $previous = null) {
        $this->adapterType = $adapterType;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Возвращает имя типа адаптера, который не поддерживается.
     *
     * @return  string
     *
     */
    public function getUnsupportedAdapterType(): string {
        return $this->adapterType;
    }
}
