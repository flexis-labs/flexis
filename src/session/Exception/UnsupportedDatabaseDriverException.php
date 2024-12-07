<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Exception;

/**
 * Исключение выдается, когда драйвер базы данных не поддерживается.
 */
class UnsupportedDatabaseDriverException extends \UnexpectedValueException {}
