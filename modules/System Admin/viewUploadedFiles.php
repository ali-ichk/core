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

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\FileGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/System Admin/viewUploadedFiles.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('View Uploaded Files'));

    $page->return->addReturns([
        'warning1' => __('File status check completed with partial errors.'),
    ]);

    $fileGateway = $container->get(FileGateway::class);

    // CRITERIA
    $criteria = $fileGateway->newQueryCriteria()
        ->sortBy('uploadedAt', 'DESC')
        ->fromPOST();

    $files = $fileGateway->queryNoPointerFiles($criteria);

    // DATA TABLE
    $table = DataTable::createPaginated('uploadedFiles', $criteria);
    $table->setTitle(__('Uploaded Files'));
    $table->setDescription(__('These files were uploaded via the rich text editor. Run the update to mark unreferenced files as unused.'));

    $table->addHeaderAction('searchUploadedFiles', __('Update File Status'))
        ->setURL('/modules/System Admin/searchUploadedFiles.php')
        ->setIcon('delivery2')
        ->modalWindow(650, 220)
        ->displayLabel();

    $table->modifyRows(function ($file, $row) {
        if ($file['isUsed'] == 'N') $row->addClass('error');
        return $row;
    });

    $table->addColumn('fileName', __('File Name'));
    $table->addColumn('fileExtension', __('Type'))->width('5%');
    $table->addColumn('fileSize', __('Size'))
        ->width('8%')
        ->format(function ($file) {
            return Format::fileSize($file['fileSize']);
        });
    $table->addColumn('uploadedAt', __('Uploaded'))
        ->format(Format::using('dateTime', 'uploadedAt'));
    $table->addColumn('isUsed', __('Status'))
        ->format(function ($file) {
            return ($file['isUsed'] == 'N')
                ? '<span class="tag dull">'.__('Unused').'</span>'
                : '<span class="tag success">'.__('In Use').'</span>';
        });

    echo $table->render($files);
}
