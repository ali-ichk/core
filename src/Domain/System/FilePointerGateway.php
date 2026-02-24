<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Domain\System;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * File Pointer Gateway
 *
 * @version v31
 * @since   v31
 */
class FilePointerGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonFilePointer';
    private static $primaryKey = 'gibbonFilePointerID';

    /**
     * Record a file pointer linking a file to a foreign table record
     *
     * @param int $gibbonFileID ID of the file in gibbonFile
     * @param string $foreignTable Name of the foreign table
     * @param int $foreignTableID Primary key value in the foreign table
     * @param string $foreignColumn Column name storing the file path
     * @return int|false gibbonFilePointerID on success, false on failure
     */
    public function recordFilePointer($gibbonFileID, $foreignTable, $foreignTableID, $foreignColumn)
    {
        // Build data array with all parameters
        $data = [
            'gibbonFileID' => $gibbonFileID,
            'foreignTable' => $foreignTable,
            'foreignTableID' => $foreignTableID,
            'foreignColumn' => $foreignColumn
        ];

        // Insert record into gibbonFilePointer table
        return $this->insert($data);
    }
}
