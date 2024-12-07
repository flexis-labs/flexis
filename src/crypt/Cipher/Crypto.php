<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Cipher;

use Defuse\Crypto\Crypto as DefuseCrypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key as DefuseKey;
use Defuse\Crypto\RuntimeTests;
use Flexis\Crypt\CipherInterface;
use Flexis\Crypt\Exception\DecryptionException;
use Flexis\Crypt\Exception\EncryptionException;
use Flexis\Crypt\Exception\InvalidKeyException;
use Flexis\Crypt\Exception\InvalidKeyTypeException;
use Flexis\Crypt\Key;

/**
 * Flexis шифр для шифрования, дешифрования и генерации ключей с помощью библиотеки php-шифрования.
 */
class Crypto implements CipherInterface {
    /**
     * Метод расшифровки строки данных.
     *
     * @param   string  $data  Зашифрованная строка для расшифровки.
     * @param   Key     $key   Ключевой объект, используемый для расшифровки.
     *
     * @return  string  Расшифрованная строка данных.
     *
     * @throws  DecryptionException если данные не могут быть расшифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     */
    public function decrypt(string $data, Key $key): string {
        // Validate key.
        if ($key->getType() !== 'crypto') {
            throw new InvalidKeyTypeException('crypto', $key->getType());
        }

        try {
            return DefuseCrypto::decrypt($data, DefuseKey::loadFromAsciiSafeString($key->getPrivate()));
        } catch (WrongKeyOrModifiedCiphertextException $ex) {
            throw new DecryptionException('ОПАСНОСТЬ! ОПАСНОСТЬ! Зашифрованный текст был подделан!', $ex->getCode(), $ex);
        } catch (EnvironmentIsBrokenException $ex) {
            throw new DecryptionException('Невозможно безопасно выполнить расшифровку', $ex->getCode(), $ex);
        } catch (BadFormatException $ex) {
            throw new DecryptionException('Исключение неправильного формата', $ex->getCode(), $ex);
        }
    }

    /**
     * Метод шифрования строки данных.
     *
     * @param   string  $data  Строка данных для шифрования.
     * @param   Key     $key   Ключевой объект, используемый для шифрования.
     *
     * @return  string  Зашифрованная строка данных.
     *
     * @throws  EncryptionException если данные не могут быть зашифрованы.
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра.
     * @throws  BadFormatException
     */
    public function encrypt(string $data, Key $key): string {
        if ($key->getType() !== 'crypto') {
            throw new InvalidKeyTypeException('crypto', $key->getType());
        }

        try {
            return DefuseCrypto::encrypt($data, DefuseKey::loadFromAsciiSafeString($key->getPrivate()));
        } catch (EnvironmentIsBrokenException $ex) {
            throw new EncryptionException('Невозможно безопасно выполнить шифрование', $ex->getCode(), $ex);
        }
    }

    /**
     * Метод для создания нового объекта ключа шифрования.
     *
     * @param   array  $options  Варианты генерации ключей.
     *
     * @return  Key
     *
     * @throws  InvalidKeyException если ключ не может быть сгенерирован
     * @throws  EnvironmentIsBrokenException
     */
    public function generateKey(array $options = []): Key {
        try {
            $public = DefuseKey::createNewRandomKey();
        } catch (EnvironmentIsBrokenException $ex) {
            throw new InvalidKeyException('Невозможно безопасно создать ключ', $ex->getCode(), $ex);
        }

        return new Key('crypto', $public->saveToAsciiSafeString(), $public->getRawBytes());
    }

    /**
     * Проверяет, поддерживается ли шифр в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool {
        try {
            RuntimeTests::runtimeTest();
            return true;
        } catch (EnvironmentIsBrokenException $e) {
            return false;
        }
    }
}
