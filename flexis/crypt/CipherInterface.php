<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt;

use Flexis\Crypt\Exception\DecryptionException;
use Flexis\Crypt\Exception\EncryptionException;
use Flexis\Crypt\Exception\InvalidKeyException;
use Flexis\Crypt\Exception\InvalidKeyTypeException;
use Flexis\Crypt\Exception\UnsupportedCipherException;

/**
 * Интерфейс шифрования Flexis Framework.
 */
interface CipherInterface {
    /**
     * Метод расшифровки строки данных.
     *
     * @param   string  $data  Зашифрованная строка для расшифровки.
     * @param   Key     $key   Объект key[/pair], используемый для расшифровки.
     *
     * @return  string  Расшифрованная строка данных.
     *
     * @throws  DecryptionException если данные не могут быть расшифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     */
    public function decrypt(string $data, Key $key): string;

    /**
     * Метод шифрования строки данных.
     *
     * @param   string  $data  Строка данных для шифрования.
     * @param   Key     $key   Объект key[/pair], используемый для шифрования.
     *
     * @return  string  Зашифрованная строка данных.
     *
     * @throws  EncryptionException если данные не могут быть зашифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     */
    public function encrypt(string $data, Key $key): string;

    /**
     * Метод для создания нового объекта ключа шифрования[/pair].
     *
     * @param   array  $options  Варианты генерации ключей.
     *
     * @return  Key
     *
     * @throws  InvalidKeyException если ключ не может быть сгенерирован
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     */
    public function generateKey(array $options = []): Key;

    /**
     * Проверяет, поддерживается ли шифр в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool;
}
