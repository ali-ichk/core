<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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
use Gibbon\Contracts\Comms\Mailer;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Forms\PersonalDocumentHandler;

include '../../gibbon.php';

//Check to see if system settings are set from databases
if (!$session->has('systemSettingsSet')) {
    getSystemSettings($guid, $connection2);
}

//Module includes from User Admin (for custom fields)
include '../User Admin/moduleFunctions.php';

//Module includes from Finance (for setting payment log)
include '../Finance/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Students/applicationForm.php';

$proceed = false;
$public = false;

if (!$session->has('username')) {
    $public = true;
    //Get public access
    $access = getSettingByScope($connection2, 'Application Form', 'publicApplications');
    if ($access == 'Y') {
        $proceed = true;
    }
} else {
    if (isActionAccessible($guid, $connection2, '/modules/Students/applicationForm.php') != false) {
        $proceed = true;
    }
}

if ($proceed == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $id = null;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    }
    //IF ID IS NOT SET IT IS A NEW APPLICATION, SO PROCESS AND SAVE.
    if (is_null($id)) {
        //Proceed!

        // Sanitize the whole $_POST array
        $validator = new \Gibbon\Data\Validator();
        $_POST = $validator->sanitize($_POST);

        //GET STUDENT FIELDS
        $surname = $_POST['surname'] ?? '';
        $firstName = trim($_POST['firstName'] ?? '');
        $preferredName = trim($_POST['preferredName'] ?? '');
        $officialName = trim($_POST['officialName'] ?? '');
        $nameInCharacters = $_POST['nameInCharacters'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['dob'] ?? '';
        if ($dob == '') {
            $dob = null;
        } else {
            $dob = Format::dateConvert($dob);
        }
        $languageHomePrimary = $_POST['languageHomePrimary'] ?? '';
        $languageHomeSecondary = $_POST['languageHomeSecondary'] ?? '';
        $languageFirst = $_POST['languageFirst'] ?? '';
        $languageSecond = $_POST['languageSecond'] ?? '';
        $languageThird = $_POST['languageThird'] ?? '';
        $countryOfBirth = $_POST['countryOfBirth'] ?? '';
        $citizenship1 = $_POST['citizenship1'] ?? '';
        $citizenship1Passport = $_POST['citizenship1Passport'] ?? '';
        $citizenship1PassportExpiry = !empty($_POST['citizenship1PassportExpiry']) ? Format::dateConvert($_POST['citizenship1PassportExpiry']) : null;
        $nationalIDCardNumber = $_POST['nationalIDCardNumber'] ?? '';
        $residencyStatus = $_POST['residencyStatus'] ?? '';
        $visaExpiryDate = $_POST['visaExpiryDate'] ?? '';
        if ($visaExpiryDate == '') {
            $visaExpiryDate = null;
        } else {
            $visaExpiryDate = Format::dateConvert($visaExpiryDate);
        }
        $email = trim($_POST['email'] ?? '');
        $phone1Type = $_POST['phone1Type'] ?? '';
        if (!empty($_POST['phone1']) and $phone1Type == '') {
            $phone1Type = 'Other';
        }
        $phone1CountryCode = $_POST['phone1CountryCode'] ?? '';
        $phone1 = preg_replace('/[^0-9+]/', '', $_POST['phone1'] ?? '');
        $phone2Type = $_POST['phone2Type'] ?? '';
        if (!empty($_POST['phone2']) and $phone2Type == '') {
            $phone2Type = 'Other';
        }
        $phone2CountryCode = $_POST['phone2CountryCode'] ?? '';
        $phone2 = preg_replace('/[^0-9+]/', '', $_POST['phone2'] ?? '');

        $medicalInformation = $_POST['medicalInformation'] ?? '';
        $sen = $_POST['sen'] ?? 'N';
        if ($sen == 'N') {
            $senDetails = '';
        } else {
            $senDetails = $_POST['senDetails'] ?? '';
        }
        $gibbonSchoolYearIDEntry = $_POST['gibbonSchoolYearIDEntry'] ?? '';
        $dayType = $_POST['dayType'] ?? null;
        $dateStart = !empty($_POST['dateStart']) ? Format::dateConvert($_POST['dateStart']) : null;
        $gibbonYearGroupIDEntry = $_POST['gibbonYearGroupIDEntry'] ?? '';
        $referenceEmail = $_POST['referenceEmail'] ?? '';
        $schoolName1 = $_POST['schoolName1'] ?? '';
        $schoolAddress1 = $_POST['schoolAddress1'] ?? '';
        $schoolGrades1 = $_POST['schoolGrades1'] ?? '';
        $schoolLanguage1 = $_POST['schoolLanguage1'] ?? '';
        $schoolDate1 = $_POST['schoolDate1'] ?? '';
        if ($schoolDate1 == '') {
            $schoolDate1 = null;
        } else {
            $schoolDate1 = Format::dateConvert($schoolDate1);
        }
        $schoolName2 = $_POST['schoolName2'] ?? '';
        $schoolAddress2 = $_POST['schoolAddress2'] ?? '';
        $schoolGrades2 = $_POST['schoolGrades2'] ?? '';
        $schoolLanguage2 = $_POST['schoolLanguage2'] ?? '';
        $schoolDate2 = $_POST['schoolDate2'] ?? '';
        if ($schoolDate2 == '') {
            $schoolDate2 = null;
        } else {
            $schoolDate2 = Format::dateConvert($schoolDate2);
        }

        //GET FAMILY FEILDS
        $gibbonFamily = $_POST['gibbonFamily'] ?? '';
        if ($gibbonFamily == 'TRUE') {
            $gibbonFamilyID = $_POST['gibbonFamilyID'];
        } else {
            $gibbonFamilyID = null;
        }
        $homeAddress = $_POST['homeAddress'] ?? null;
        $homeAddressDistrict = $_POST['homeAddressDistrict'] ?? null;
        $homeAddressCountry = $_POST['homeAddressCountry'] ?? null;

        //GET PARENT1 FEILDS
        $parent1gibbonPersonID = $_POST['parent1gibbonPersonID'] ?? null;
        $parent1title = $_POST['parent1title'] ?? null;
        $parent1surname = trim($_POST['parent1surname'] ?? '');
        $parent1firstName = trim($_POST['parent1firstName'] ?? '');
        $parent1preferredName = trim($_POST['parent1preferredName'] ?? '');
        $parent1officialName = trim($_POST['parent1officialName'] ?? '');
        $parent1nameInCharacters = $_POST['parent1nameInCharacters'] ?? null;
        $parent1gender = $_POST['parent1gender'] ?? null;
        $parent1relationship = $_POST['parent1relationship'] ?? null;
        $parent1languageFirst = $_POST['parent1languageFirst'] ?? null;
        $parent1languageSecond = $_POST['parent1languageSecond'] ?? null;
        $parent1citizenship1 = $_POST['parent1citizenship1'] ?? null;
        $parent1nationalIDCardNumber = $_POST['parent1nationalIDCardNumber'] ?? null;
        $parent1residencyStatus = $_POST['parent1residencyStatus'] ?? null;
        $parent1visaExpiryDate = null;
        if (isset($_POST['parent1visaExpiryDate'])) {
            if ($_POST['parent1visaExpiryDate'] != '') {
                $parent1visaExpiryDate = Format::dateConvert($_POST['parent1visaExpiryDate']);
            }
        }
        $parent1email = trim($_POST['parent1email'] ?? '');
        $parent1phone1Type = $_POST['parent1phone1Type'] ?? null;
        if (isset($_POST['parent1phone1']) and $parent1phone1Type == '') {
            $parent1phone1Type = 'Other';
        }
        $parent1phone1CountryCode = $_POST['parent1phone1CountryCode'] ?? null;
        $parent1phone1 = $_POST['parent1phone1'] ?? null;
        $parent1phone2Type = $_POST['parent1phone2Type'] ?? null;
        if (isset($_POST['parent1phone2']) and $parent1phone2Type == '') {
            $parent1phone2Type = 'Other';
        }
        $parent1phone2CountryCode = $_POST['parent1phone2CountryCode'] ?? null;
        $parent1phone2 = $_POST['parent1phone2'] ?? null;
        $parent1profession = $_POST['parent1profession'] ?? null;
        $parent1employer = $_POST['parent1employer'] ?? null;

        //GET PARENT2 FEILDS
        $parent2title = $_POST['parent2title'] ?? null;
        $parent2surname = trim($_POST['parent2surname'] ?? '');
        $parent2firstName = trim($_POST['parent2firstName'] ?? '');
        $parent2preferredName = trim($_POST['parent2preferredName'] ?? '');
        $parent2officialName = trim($_POST['parent2officialName'] ?? '');
        $parent2nameInCharacters = $_POST['parent2nameInCharacters'] ?? null;
        $parent2gender = $_POST['parent2gender'] ?? null;
        $parent2relationship = $_POST['parent2relationship'] ?? null;
        $parent2languageFirst = $_POST['parent2languageFirst'] ?? null;
        $parent2languageSecond = $_POST['parent2languageSecond'] ?? null;
        $parent2citizenship1 = $_POST['parent2citizenship1'] ?? null;
        $parent2nationalIDCardNumber = $_POST['parent2nationalIDCardNumber'] ?? null;
        $parent2residencyStatus = $_POST['parent2residencyStatus'] ?? null;
        $parent2visaExpiryDate = null;
        if (isset($_POST['parent2visaExpiryDate'])) {
            if ($_POST['parent2visaExpiryDate'] != '') {
                $parent2visaExpiryDate = Format::dateConvert($_POST['parent2visaExpiryDate']);
            }
        }
        $parent2email = trim($_POST['parent2email'] ?? '');
        $parent2phone1Type = $_POST['parent2phone1Type'] ?? null;
        if (isset($_POST['parent2phone1']) and $parent2phone1Type == '') {
            $parent2phone1Type = 'Other';
        }
        $parent2phone1CountryCode = $_POST['parent2phone1CountryCode'] ?? null;
        $parent2phone1 = $_POST['parent2phone1'] ?? null;
        $parent2phone2Type = $_POST['parent2phone2Type'] ?? null;
        if (isset($_POST['parent2phone2']) and $parent2phone2Type == '') {
            $parent2phone2Type = 'Other';
        }
        $parent2phone2CountryCode = $_POST['parent2phone2CountryCode'] ?? null;
        $parent2phone2 = $_POST['parent2phone2'] ?? null;
        $parent2profession = $_POST['parent2profession'] ?? null;
        $parent2employer = $_POST['parent2employer'] ?? null;


        //GET SIBLING FIELDS
        $siblingName1 = $_POST['siblingName1'] ?? '';
        $siblingDOB1 = $_POST['siblingDOB1'] ?? '';
        if ($siblingDOB1 == '') {
            $siblingDOB1 = null;
        } else {
            $siblingDOB1 = Format::dateConvert($siblingDOB1);
        }
        $siblingSchool1 = $_POST['siblingSchool1'] ?? '';
        $siblingSchoolJoiningDate1 = $_POST['siblingSchoolJoiningDate1'] ?? '';
        if ($siblingSchoolJoiningDate1 == '') {
            $siblingSchoolJoiningDate1 = null;
        } else {
            $siblingSchoolJoiningDate1 = Format::dateConvert($siblingSchoolJoiningDate1);
        }
        $siblingName2 = $_POST['siblingName2'] ?? '';
        $siblingDOB2 = $_POST['siblingDOB2'] ?? '';
        if ($siblingDOB2 == '') {
            $siblingDOB2 = null;
        } else {
            $siblingDOB2 = Format::dateConvert($siblingDOB2);
        }
        $siblingSchool2 = $_POST['siblingSchool2'] ?? '';
        $siblingSchoolJoiningDate2 = $_POST['siblingSchoolJoiningDate2'] ?? '';
        if ($siblingSchoolJoiningDate2 == '') {
            $siblingSchoolJoiningDate2 = null;
        } else {
            $siblingSchoolJoiningDate2 = Format::dateConvert($siblingSchoolJoiningDate2);
        }
        $siblingName3 = $_POST['siblingName3'] ?? '';
        $siblingDOB3 = $_POST['siblingDOB3'] ?? '';
        if ($siblingDOB3 == '') {
            $siblingDOB3 = null;
        } else {
            $siblingDOB3 = Format::dateConvert($siblingDOB3);
        }
        $siblingSchool3 = $_POST['siblingSchool3'] ?? '';
        $siblingSchoolJoiningDate3 = $_POST['siblingSchoolJoiningDate3'] ?? '';
        if ($siblingSchoolJoiningDate3 == '') {
            $siblingSchoolJoiningDate3 = null;
        } else {
            $siblingSchoolJoiningDate3 = Format::dateConvert($siblingSchoolJoiningDate3);
        }

        //GET PAYMENT FIELDS
        $payment =  $_POST['payment'] ?? '';
        $companyName = $_POST['companyName'] ?? null;
        $companyContact = $_POST['companyContact'] ?? null;
        $companyAddress = $_POST['companyAddress'] ?? null;
        $companyEmail = $_POST['companyEmail'] ?? null;
        $companyCCFamily = $_POST['companyCCFamily'] ?? null;
        $companyPhone = $_POST['companyPhone'] ?? null;
        $companyAll = $_POST['companyAll'] ?? null;

        $gibbonFinanceFeeCategoryIDList = null;
        if (isset($_POST['gibbonFinanceFeeCategoryIDList'])) {
            $gibbonFinanceFeeCategoryIDArray = $_POST['gibbonFinanceFeeCategoryIDList'];
            if (count($gibbonFinanceFeeCategoryIDArray) > 0) {
                foreach ($gibbonFinanceFeeCategoryIDArray as $gibbonFinanceFeeCategoryID) {
                    $gibbonFinanceFeeCategoryIDList .= $gibbonFinanceFeeCategoryID.',';
                }
                $gibbonFinanceFeeCategoryIDList = substr($gibbonFinanceFeeCategoryIDList, 0, -1);
            }
        }

        //GET OTHER FIELDS
        $languageChoice = $_POST['languageChoice'] ?? null;
        $languageChoiceExperience = $_POST['languageChoiceExperience'] ?? null;
        $scholarshipInterest = $_POST['scholarshipInterest'] ?? 'N';
        $scholarshipRequired = $_POST['scholarshipRequired'] ?? 'N';
        $howDidYouHear = $_POST['howDidYouHear'] ?? null;
        $howDidYouHearMore = $_POST['howDidYouHearMore'] ?? null;

        $agreement = null;
        if (isset($_POST['agreement'])) {
            if ($_POST['agreement'] == 'on') {
                $agreement = 'Y';
            } else {
                $agreement = 'N';
            }
        }
        $privacy = null;
        if (isset($_POST['privacyOptions'])) {
            $privacyOptions = $_POST['privacyOptions'];
            foreach ($privacyOptions as $privacyOption) {
                if ($privacyOption != '') {
                    $privacy .= $privacyOption.', ';
                }
            }
            if ($privacy != '') {
                $privacy = substr($privacy, 0, -2);
            } else {
                $privacy = null;
            }
        }

        //VALIDATE INPUTS
        $familyFail = false;
        if ($gibbonFamily == 'TRUE') {
            if ($gibbonFamilyID == '') {
                $familyFail = true;
            }
        } else {
            if ($homeAddress == '' or $homeAddressDistrict == '' or $homeAddressCountry == '') {
                $familyFail = true;
            }
            if ($parent1gibbonPersonID == null) {
                if ($parent1title == '' or $parent1surname == '' or $parent1firstName == '' or $parent1preferredName == '' or $parent1officialName == '' or $parent1gender == '' or $parent1relationship == '' or $parent1phone1 == '' or $parent1profession == '') {
                    $familyFail = true;
                }
            }
            if (isset($_POST['secondParent'])) {
                if ($_POST['secondParent'] != 'No') {
                    if ($parent2title == '' or $parent2surname == '' or $parent2firstName == '' or $parent2preferredName == '' or $parent2officialName == '' or $parent2gender == '' or $parent2relationship == '' or $parent2phone1 == '' or $parent2profession == '') {
                        $familyFail = true;
                    }
                }
            }
        }
        if ($surname == '' or $firstName == '' or $preferredName == '' or $officialName == '' or $gender == '' or $dob == '' or $languageHomePrimary == '' or $languageFirst == '' or $countryOfBirth == '' or $gibbonSchoolYearIDEntry == '' or $dateStart == '' or $gibbonYearGroupIDEntry == '' or $sen == '' or $howDidYouHear == '' or (isset($_POST['agreement']) and $agreement != 'Y') or $familyFail) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
        } else {
            //DEAL WITH CUSTOM FIELDS
            $customRequireFail = false;
            $customFieldHandler = $container->get(CustomFieldHandler::class);

            $params = ['student' => 1, 'applicationForm' => 1];
            $fields = $customFieldHandler->getFieldDataFromPOST('User', $params, $customRequireFail);

            $parent1fields = $parent2fields = '';
            if ($gibbonFamily == 'FALSE') { //Only if there is no family
                $params = ['parent' => 1, 'applicationForm' => 1, 'prefix' => 'parent1custom'];
                $parent1fields = $customFieldHandler->getFieldDataFromPOST('User', $params, $customRequireFail);

                if (empty($_POST['secondParent'])) {
                    $params = ['parent' => 1, 'applicationForm' => 1, 'prefix' => 'parent2custom'];
                    $parent2fields = $customFieldHandler->getFieldDataFromPOST('User', $params, $customRequireFail);
                }
            }

            if ($customRequireFail) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit();
            } else {
                //Write to database
                try {
                    $data = array('surname' => $surname, 'firstName' => $firstName, 'preferredName' => $preferredName, 'officialName' => $officialName, 'nameInCharacters' => $nameInCharacters, 'gender' => $gender, 'dob' => $dob, 'languageHomePrimary' => $languageHomePrimary, 'languageHomeSecondary' => $languageHomeSecondary, 'languageFirst' => $languageFirst, 'languageSecond' => $languageSecond, 'languageThird' => $languageThird, 'countryOfBirth' => $countryOfBirth, 'email' => $email, 'homeAddress' => $homeAddress, 'homeAddressDistrict' => $homeAddressDistrict, 'homeAddressCountry' => $homeAddressCountry, 'phone1Type' => $phone1Type, 'phone1CountryCode' => $phone1CountryCode, 'phone1' => $phone1, 'phone2Type' => $phone2Type, 'phone2CountryCode' => $phone2CountryCode, 'phone2' => $phone2, 'medicalInformation' => $medicalInformation, 'sen' => $sen, 'senDetails' => $senDetails, 'gibbonSchoolYearIDEntry' => $gibbonSchoolYearIDEntry, 'dayType' => $dayType, 'dateStart' => $dateStart, 'gibbonYearGroupIDEntry' => $gibbonYearGroupIDEntry, 'referenceEmail' => $referenceEmail, 'schoolName1' => $schoolName1, 'schoolAddress1' => $schoolAddress1, 'schoolGrades1' => $schoolGrades1, 'schoolLanguage1' => $schoolLanguage1, 'schoolDate1' => $schoolDate1, 'schoolName2' => $schoolName2, 'schoolAddress2' => $schoolAddress2, 'schoolGrades2' => $schoolGrades2, 'schoolLanguage2' => $schoolLanguage2, 'schoolDate2' => $schoolDate2, 'gibbonFamilyID' => $gibbonFamilyID, 'parent1gibbonPersonID' => $parent1gibbonPersonID, 'parent1title' => $parent1title, 'parent1surname' => $parent1surname, 'parent1firstName' => $parent1firstName, 'parent1preferredName' => $parent1preferredName, 'parent1officialName' => $parent1officialName, 'parent1nameInCharacters' => $parent1nameInCharacters, 'parent1gender' => $parent1gender, 'parent1relationship' => $parent1relationship, 'parent1languageFirst' => $parent1languageFirst, 'parent1languageSecond' => $parent1languageSecond,  'parent1email' => $parent1email, 'parent1phone1Type' => $parent1phone1Type, 'parent1phone1CountryCode' => $parent1phone1CountryCode, 'parent1phone1' => $parent1phone1, 'parent1phone2Type' => $parent1phone2Type, 'parent1phone2CountryCode' => $parent1phone2CountryCode, 'parent1phone2' => $parent1phone2, 'parent1profession' => $parent1profession, 'parent1employer' => $parent1employer, 'parent2title' => $parent2title, 'parent2surname' => $parent2surname, 'parent2firstName' => $parent2firstName, 'parent2preferredName' => $parent2preferredName, 'parent2officialName' => $parent2officialName, 'parent2nameInCharacters' => $parent2nameInCharacters, 'parent2gender' => $parent2gender, 'parent2relationship' => $parent2relationship, 'parent2languageFirst' => $parent2languageFirst, 'parent2languageSecond' => $parent2languageSecond, 'parent2email' => $parent2email, 'parent2phone1Type' => $parent2phone1Type, 'parent2phone1CountryCode' => $parent2phone1CountryCode, 'parent2phone1' => $parent2phone1, 'parent2phone2Type' => $parent2phone2Type, 'parent2phone2CountryCode' => $parent2phone2CountryCode, 'parent2phone2' => $parent2phone2, 'parent2profession' => $parent2profession, 'parent2employer' => $parent2employer, 'siblingName1' => $siblingName1, 'siblingDOB1' => $siblingDOB1, 'siblingSchool1' => $siblingSchool1, 'siblingSchoolJoiningDate1' => $siblingSchoolJoiningDate1, 'siblingName2' => $siblingName2, 'siblingDOB2' => $siblingDOB2, 'siblingSchool2' => $siblingSchool2, 'siblingSchoolJoiningDate2' => $siblingSchoolJoiningDate2, 'siblingName3' => $siblingName3, 'siblingDOB3' => $siblingDOB3, 'siblingSchool3' => $siblingSchool3, 'siblingSchoolJoiningDate3' => $siblingSchoolJoiningDate3, 'languageChoice' => $languageChoice, 'languageChoiceExperience' => $languageChoiceExperience, 'scholarshipInterest' => $scholarshipInterest, 'scholarshipRequired' => $scholarshipRequired, 'payment' => $payment, 'companyName' => $companyName, 'companyContact' => $companyContact, 'companyAddress' => $companyAddress, 'companyEmail' => $companyEmail, 'companyCCFamily' => $companyCCFamily, 'companyPhone' => $companyPhone, 'companyAll' => $companyAll, 'gibbonFinanceFeeCategoryIDList' => $gibbonFinanceFeeCategoryIDList, 'howDidYouHear' => $howDidYouHear, 'howDidYouHearMore' => $howDidYouHearMore, 'agreement' => $agreement, 'privacy' => $privacy, 'fields' => $fields, 'parent1fields' => $parent1fields, 'parent2fields' => $parent2fields, 'timestamp' => date('Y-m-d H:i:s'));
                    $sql = 'INSERT INTO gibbonApplicationForm SET surname=:surname, firstName=:firstName, preferredName=:preferredName, officialName=:officialName, nameInCharacters=:nameInCharacters, gender=:gender, dob=:dob, languageHomePrimary=:languageHomePrimary, languageHomeSecondary=:languageHomeSecondary, languageFirst=:languageFirst, languageSecond=:languageSecond, languageThird=:languageThird, countryOfBirth=:countryOfBirth, email=:email, homeAddress=:homeAddress, homeAddressDistrict=:homeAddressDistrict, homeAddressCountry=:homeAddressCountry, phone1Type=:phone1Type, phone1CountryCode=:phone1CountryCode, phone1=:phone1, phone2Type=:phone2Type, phone2CountryCode=:phone2CountryCode, phone2=:phone2, medicalInformation=:medicalInformation, sen=:sen, senDetails=:senDetails, gibbonSchoolYearIDEntry=:gibbonSchoolYearIDEntry, dateStart=:dateStart, gibbonYearGroupIDEntry=:gibbonYearGroupIDEntry, dayType=:dayType, referenceEmail=:referenceEmail, schoolName1=:schoolName1, schoolAddress1=:schoolAddress1, schoolGrades1=:schoolGrades1, schoolLanguage1=:schoolLanguage1, schoolDate1=:schoolDate1, schoolName2=:schoolName2, schoolAddress2=:schoolAddress2, schoolGrades2=:schoolGrades2, schoolLanguage2=:schoolLanguage2, schoolDate2=:schoolDate2, gibbonFamilyID=:gibbonFamilyID, parent1gibbonPersonID=:parent1gibbonPersonID, parent1title=:parent1title, parent1surname=:parent1surname, parent1firstName=:parent1firstName, parent1preferredName=:parent1preferredName, parent1officialName=:parent1officialName, parent1nameInCharacters=:parent1nameInCharacters, parent1gender=:parent1gender, parent1relationship=:parent1relationship, parent1languageFirst=:parent1languageFirst, parent1languageSecond=:parent1languageSecond, parent1email=:parent1email, parent1phone1Type=:parent1phone1Type, parent1phone1CountryCode=:parent1phone1CountryCode, parent1phone1=:parent1phone1, parent1phone2Type=:parent1phone2Type, parent1phone2CountryCode=:parent1phone2CountryCode, parent1phone2=:parent1phone2, parent1profession=:parent1profession, parent1employer=:parent1employer, parent2title=:parent2title, parent2surname=:parent2surname, parent2firstName=:parent2firstName, parent2preferredName=:parent2preferredName, parent2officialName=:parent2officialName, parent2nameInCharacters=:parent2nameInCharacters, parent2gender=:parent2gender, parent2relationship=:parent2relationship, parent2languageFirst=:parent2languageFirst, parent2languageSecond=:parent2languageSecond, parent2email=:parent2email, parent2phone1Type=:parent2phone1Type, parent2phone1CountryCode=:parent2phone1CountryCode, parent2phone1=:parent2phone1, parent2phone2Type=:parent2phone2Type, parent2phone2CountryCode=:parent2phone2CountryCode, parent2phone2=:parent2phone2, parent2profession=:parent2profession, parent2employer=:parent2employer, siblingName1=:siblingName1, siblingDOB1=:siblingDOB1, siblingSchool1=:siblingSchool1, siblingSchoolJoiningDate1=:siblingSchoolJoiningDate1, siblingName2=:siblingName2, siblingDOB2=:siblingDOB2, siblingSchool2=:siblingSchool2, siblingSchoolJoiningDate2=:siblingSchoolJoiningDate2, siblingName3=:siblingName3, siblingDOB3=:siblingDOB3, siblingSchool3=:siblingSchool3, siblingSchoolJoiningDate3=:siblingSchoolJoiningDate3, languageChoice=:languageChoice, languageChoiceExperience=:languageChoiceExperience, scholarshipInterest=:scholarshipInterest, scholarshipRequired=:scholarshipRequired, payment=:payment, companyName=:companyName, companyContact=:companyContact, companyAddress=:companyAddress, companyEmail=:companyEmail, companyCCFamily=:companyCCFamily, companyPhone=:companyPhone, companyAll=:companyAll, gibbonFinanceFeeCategoryIDList=:gibbonFinanceFeeCategoryIDList, howDidYouHear=:howDidYouHear, howDidYouHearMore=:howDidYouHearMore, agreement=:agreement, privacy=:privacy, fields=:fields, parent1fields=:parent1fields, parent2fields=:parent2fields, timestamp=:timestamp';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                //Last insert ID
                $AI = str_pad($connection2->lastInsertID(), 7, '0', STR_PAD_LEFT);
                $secureAI = sha1($AI.'X2J53ZGy'.$guid.$gibbonSchoolYearIDEntry);

                // PERSONAL DOCUMENTS
                $personalDocumentHandler = $container->get(PersonalDocumentHandler::class);
                $personalDocumentFail = false;

                $params = ['student' => true, 'applicationForm' => true];
                $personalDocumentHandler->updateDocumentsFromPOST('gibbonApplicationForm', $AI, $params, $personalDocumentFail);

                if ($gibbonFamily == 'FALSE') { // Only if there is no family
                    $params = ['parent' => true, 'applicationForm' => true, 'prefix' => 'parent1'];
                    $personalDocumentHandler->updateDocumentsFromPOST('gibbonApplicationFormParent1', $AI, $params, $personalDocumentFail);
    
                    if (empty($_POST['secondParent'])) {
                        $params = ['parent' => true, 'applicationForm' => true, 'prefix' => 'parent2'];
                        $personalDocumentHandler->updateDocumentsFromPOST('gibbonApplicationFormParent2', $AI, $params, $personalDocumentFail);
                    }
                }

                // Update the Application Form with a hash for looking up this record in the future
                $data = array('gibbonApplicationFormID' => $AI, 'gibbonApplicationFormHash' => $secureAI );
                $sql = 'UPDATE gibbonApplicationForm SET gibbonApplicationFormHash=:gibbonApplicationFormHash WHERE gibbonApplicationFormID=:gibbonApplicationFormID';
                $result = $connection2->prepare($sql);
                $result->execute($data);

                //Deal with family relationships
                if ($gibbonFamily == 'TRUE') {
                    $relationships = $_POST[$gibbonFamilyID.'-relationships'];
                    $relationshipsGibbonPersonIDs = $_POST[$gibbonFamilyID.'-relationshipsGibbonPersonID'];
                    $count = 0;
                    foreach ($relationships as $relationship) {

                            $data = array('gibbonApplicationFormID' => $AI, 'gibbonPersonID' => $relationshipsGibbonPersonIDs[$count], 'relationship' => $relationship);
                            $sql = 'INSERT INTO gibbonApplicationFormRelationship SET gibbonApplicationFormID=:gibbonApplicationFormID, gibbonPersonID=:gibbonPersonID, relationship=:relationship';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        ++$count;
                    }
                }

                //Deal with required documents
                $requiredDocuments = getSettingByScope($connection2, 'Application Form', 'requiredDocuments');
                if ($requiredDocuments != '' and $requiredDocuments != false) {
                    $fileCount = 0;
                    if (isset($_POST['fileCount'])) {
                        $fileCount = $_POST['fileCount'];
                    }

                    $fileUploader = new Gibbon\FileUploader($pdo, $gibbon->session);

                    for ($i = 0; $i < $fileCount; ++$i) {
                        if (empty($_FILES["file$i"]['tmp_name'])) continue;

                        $file = (isset($_FILES["file$i"]))? $_FILES["file$i"] : null;
                        $fileName = (isset($_POST["fileName$i"]))? $_POST["fileName$i"] : null;

                        // Upload the file, return the /uploads relative path
                        $attachment = $fileUploader->uploadFromPost($file, 'ApplicationDocument');

                        // Write files to database, if there is one
                        if (!empty($attachment)) {

                                $dataFile = array('gibbonApplicationFormID' => $AI, 'name' => $fileName, 'path' => $attachment);
                                $sqlFile = 'INSERT INTO gibbonApplicationFormFile SET gibbonApplicationFormID=:gibbonApplicationFormID, name=:name, path=:path';
                                $resultFile = $connection2->prepare($sqlFile);
                                $resultFile->execute($dataFile);
                        }
                    }
                }

                // Raise a new notification event
                $event = new NotificationEvent('Students', 'New Application Form');

                $event->addRecipient($session->get('organisationAdmissions'));
                $event->setNotificationText(sprintf(__('An application form has been submitted for %1$s.'), Format::name('', $preferredName, $surname, 'Student')));
                $event->setActionLink("/index.php?q=/modules/Students/applicationForm_manage_edit.php&gibbonApplicationFormID=$AI&gibbonSchoolYearID=$gibbonSchoolYearIDEntry&search=");

                $event->sendNotifications($pdo, $gibbon->session);


                //Email reference form link to referee
                $applicationFormRefereeLink = getSettingByScope($connection2, 'Students', 'applicationFormRefereeLink');
                if ($applicationFormRefereeLink != '' and $referenceEmail != '' and $session->get('organisationAdmissionsName') != '' and $session->get('organisationAdmissionsEmail') != '') {
                    //Prep message
                    $subject = __('Request For Reference');
                    $body = sprintf(__('To whom it may concern,%4$sThis email is being sent in relation to the application of a current or former student of your school: %1$s.%4$sIn assessing their application for our school, we would like to enlist your help in completing the following reference form: %2$s.<br/><br/>Please feel free to contact me, should you have any questions in regard to this matter.%4$sRegards,%4$s%3$s'), $officialName, "<a href='$applicationFormRefereeLink' target='_blank'>$applicationFormRefereeLink</a>", $session->get('organisationAdmissionsName'), '<br/><br/>');

                    $mail = $container->get(Mailer::class);
                    $mail->Subject = $subject;
                    $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                    $mail->AddAddress($referenceEmail);
                    $mail->renderBody('mail/email.twig.html', [
                        'title'  => $subject,
                        'body'   => $body,
                        'button' => [
                            'url'  => $applicationFormRefereeLink,
                            'text' => __('Click Here'),
                            'external' => true,
                        ],
                    ]);
                    $mail->Send();
                }

                $skipEmailNotification = (isset($_POST['skipEmailNotification']))? $_POST['skipEmailNotification'] : false;

                //Notify parent 1 of application status
                if (!empty($parent1email) && !$skipEmailNotification) {
                    $subject =  sprintf(__('%1$s Application Form Confirmation'), $session->get('organisationName'));
                    $body = sprintf(__('Dear Parent%1$sThank you for applying for a student place at %2$s.'), '<br/><br/>', $session->get('organisationName')).' ';
                    $body .= __('Your application was successfully submitted. Our admissions team will review your application and be in touch in due course.').'<br/><br/>';
                    $body .= __('You may continue submitting applications for siblings with the form below and they will be linked to your family data.').'<br/><br/>';
                    $body .= "<a href='{$URL}&id={$secureAI}'>{$URL}&id={$secureAI}</a><br/><br/>";
                    $body .= sprintf(__('In the meantime, should you have any questions please contact %1$s at %2$s.'), $session->get('organisationAdmissionsName'), $session->get('organisationAdmissionsEmail')).'<br/><br/>';

                    $mail = $container->get(Mailer::class);
                    $mail->Subject = $subject;
                    $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                    $mail->AddAddress($parent1email);
                    $mail->renderBody('mail/email.twig.html', [
                        'title'  => $subject,
                        'body'   => $body,
                        'button' => [
                            'url'  => "{$URL}&id={$secureAI}",
                            'text' => __('Add Another Application'),
                            'external' => true,
                        ],
                    ]);
                    $mail->Send();
                }

                // Handle Sibling Applications
                if (!empty($_POST['linkedApplicationFormID'])) {
                    $data = array( 'gibbonApplicationFormID' => $_POST['linkedApplicationFormID'] );
                    $sql = 'SELECT DISTINCT gibbonApplicationFormID FROM gibbonApplicationForm
                            LEFT JOIN gibbonApplicationFormLink ON (gibbonApplicationForm.gibbonApplicationFormID=gibbonApplicationFormLink.gibbonApplicationFormID1 OR gibbonApplicationForm.gibbonApplicationFormID=gibbonApplicationFormLink.gibbonApplicationFormID2)
                            WHERE (gibbonApplicationFormID=:gibbonApplicationFormID AND gibbonApplicationFormLinkID IS NULL)
                            OR gibbonApplicationFormID1=:gibbonApplicationFormID
                            OR gibbonApplicationFormID2=:gibbonApplicationFormID';
                    $resultLinked = $pdo->executeQuery($data, $sql);

                    if ($resultLinked && $resultLinked->rowCount() > 0) {
                        // Create a new link to each existing form
                        while ($linkedApplication = $resultLinked->fetch()) {
                            $data = array( 'gibbonApplicationFormID1' => $AI, 'gibbonApplicationFormID2' => $linkedApplication['gibbonApplicationFormID'] );
                            $sql = "INSERT INTO gibbonApplicationFormLink SET gibbonApplicationFormID1=:gibbonApplicationFormID1, gibbonApplicationFormID2=:gibbonApplicationFormID2 ON DUPLICATE KEY UPDATE timestamp=NOW()";
                            $resultNewLink = $pdo->executeQuery($data, $sql);
                        }
                    }
                }

                //Attempt payment if everything is set up for it
                $applicationFee = getSettingByScope($connection2, 'Application Form', 'applicationFee');
                $enablePayments = getSettingByScope($connection2, 'System', 'enablePayments');
                $paypalAPIUsername = getSettingByScope($connection2, 'System', 'paypalAPIUsername');
                $paypalAPIPassword = getSettingByScope($connection2, 'System', 'paypalAPIPassword');
                $paypalAPISignature = getSettingByScope($connection2, 'System', 'paypalAPISignature');

                if ($applicationFee > 0 and is_numeric($applicationFee) and $enablePayments == 'Y' and $paypalAPIUsername != '' and $paypalAPIPassword != '' and $paypalAPISignature != '') {
                    $session->set('gatewayCurrencyNoSupportReturnURL', $session->get('absoluteURL')."/index.php?q=/modules/Students/applicationForm.php&return=success4&id=$secureAI");
                    $URL = $session->get('absoluteURL')."/lib/paypal/expresscheckout.php?Payment_Amount=$applicationFee&return=".urlencode("modules/Students/applicationFormProcess.php?return=success1&id=$secureAI&applicationFee=$applicationFee").'&fail='.urlencode("modules/Students/applicationFormProcess.php?return=success2&id=$secureAI&applicationFee=$applicationFee");
                    header("Location: {$URL}");
                } else {
                    $URL .= "&return=success0&id=$secureAI";
                    header("Location: {$URL}");
                }
            }
        }
    }
    //IF ID IS SET WE ARE JUST RETURNING TO FINALISE PAYMENT AND RECORD OF PAYMENT, SO LET'S DO IT.
    else {
        //Get returned paypal tokens, ids, etc
        $paymentMade = 'N';
        if ($_GET['return'] == 'success1') {
            $paymentMade = 'Y';
        }
        $paymentToken = null;
        if (isset($_GET['token'])) {
            $paymentToken = $_GET['token'];
        }
        $paymentPayerID = null;
        if (isset($_GET['PayerID'])) {
            $paymentPayerID = $_GET['PayerID'];
        }
        $gibbonApplicationFormID = null;
        if (isset($_GET['id'])) {
            // Find the ID based on the hash provided for added security
            $data = array( 'gibbonApplicationFormHash' => $_GET['id'] );
            $sql = "SELECT gibbonApplicationFormID FROM gibbonApplicationForm WHERE gibbonApplicationFormHash=:gibbonApplicationFormHash";
            $resultID = $pdo->executeQuery($data, $sql);

            if ($resultID && $resultID->rowCount() == 1) {
                $gibbonApplicationFormID = $resultID->fetchColumn(0);
            }
        }
        $applicationFee = null;
        if (isset($_GET['applicationFee'])) {
            $applicationFee = $_GET['applicationFee'];
        }

        //Get email parameters ready to send messages for to admissions for payment problems
        $to = $session->get('organisationAdmissionsEmail');
        $subject = $session->get('organisationNameShort').' Gibbon Application Form Payment Issue';

        //Check return values to see if we can proceed
        if ($paymentToken == '' or $gibbonApplicationFormID == '' or $applicationFee == '') {
            $body = __('Payment via PayPal may or may not have been successful, but has not been recorded either way due to a system error. Please check your PayPal account for details. The following may be useful:')."<br/><br/>Payment Token: $paymentToken<br/><br/>Payer ID: $paymentPayerID<br/><br/>Application Form ID: $gibbonApplicationFormID<br/><br/>Application Fee: $applicationFee<br/><br/>".$session->get('systemName').' '.__('Admissions Administrator');

            $mail = $container->get(Mailer::class);
            $mail->Subject = $subject;
            $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
            $mail->AddAddress($to);
            $mail->renderBody('mail/email.twig.html', [
                'title'  => $subject,
                'body'   => $body,
            ]);

            $mail->Send();

            //Success 2
            $URL .= '&return=success2&id='.$_GET['id'];
            header("Location: {$URL}");
            exit();
        } else {
            //PROCEED AND FINALISE PAYMENT
            require '../../lib/paypal/paypalfunctions.php';

            //Ask paypal to finalise the payment
            $confirmPayment = confirmPayment($guid, $applicationFee, $paymentToken, $paymentPayerID);

            $ACK = $confirmPayment['ACK'];
            $paymentTransactionID = $confirmPayment['PAYMENTINFO_0_TRANSACTIONID'] ?? '';
            $paymentReceiptID = $confirmPayment['PAYMENTINFO_0_RECEIPTID'] ?? '';

            //Payment was successful. Yeah!
            if ($ACK == 'Success') {
                $updateFail = false;

                //Save payment details to gibbonPayment
                $gibbonPaymentID = setPaymentLog($connection2, $guid, 'gibbonApplicationForm', $gibbonApplicationFormID, 'Online', 'Complete', $applicationFee, 'Paypal', 'Success', $paymentToken, $paymentPayerID, $paymentTransactionID, $paymentReceiptID);

                //Link gibbonPayment record to gibbonApplicationForm, and make note that payment made
                if ($gibbonPaymentID != '') {
                    try {
                        $data = array('paymentMade' => $paymentMade, 'gibbonPaymentID' => $gibbonPaymentID, 'gibbonApplicationFormID' => $gibbonApplicationFormID);
                        $sql = 'UPDATE gibbonApplicationForm SET paymentMade=:paymentMade, gibbonPaymentID=:gibbonPaymentID WHERE gibbonApplicationFormID=:gibbonApplicationFormID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $updateFail = true;
                    }
                } else {
                    $updateFail = true;
                }

                if ($updateFail == true) {
                    $body = __('Payment via PayPal was successful, but has not been recorded due to a system error. Please check your PayPal account for details. The following may be useful:')."<br/><br/>Payment Token: $paymentToken<br/><br/>Payer ID: $paymentPayerID<br/><br/>Application Form ID: $gibbonApplicationFormID<br/><br/>Application Fee: $applicationFee<br/><br/>".$session->get('systemName').' '.__('Admissions Administrator');

                    $mail = $container->get(Mailer::class);
                    $mail->Subject = $subject;
                    $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                    $mail->AddAddress($to);
                    $mail->renderBody('mail/email.twig.html', [
                        'title'  => $subject,
                        'body'   => $body,
                    ]);

                    $mail->Send();

                    $URL .= '&return=success3&id='.$_GET['id'];
                    header("Location: {$URL}");
                    exit;
                }

                $URL .= '&return=success1&id='.$_GET['id'];
                header("Location: {$URL}");
            } else {
                $updateFail = false;

                //Save payment details to gibbonPayment
                $gibbonPaymentID = setPaymentLog($connection2, $guid, 'gibbonApplicationForm', $gibbonApplicationFormID, 'Online', 'Failure', $applicationFee, 'Paypal', 'Failure', $paymentToken, $paymentPayerID, $paymentTransactionID, $paymentReceiptID);

                //Link gibbonPayment record to gibbonApplicationForm, and make note that payment made
                if ($gibbonPaymentID != '') {
                    try {
                        $data = array('paymentMade' => $paymentMade, 'gibbonPaymentID' => $gibbonPaymentID, 'gibbonApplicationFormID' => $gibbonApplicationFormID);
                        $sql = 'UPDATE gibbonApplicationForm SET paymentMade=:paymentMade, gibbonPaymentID=:gibbonPaymentID WHERE gibbonApplicationFormID=:gibbonApplicationFormID';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $updateFail = true;
                    }
                } else {
                    $updateFail = true;
                }

                if ($updateFail == true) {
                    $body = __('Payment via PayPal was unsuccessful, and has also not been recorded due to a system error. Please check your PayPal account for details. The following may be useful:')."<br/><br/>Payment Token: $paymentToken<br/><br/>Payer ID: $paymentPayerID<br/><br/>Application Form ID: $gibbonApplicationFormID<br/><br/>Application Fee: $applicationFee<br/><br/>".$session->get('systemName').' '.__('Admissions Administrator');

                    $mail = $container->get(Mailer::class);
                    $mail->Subject = $subject;
                    $mail->SetFrom($session->get('organisationAdmissionsEmail'), $session->get('organisationAdmissionsName'));
                    $mail->AddAddress($to);
                    $mail->renderBody('mail/email.twig.html', [
                        'title'  => $subject,
                        'body'   => $body,
                    ]);

                    $mail->Send();

                    //Success 2
                    $URL .= '&return=success2&id='.$_GET['id'];
                    header("Location: {$URL}");
                    exit;
                }

                //Success 2
                $URL .= '&return=success2&id='.$_GET['id'];
                header("Location: {$URL}");
            }
        }
    }
}
