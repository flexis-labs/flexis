<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Exception;

use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Исключение, указывающее на отсутствие пространства имен команды.
 */
class NamespaceNotFoundException extends CommandNotFoundException {}
