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

use Gibbon\Module\Reports\Domain\ReportingCycleGateway;
use Gibbon\Module\Reports\Domain\ReportingValueGateway;
use Gibbon\Module\Reports\Domain\ReportingProgressGateway;
use Gibbon\Module\Reports\Domain\ReportingScopeGateway;
use Gibbon\Module\Reports\Domain\ReportingCriteriaGateway;
use Gibbon\Module\Reports\Domain\ReportingAccessGateway;
use Gibbon\Data\Validator;
use Gibbon\FileUploader;
use Gibbon\Domain\System\FileGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$urlParams = [
    'gibbonReportingCycleID' => $_POST['gibbonReportingCycleID'] ?? '',
    'gibbonReportingScopeID' => $_POST['gibbonReportingScopeID'] ?? '',
    'scopeTypeID' => $_POST['scopeTypeID'] ?? '',
    'gibbonPersonID' => $_POST['gibbonPersonID'] ?? '',
    'allStudents' => $_POST['allStudents'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Reports/reporting_write.php&'.http_build_query($urlParams);

if (isActionAccessible($guid, $connection2, '/modules/Reports/reporting_write.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;
    $reportingValueGateway = $container->get(ReportingValueGateway::class);
    $reportingProgressGateway = $container->get(ReportingProgressGateway::class);
    $reportingCriteriaGateway = $container->get(ReportingCriteriaGateway::class);
    $reportingAccessGateway = $container->get(ReportingAccessGateway::class);
    $fileUploader = $container->get(FileUploader::class);
    $fileGateway = $container->get(FileGateway::class);
    
    $values = $_POST['value'] ?? [];

    // Validate the required values are present
    if (empty($urlParams['gibbonReportingCycleID']) || empty($urlParams['gibbonReportingScopeID']) || empty($urlParams['scopeTypeID']) || empty($values)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $reportingScope = $container->get(ReportingScopeGateway::class)->getByID($urlParams['gibbonReportingScopeID']);
    $reportingCycle = $container->get(ReportingCycleGateway::class)->getByID($urlParams['gibbonReportingCycleID']);
    if (empty($reportingCycle) || empty($reportingScope)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // ACCESS CHECK: overall check (for high-level access) or per-scope check for general access
    $accessCheck = $reportingAccessGateway->getAccessToScopeByPerson($urlParams['gibbonReportingScopeID'], $session->get('gibbonPersonID'));
    $highestAction = getHighestGroupedAction($guid, $_POST['address'], $connection2);
    if ($highestAction == 'Write Reports_editAll') {
        $reportingOpen = ($accessCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = true;
        $canWriteReport = true;
    } elseif ($highestAction == 'Write Reports_mine') {
        $writeCheck = $reportingAccessGateway->getAccessToScopeAndCriteriaGroupByPerson($urlParams['gibbonReportingScopeID'], $reportingScope['scopeType'], $urlParams['scopeTypeID'], $session->get('gibbonPersonID'));
        $reportingOpen = ($writeCheck['reportingOpen'] ?? 'N') == 'Y';
        $canAccessReport = ($accessCheck['canAccess'] ?? 'N') == 'Y';
        $canWriteReport = $reportingOpen && ($writeCheck['canWrite'] ?? 'N') == 'Y';
    }

    if (empty($canAccessReport) || !$canWriteReport) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    $data = [
        'gibbonReportingCycleID'    => $urlParams['gibbonReportingCycleID'],
        'gibbonReportingCriteriaID' => $urlParams['gibbonReportingScopeID'],
        'gibbonSchoolYearID'        => $reportingCycle['gibbonSchoolYearID'],
        'gibbonCourseClassID'       => $reportingScope['scopeType'] == 'Course' ? $urlParams['scopeTypeID'] : '',
        'gibbonPersonIDCreated'     => $session->get('gibbonPersonID'),
    ];
    
    // Insert or update each record
    foreach ($values as $gibbonReportingCriteriaID => $value) {
        $data['gibbonReportingCriteriaID'] = $gibbonReportingCriteriaID;
        $data['value'] = $data['comment'] = $data['gibbonScaleGradeID'] = null;

        $criteriaType = $reportingCriteriaGateway->getCriteriaTypeByID($gibbonReportingCriteriaID);
        $criteriaOptions = !empty($criteriaType['options']) ? json_decode($criteriaType['options'], true) : [];

        $fileMetaData = null;
        if ($criteriaType['valueType'] == 'Comment' || $criteriaType['valueType'] == 'Remark') {
            $data['comment'] = $value;
        } elseif ($criteriaType['valueType'] == 'Grade Scale') {
            $data['value'] = $reportingValueGateway->getGradeScaleValueByID($value);
            $data['gibbonScaleGradeID'] = $value;
        } elseif ($criteriaType['valueType'] == 'Image') {
            if (!empty($_FILES['file'.$gibbonReportingCriteriaID]['tmp_name'])) {
                $data['value'] = $fileUploader->uploadAndResizeImage($_FILES['file'.$gibbonReportingCriteriaID], 'reportFile', $criteriaOptions['imageSize'] ?? 1024, $criteriaOptions['imageQuality'] ?? 80);
                
                // Get file metadata for tracking
                if (!empty($data['value'])) {
                    $fileMetaData = $fileUploader->getFileMetaData($data['value']);
                }
            } else {
                $data['value'] = $value;
            }
        } else {
            $data['value'] = $value;
        }

        $updated = $reportingValueGateway->insertAndUpdate($data, [
            'value' => $data['value'],
            'comment' => $data['comment'],
            'gibbonScaleGradeID' => $data['gibbonScaleGradeID'],
            'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
            'timestampModified' => date('Y-m-d H:i:s'),
        ]);
        
        // Record file tracking after successful insert/update
        if (!empty($fileMetaData)) {
            // We need the record ID to track the file
            $gibbonReportingValueID = null;
            
            if (is_numeric($updated) && $updated > 1) {
                $gibbonReportingValueID = $updated;
            } else {
                // UPDATE case
                $record = $reportingValueGateway->selectBy(['gibbonReportingCriteriaID' => $gibbonReportingCriteriaID, 'gibbonReportingCycleID' => $data['gibbonReportingCycleID'], 'gibbonCourseClassID' => $data['gibbonCourseClassID']])->fetch();
                
                if (!empty($record)) {
                    $gibbonReportingValueID = $record['gibbonReportingValueID'];
                }
            }
            
            if (!empty($gibbonReportingValueID)) {
                $gibbonFileID = $fileGateway->recordFileUpload($fileMetaData, 'gibbonReportingValue', $gibbonReportingValueID, 'value');
            }

            if (empty($gibbonFileID)) {
                $partialFail = true;
            }
        }
        
        $partialFail = !$updated;
    }

    // Update progress
    // $dataProgress = [
    //     'gibbonReportingScopeID' => $urlParams['gibbonReportingScopeID'],
    //     'gibbonYearGroupID'      => $reportingScope['scopeType'] == 'Year Group' ? $urlParams['scopeTypeID'] : null,
    //     'gibbonFormGroupID'      => $reportingScope['scopeType'] == 'Form Group' ? $urlParams['scopeTypeID'] : null,
    //     'gibbonCourseClassID'    => $reportingScope['scopeType'] == 'Course' ? $urlParams['scopeTypeID'] : '',
    //     'gibbonPersonIDStudent'  => $gibbonPersonIDStudent,
    //     'status'               => !empty($_POST['complete'])? 'Complete' : 'In Progress',
    // ];
    // $updated = $reportingProgressGateway->insertAndUpdate($dataProgress, [
    //     'status' => $dataProgress['status'],
    // ]);

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
