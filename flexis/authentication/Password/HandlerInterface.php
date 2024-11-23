<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Password;

/**
 * Интерфейс, определяющий обработчик пароля
 */
interface HandlerInterface {
    /**
     * Создать хэш для пароля в виде открытого текста
     *
     * @param   string  $plaintext  Открытый пароль для проверки
     * @param   array   $options    Варианты операции хеширования
     *
     * @return  string
     */
    public function hashPassword(string $plaintext, array $options = []): string;

    /**
     * Убедитесь, что обработчик паролей поддерживается в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool;

    /**
     * Подтвердить пароль
     *
     * @param   string  $plaintext  Простой текстовый пароль для проверки
     * @param   string  $hashed     Хэш пароля для проверки
     *
     * @return  boolean
     */
    public function validatePassword(string $plaintext, string $hashed): bool;
}
