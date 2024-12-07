<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Password;

use Flexis\Authentication\Exception\UnsupportedPasswordHandlerException;

/**
 * Обработчик паролей для хешированных паролей Argon2id.
 */
class Argon2idHandler implements HandlerInterface {
    /**
     * Создать хэш для пароля в виде открытого текста
     *
     * @param   string  $plaintext  Открытый пароль для проверки
     * @param   array   $options    Варианты операции хеширования
     *
     * @return  string
     *
     * @throws  UnsupportedPasswordHandlerException если обработчик паролей не поддерживается
     */
    public function hashPassword(string $plaintext, array $options = []): string {
        if (version_compare(\PHP_VERSION, '7.3', '>=') && \defined('PASSWORD_ARGON2ID')) {
            return password_hash($plaintext, \PASSWORD_ARGON2ID, $options);
        }

        throw new UnsupportedPasswordHandlerException('Алгоритм Argon2id не поддерживается.');
    }

    /**
     * Убедитесь, что обработчик паролей поддерживается в этой среде.
     *
     * @return  boolean
     *
     */
    public static function isSupported(): bool {
        if (version_compare(\PHP_VERSION, '7.3', '>=') && \defined('PASSWORD_ARGON2ID')) {
            return true;
        }

        return false;
    }

    /**
     * Подтвердить пароль
     *
     * @param   string  $plaintext  Простой текстовый пароль для проверки
     * @param   string  $hashed     Хэш пароля для проверки
     *
     * @return  boolean
     *
     * @throws  UnsupportedPasswordHandlerException если обработчик паролей не поддерживается
     */
    public function validatePassword(string $plaintext, string $hashed): bool {
        if (version_compare(\PHP_VERSION, '7.3', '>=') && \defined('PASSWORD_ARGON2ID')) {
            return password_verify($plaintext, $hashed);
        }

        throw new UnsupportedPasswordHandlerException('Алгоритм Argon2id не поддерживается.');
    }
}
