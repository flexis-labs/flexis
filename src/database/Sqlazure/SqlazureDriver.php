<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlazure;

use Flexis\Database\Sqlsrv\SqlsrvDriver;

/**
 * Драйвер базы данных SQL Azure
 *
 * @link   https://msdn.microsoft.com/en-us/library/ee336279.aspx
 */
class SqlazureDriver extends SqlsrvDriver {
    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'sqlazure';
}
