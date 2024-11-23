<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Cipher;

use Flexis\Crypt\CipherInterface;
use Flexis\Crypt\Exception\DecryptionException;
use Flexis\Crypt\Exception\EncryptionException;
use Flexis\Crypt\Exception\InvalidKeyException;
use Flexis\Crypt\Exception\InvalidKeyTypeException;
use Flexis\Crypt\Exception\UnsupportedCipherException;
use Flexis\Crypt\Key;
use ParagonIE\Sodium\Compat;

/**
 * Шифр для шифрования/дешифрования и генерации ключей с помощью Sodium алгоритма.
 */
class Sodium implements CipherInterface {
    /**
     * Сообщение nonce, которое будет использоваться при шифровании/дешифровании.
     *
     * @var    string
     */
    private string $nonce;

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
        if ($key->getType() !== 'sodium') {
            throw new InvalidKeyTypeException('sodium', $key->getType());
        }

        if (!$this->nonce) {
            throw new DecryptionException('Отсутствует nonce для расшифровки данных');
        }

        if (\function_exists('sodium_crypto_box_open')) {
            try {
                $decrypted = sodium_crypto_box_open(
                    $data,
                    $this->nonce,
                    sodium_crypto_box_keypair_from_secretkey_and_publickey($key->getPrivate(), $key->getPublic())
                );

                if ($decrypted === false) {
                    throw new DecryptionException('Неверное сообщение или неверный MAC.');
                }
            } catch (\SodiumException $exception) {
                throw new DecryptionException('Неверное сообщение или неверный MAC.', $exception->getCode(), $exception);
            }

            return $decrypted;
        }

        if (\extension_loaded('libsodium')) {
            $decrypted = \Sodium\crypto_box_open(
                $data,
                $this->nonce,
                \Sodium\crypto_box_keypair_from_secretkey_and_publickey($key->getPrivate(), $key->getPublic())
            );

            if ($decrypted === false) {
                throw new DecryptionException('Неверное сообщение или недействительное MAC');
            }

            return $decrypted;
        }

        throw new UnsupportedCipherException(static::class);
    }

    /**
     * Метод шифрования строки данных.
     *
     * @param   string  $data  Строка данных для шифрования.
     * @param   Key     $key   Ключевой объект, используемый для шифрования.
     *
     * @return  string  Зашифрованная строка данных.
     *
     * @throws  EncryptionException если данные не могут быть зашифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     */
    public function encrypt(string $data, Key $key): string {
        if ($key->getType() !== 'sodium') {
            throw new InvalidKeyTypeException('sodium', $key->getType());
        }

        if (!$this->nonce) {
            throw new EncryptionException('Отсутствует nonce для расшифровки данных');
        }

        if (\function_exists('sodium_crypto_box')) {
            try {
                return sodium_crypto_box(
                    $data,
                    $this->nonce,
                    sodium_crypto_box_keypair_from_secretkey_and_publickey($key->getPrivate(), $key->getPublic())
                );
            } catch (\SodiumException $exception) {
                throw new EncryptionException('Не удалось зашифровать файл.', $exception->getCode(), $exception);
            }
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_box(
                $data,
                $this->nonce,
                \Sodium\crypto_box_keypair_from_secretkey_and_publickey($key->getPrivate(), $key->getPublic())
            );
        }

        throw new UnsupportedCipherException(static::class);
    }

    /**
     * Метод для создания нового объекта ключа шифрования.
     *
     * @param   array  $options  Варианты генерации ключей.
     *
     * @return  Key
     *
     * @throws  InvalidKeyException если ключ не может быть сгенерирован
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     */
    public function generateKey(array $options = []): Key {
        if (\function_exists('sodium_crypto_box_keypair')) {
            try {
                $pair = sodium_crypto_box_keypair();

                return new Key('sodium', sodium_crypto_box_secretkey($pair), sodium_crypto_box_publickey($pair));
            } catch (\SodiumException $exception) {
                throw new InvalidKeyException('Не удалось сгенерировать ключ шифрования.', $exception->getCode(), $exception);
            }
        }

        if (\extension_loaded('libsodium')) {
            $pair = \Sodium\crypto_box_keypair();

            return new Key('sodium', \Sodium\crypto_box_secretkey($pair), \Sodium\crypto_box_publickey($pair));
        }

        throw new UnsupportedCipherException(static::class);
    }

    /**
     * Проверяет, поддерживается ли шифр в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool {
        return \function_exists('sodium_crypto_box') || \extension_loaded('libsodium') || class_exists(Compat::class);
    }

    /**
     * Устанавливает nonce, который будет использоваться для шифрования/дешифрования сообщений.
     *
     * @param   string  $nonce  Сообщение nonce.
     *
     * @return  void
     */
    public function setNonce(string $nonce): void {
        $this->nonce = $nonce;
    }
}
