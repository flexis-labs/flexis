<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt;

/**
 * Объект ключа шифрования для Flexis Framework.
 */
class Key {
    /**
     * Закрытый ключ.
     *
     * @var    string
     */
    private string $private;

    /**
     * Открытый ключ.
     *
     * @var    string
     */
    private string $public;

    /**
     * Тип ключа.
     *
     * @var    string
     */
    private string $type;

    /**
     * Конструктор.
     *
     * @param   string  $type     Тип ключа.
     * @param   string  $private  Закрытый ключ.
     * @param   string  $public   Открытый ключ.
     *
     */
    public function __construct(string $type, string $private, string $public) {
        $this->type    = $type;
        $this->private = $private;
        $this->public  = $public;
    }

    /**
     * Возвращает закрытый ключ
     *
     * @return  string
     */
    public function getPrivate(): string {
        return $this->private;
    }

    /**
     * Возвращает открытый ключ
     *
     * @return  string
     */
    public function getPublic(): string {
        return $this->public;
    }

    /**
     * Возвращает тип ключа
     *
     * @return  string
     */
    public function getType(): string {
        return $this->type;
    }
}
