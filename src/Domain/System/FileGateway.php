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

use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Database\Result;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\FilePointerGateway;

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

    private $session;
    private $filePointerGateway;

    /**
     * Create a new gateway instance using the supplied database connection.
     * 
     * @param Connection $db
     * @param Session $session
     * @param FilePointerGateway $filePointerGateway
     */
    public function __construct(Connection $db, Session $session, FilePointerGateway $filePointerGateway)
    {
        parent::__construct($db);
        $this->session = $session;
        $this->filePointerGateway = $filePointerGateway;
    }

    /**
     * Record a file upload with pointer in a transaction
     * @param array $metaData Array with file metadata
     * @param int $gibbonPersonIDOwner gibbonPersonID of uploader
     * @param string|null $foreignTable Name of the foreign table (nullable)
     * @param int|null $foreignTableID Primary key value in the foreign table (nullable)
     * @param string $foreignColumn Column name storing the file path
     * @return int|false gibbonFileID on success, false on failure
     */
    public function recordFileUpload(array $metaData, string $foreignTable, int|string $foreignTableID, string $foreignColumn)
    {
        // Begin database transaction
        $this->db()->beginTransaction();

        // Validate file exists at filePath
        if (!file_exists($metaData['absolutePath'])) {
            return false;
        }

        $oldFile = $this->filePointerGateway->checkPointerExists($foreignTable, $foreignTableID, $foreignColumn)->fetch();

        if (empty($oldFile)) {
            // Insert record into gibbonFile table
            $gibbonFileID = $this->insertAndUpdateFile($metaData);

            // If recordFileUpload fails, rollback and return false
            if (empty($gibbonFileID)) {
                $this->db()->rollBack();
                return false;
            }

            // Call recordFilePointer with the gibbonFileID
            $gibbonFilePointerID = $this->filePointerGateway->insertFilePointer($gibbonFileID, $foreignTable, $foreignTableID, $foreignColumn);

            // If pointer insertion fails, rollback transaction and return false
            if (empty($gibbonFilePointerID)) {
                $this->db()->rollBack();
                return false;
            }
        } else {
            $gibbonFileID = $this->insertAndUpdateFile($metaData, $oldFile['gibbonFileID']);

            // If update fails, rollback and return false
            if (empty($gibbonFileID)) {
                $this->db()->rollBack();
                return false;
            }

            // Store old file path for deletion after transaction commits
            $oldFilePath = $this->session->get('absolutePath') . '/' . $oldFile['filePath'];
        }

        // All operations succeeded, commit the transaction
        $this->db()->commit();

        // Delete old file only after successful commit (for updates only)
        if (!empty($oldFilePath) && file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }

        // Return $gibbonFileID
        return $gibbonFileID;
    }

        /**
     * Record a file upload with metadata
     *
     * @param array $metaData Array with file metadata
     * @return int|false gibbonFileID on success, false on failure
     */
    protected function insertAndUpdateFile(array $metaData, $gibbonFileID = null)
    {
        // Calculate SHA-256 checksum
        $checksum = hash_file('sha256', $metaData['absolutePath']);
        if ($checksum === false) {
            return false;
        }

        // Build data array with all fields
        $data = [
            'filePath' => $metaData['filePath'] ?? '',
            'fileName' => $metaData['fileName'] ?? '',
            'fileExtension' => $metaData['fileExtension'] ?? '',
            'fileSize' => $metaData['fileSize'] ?? '',
            'mimeType' => $metaData['mimeType'] ?? '',
            'gibbonPersonIDOwner' => $metaData['gibbonPersonIDOwner'] ?? '',
            'uploadedAt' => date('Y-m-d H:i:s'),
            'checksum' => $checksum
        ];

        if (empty($gibbonFileID)) {
            // Insert record into gibbonFile table
            $gibbonFileID = $this->insert($data);
            
            if (empty($gibbonFileID)) {
                return false;
            }
        } else {
            if (!$this->update($gibbonFileID, $data)) {
                return false;
            }
        }

        return $gibbonFileID;
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
     * @return array of records
     */
    public function selectOrphanedFileRecords()
    {
        // Query all records from gibbonFile
        $sql = "SELECT gibbonFileID, filePath, fileName, uploadedAt FROM gibbonFile";

        $result = $this->db()->select($sql)->fetchAll();

        // Filter to records where file does not exist
        $orphanedRecords = [];
        foreach ($result as $record) {
            $fullPath = $this->session->get('absolutePath'). '/' . $record['filePath'];
            if (!file_exists($fullPath)) {
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
        
        // Construct absolute path from stored relative filePath
        $absolutePath = $this->session->get('absolutePath') . '/' . $filePath;
        
        // Check if file exists at absolute path
        if (!file_exists($absolutePath)) {
            return false;
        }
        
        // Recalculate checksum from file at absolute path
        $calculatedChecksum = hash_file('sha256', $absolutePath);
        
        if (empty($calculatedChecksum)) {
            return false;
        }
        
        // Compare stored and calculated checksums
        return $storedChecksum === $calculatedChecksum;
    }

}
