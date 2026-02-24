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
 * @version v31
 * @since   v31
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
     * @param int $gibbonPersonIDOwner - gibbonPersonID of uploader
     * @return int|false gibbonFileID on success, false on failure
     */
    public function recordFileUpload($filePath, $fileName, $fileExtension, $fileSize, $mimeType, $gibbonPersonIDOwner)
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
            'gibbonPersonIDOwner' => $gibbonPersonIDOwner,
            'checksum' => $checksum
        ];

        // Insert record into gibbonFile table
        return $this->insert($data);
    }

    /**
     * Record a file upload with pointer in a transaction
     *
     * @param FilePointerGateway $filePointerGateway Gateway for file pointer operations
     * @param string $filePath Relative path from Gibbon root
     * @param string $fileName Original filename
     * @param string $fileExtension File extension
     * @param int $fileSize Size in bytes
     * @param string $mimeType MIME type
     * @param int $gibbonPersonIDOwner gibbonPersonID of uploader
     * @param string $foreignTable Name of the foreign table
     * @param int $foreignTableID Primary key value in the foreign table
     * @param string $foreignColumn Column name storing the file path
     * @return array|false Array with gibbonFileID and gibbonFilePointerID on success, false on failure
     */
    public function recordFileUploadWithPointer(FilePointerGateway $filePointerGateway, $filePath, $fileName, $fileExtension, $fileSize, $mimeType, $gibbonPersonIDOwner, $foreignTable, $foreignTableID, $foreignColumn)
    {
        // Begin database transaction
        $this->db()->beginTransaction();

        // Call recordFileUpload and store gibbonFileID
        $gibbonFileID = $this->recordFileUpload($filePath, $fileName, $fileExtension, $fileSize, $mimeType, $gibbonPersonIDOwner);

        // If recordFileUpload fails, rollback and return false
        if (empty($gibbonFileID)) {
            $this->db()->rollBack();
            return false;
        }

        // Call recordFilePointer with the gibbonFileID
        $gibbonFilePointerID = $filePointerGateway->recordFilePointer($gibbonFileID, $foreignTable, $foreignTableID, $foreignColumn);

        // If recordFilePointer fails, rollback transaction and return false
        if (empty($gibbonFilePointerID)) {
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
    }

    /**
     * Query all files linked to a specific foreign table record
     *
     * @param string $foreignTable Name of the foreign table
     * @param int $foreignTableID Primary key value in the foreign table
     * @return Result Result object with all matching file records
     */
    public function selectFilesByForeignRecord($foreignTable, $foreignTableID)
    {
        $data = [
            'foreignTable' => $foreignTable,
            'foreignTableID' => $foreignTableID
        ];

        $sql = "SELECT gibbonFile.* FROM gibbonFile JOIN gibbonFilePointer ON (gibbonFile.gibbonFileID = gibbonFilePointer.gibbonFileID) WHERE gibbonFilePointer.foreignTable = :foreignTable AND gibbonFilePointer.foreignTableID = :foreignTableID ORDER BY gibbonFile.uploadedAt DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Query all file records where the file no longer exists on the filesystem
     *
     * @return DataSet DataSet object with orphaned file records (gibbonFileID, filePath, fileName, uploadedAt)
     */
    public function selectOrphanedFileRecords()
    {
        // Query all records from gibbonFile
        $sql = "SELECT gibbonFileID, filePath, fileName, uploadedAt FROM gibbonFile";

        $result = $this->db()->select($sql)->fetchAll();

        // Filter to records where file does not exist
        $orphanedRecords = [];
        foreach ($result as $record) {
            if (!file_exists($record['filePath'])) {
                $orphanedRecords[] = $record;
            }
        }

        // Return array with filtered records
        return $orphanedRecords;
    }

    /**
     * Verify file integrity by comparing stored checksum with recalculated checksum
     *
     * @param int $gibbonFileID The file record ID to verify
     * @return bool True if checksums match, false if mismatch or file missing
     */
    public function verifyFileIntegrity($gibbonFileID)
    {
        // Retrieve stored checksum from gibbonFile record
        $data = ['gibbonFileID' => $gibbonFileID];
        $sql = "SELECT filePath, checksum FROM gibbonFile WHERE gibbonFileID = :gibbonFileID";
        
        $result = $this->db()->select($sql, $data);
        
        if (empty($result)) {
            return false;
        }
        
        $record = $result->fetch();
        $storedChecksum = $record['checksum'];
        $filePath = $record['filePath'];
        
        // Check if file exists at filePath
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Recalculate checksum from file at filePath
        $calculatedChecksum = hash_file('sha256', $filePath);
        
        if (empty($calculatedChecksum)) {
            return false;
        }
        
        // Compare stored and calculated checksums
        return $storedChecksum === $calculatedChecksum;
    }
}
