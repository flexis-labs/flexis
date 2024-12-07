<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Password;

/**
 * Обработчик паролей для хешированных паролей BCrypt
 */
class BCryptHandler implements HandlerInterface {
    /**
     * Создать хэш для пароля в виде открытого текста
     *
     * @param   string  $plaintext  Открытый пароль для проверки
     * @param   array   $options    Варианты операции хеширования
     *
     * @return  string
     */
    public function hashPassword(string $plaintext, array $options = []): string {
        return password_hash($plaintext, \PASSWORD_BCRYPT, $options);
    }

    /**
     * Убедитесь, что обработчик паролей поддерживается в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool {
        return \function_exists('password_verify');
    }

    /**
     * Подтвердить пароль
     *
     * @param   string  $plaintext  Простой текстовый пароль для проверки
     * @param   string  $hashed     Хэш пароля для проверки
     *
     * @return  boolean
     */
    public function validatePassword(string $plaintext, string $hashed): bool {
        return password_verify($plaintext, $hashed);
    }
}
