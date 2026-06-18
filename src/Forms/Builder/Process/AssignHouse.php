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

namespace Gibbon\Forms\Builder\Process;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\School\HouseGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\User\RoleGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Forms\Builder\AbstractFormProcess;
use Gibbon\Forms\Builder\FormBuilderInterface;
use Gibbon\Forms\Builder\Storage\FormDataInterface;
use Gibbon\Forms\Builder\View\AssignHouseView;

class AssignHouse extends AbstractFormProcess implements ViewableProcess
{
    protected $requiredFields = ['gibbonSchoolYearIDEntry', 'gibbonYearGroupIDEntry', 'gender'];

    private $session;
    private $userGateway;
    private $houseGateway;
    private $familyGateway;
    private $roleGateway;

    public function __construct(Session $session, UserGateway $userGateway, HouseGateway $houseGateway, FamilyGateway $familyGateway, RoleGateway $roleGateway)
    {
        $this->session = $session;
        $this->userGateway = $userGateway;
        $this->houseGateway = $houseGateway;
        $this->familyGateway = $familyGateway;
        $this->roleGateway = $roleGateway;
    }

    public function getViewClass() : string
    {
        return AssignHouseView::class;
    }

    public function isEnabled(FormBuilderInterface $builder)
    {
        return $builder->getConfig('autoHouseAssign') == 'Y';
    }

    public function process(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('gibbonPersonIDStudent')) return;

        $assignedHouse = null;
        $gibbonFamilyID = $formData->get('gibbonFamilyID');
        $gibbonPersonIDStudent = $formData->get('gibbonPersonIDStudent');

        // Try family-based house assignment if family ID exists
        if (!empty($gibbonFamilyID)) {

            // Check parents first
            $adults = $this->familyGateway->selectAdultsByFamily($gibbonFamilyID, true);
            foreach ($adults as $adult) {
                // Check if parent is staff and has a house assignment
                if ($adult['status'] == 'Full' && !empty($adult['gibbonHouseID'])) {
                    // Staff members have roles in Staff category
                    $roleCategory = $this->roleGateway->getRoleCategory($adult['gibbonRoleIDPrimary']);
                    if ($roleCategory == 'Staff') {
                        $house = $this->houseGateway->getByID($adult['gibbonHouseID']);
                        if (!empty($house)) {
                            $assignedHouse = [
                                'gibbonHouseID' => $adult['gibbonHouseID'],
                                'house' => $house['name'],
                            ];
                            break;
                        }
                    }
                }
            }

            // If no staff parent found, check enrolled siblings
            if (empty($assignedHouse)) {
                $children = $this->familyGateway->selectChildrenByFamily($gibbonFamilyID, true);
                foreach ($children as $child) {
                    // Skip the current student
                    if ($child['gibbonPersonID'] == $gibbonPersonIDStudent) {
                        continue;
                    }
                    
                    // Check if sibling is enrolled and has a house
                    if ($child['status'] == 'Full' && !empty($child['gibbonHouseID'])) {
                        $house = $this->houseGateway->getByID($child['gibbonHouseID']);
                        if (!empty($house)) {
                            $assignedHouse = [
                                'gibbonHouseID' => $child['gibbonHouseID'],
                                'house' => $house['name'],
                            ];
                            break;
                        }
                    }
                }
            }
        }

        // Fallback to gender-based assignment if no family assignment found
        if (empty($assignedHouse)) {
            $assignedHouse = $this->houseGateway->selectAssignedHouseByGender($this->session->get('gibbonSchoolYearIDCurrent'), $formData->get('gibbonYearGroupIDEntry'), $formData->get('gender'))->fetch();
        }

        if (empty($assignedHouse)) return;

        $formData->set('gibbonHouseID', $assignedHouse['gibbonHouseID']);

        // Update the user data for this student
        $this->userGateway->update($gibbonPersonIDStudent, [
            'gibbonHouseID' => $formData->get('gibbonHouseID'),
        ]);

        $this->setResult($assignedHouse['house']);
    }

    public function rollback(FormBuilderInterface $builder, FormDataInterface $formData)
    {
        if (!$formData->has('gibbonPersonIDStudent')) return;

        $this->userGateway->update($formData->get('gibbonPersonIDStudent'), [
            'gibbonHouseID' => null,
        ]);

        $formData->setResult('assignHouseResult', false);
    }
}
