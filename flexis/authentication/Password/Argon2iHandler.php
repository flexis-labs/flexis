<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Password;

use Flexis\Authentication\Exception\UnsupportedPasswordHandlerException;
use SodiumException;

/**
 * Обработчик паролей для хешированных паролей Argon2i
 */
class Argon2iHandler implements HandlerInterface {
    /**
     * Создать хэш для пароля в виде открытого текста
     *
     * @param   string  $plaintext  Открытый пароль для проверки
     * @param   array   $options    Варианты операции хеширования
     *
     * @return  string
     *
     * @throws  UnsupportedPasswordHandlerException если обработчик паролей не поддерживается
     * @throws  SodiumException
     */
    public function hashPassword(string $plaintext, array $options = []): string {
        if (\defined('PASSWORD_ARGON2I')) {
            return password_hash($plaintext, \PASSWORD_ARGON2I, $options);
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            $hash = sodium_crypto_pwhash_str(
                $plaintext,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
            sodium_memzero($plaintext);

            return $hash;
        }

        if (\extension_loaded('libsodium')) {
            $hash = \Sodium\crypto_pwhash_str(
                $plaintext,
                \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
            \Sodium\memzero($plaintext);

            return $hash;
        }

        throw new UnsupportedPasswordHandlerException('Алгоритм Argon2i не поддерживается.');
    }

    /**
     * Убедитесь, что обработчик паролей поддерживается в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool {
        if (\defined('PASSWORD_ARGON2I')) {
            return true;
        }

        if (class_exists('\\ParagonIE_Sodium_Compat') && method_exists('\\ParagonIE_Sodium_Compat', 'crypto_pwhash_is_available')) {
            return \ParagonIE_Sodium_Compat::crypto_pwhash_is_available();
        }

        return \function_exists('sodium_crypto_pwhash_str') || \extension_loaded('libsodium');
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
     * @throws  SodiumException
     */
    public function validatePassword(string $plaintext, string $hashed): bool {
        if (\defined('PASSWORD_ARGON2I')) {
            return password_verify($plaintext, $hashed);
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            $valid = sodium_crypto_pwhash_str_verify($hashed, $plaintext);
            sodium_memzero($plaintext);

            return $valid;
        }

        if (\extension_loaded('libsodium')) {
            $valid = \Sodium\crypto_pwhash_str_verify($hashed, $plaintext);
            \Sodium\memzero($plaintext);

            return $valid;
        }

        throw new UnsupportedPasswordHandlerException('Алгоритм Argon2i не поддерживается.');
    }
}
