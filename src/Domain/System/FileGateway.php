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
 * File Gateway
 *
 * @version v28
 * @since   v28
 */
class FileGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonFile';
    private static $primaryKey = 'gibbonFileID';

    /**
     * Record a file upload with metadata
     *
     * @param string $filePath Relative path from Gibbon root
     * @param string $fileName Original filename
     * @param string $fileExtension File extension
     * @param int $fileSize Size in bytes
     * @param string $mimeType MIME type
     * @param int $uploadedBy gibbonPersonID of uploader
     * @return int|false gibbonFileID on success, false on failure
     */
    public function recordFileUpload($filePath, $fileName, $fileExtension, $fileSize, $mimeType, $uploadedBy)
    {
        // Validate file exists at filePath
        if (!file_exists($filePath)) {
            return false;
        }

        // Calculate SHA-256 checksum
        $checksum = hash_file('sha256', $filePath);
        if ($checksum === false) {
            return false;
        }

        // Build data array with all parameters plus checksum and current timestamp
        $data = [
            'filePath' => $filePath,
            'fileName' => $fileName,
            'fileExtension' => $fileExtension,
            'fileSize' => $fileSize,
            'mimeType' => $mimeType,
            'uploadedBy' => $uploadedBy,
            'checksum' => $checksum
        ];

        // Insert record into gibbonFile table
        return $this->insert($data);
    }

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
        // Validate gibbonFileID exists in gibbonFile
        $fileRecord = $this->getByID($gibbonFileID);
        if (empty($fileRecord)) {
            return false;
        }

        // Build data array with all parameters plus current timestamp
        $data = [
            'gibbonFileID' => $gibbonFileID,
            'foreignTable' => $foreignTable,
            'foreignTableID' => $foreignTableID,
            'foreignColumn' => $foreignColumn
        ];

        // Use Gateway insert method to insert into gibbonFilePointer table
        $query = $this
            ->newInsert()
            ->into('gibbonFilePointer')
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * Record a file upload with pointer in a transaction
     *
     * @param string $filePath Relative path from Gibbon root
     * @param string $fileName Original filename
     * @param string $fileExtension File extension
     * @param int $fileSize Size in bytes
     * @param string $mimeType MIME type
     * @param int $uploadedBy gibbonPersonID of uploader
     * @param string $foreignTable Name of the foreign table
     * @param int $foreignTableID Primary key value in the foreign table
     * @param string $foreignColumn Column name storing the file path
     * @return array|false Array with gibbonFileID and gibbonFilePointerID on success, false on failure
     */
    public function recordFileUploadWithPointer($filePath, $fileName, $fileExtension, $fileSize, $mimeType, $uploadedBy, $foreignTable, $foreignTableID, $foreignColumn)
    {
        // Begin database transaction
        $this->db()->beginTransaction();

        try {
            // Call recordFileUpload and store gibbonFileID
            $gibbonFileID = $this->recordFileUpload($filePath, $fileName, $fileExtension, $fileSize, $mimeType, $uploadedBy);

            // If recordFileUpload fails, rollback and return false
            if ($gibbonFileID === false) {
                $this->db()->rollBack();
                return false;
            }

            // Call recordFilePointer with the gibbonFileID
            $gibbonFilePointerID = $this->recordFilePointer($gibbonFileID, $foreignTable, $foreignTableID, $foreignColumn);

            // If recordFilePointer fails, rollback transaction and return false
            if ($gibbonFilePointerID === false) {
                $this->db()->rollBack();
                return false;
            }

            // Both operations succeeded, commit the transaction
            $this->db()->commit();

            // Return array with both IDs
            return [
                'gibbonFileID' => $gibbonFileID,
                'gibbonFilePointerID' => $gibbonFilePointerID
            ];

        } catch (\Exception $e) {
            // Rollback on any exception
            $this->db()->rollBack();
            return false;
        }
    }
}
