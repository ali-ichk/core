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

namespace Gibbon\Module\Students;

use Gibbon\Services\Format;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\Timetable\TimetableDayDateGateway;
use Gibbon\Domain\School\SchoolYearSpecialDayGateway;
use Gibbon\Domain\Attendance\AttendanceLogPersonGateway;

/**
 * Student Attendance Status
 *
 * @version v31
 * @since   v31
 */
class StudentAttendanceStatus
{
    protected $pdo;
    protected $attendanceLogGateway;
	protected $timetableGateway;
    protected $schoolYearSpecialDayGateway;

    public function __construct(
        Connection $pdo,
        AttendanceLogPersonGateway $attendanceLogGateway,
		TimetableDayDateGateway $timetableGateway,
        SchoolYearSpecialDayGateway $schoolYearSpecialDayGateway
    ) {
        $this->pdo = $pdo;
        $this->attendanceLogGateway = $attendanceLogGateway;
		$this->timetableGateway = $timetableGateway;
        $this->schoolYearSpecialDayGateway = $schoolYearSpecialDayGateway;
    }
    
    public function getCurrentAttendanceStatus($gibbonSchoolYearID, $gibbonPersonID, $preferredName, $surname)
    {
        $today = date('Y-m-d');
        $lastNonClassAttendanceLog = $this->attendanceLogGateway->selectNonClassAttendanceLogsByPersonAndDate($gibbonPersonID, $today)->fetch();
        $absent = '';

        if (!empty($lastNonClassAttendanceLog)) {
            $absent = $lastNonClassAttendanceLog['type'] == 'Absent' ?? false;
            
            if ($absent) {
                $absenceMessage = __('{name} is {type} today.', [
                    'name' =>Format::name('', $preferredName, $surname, 'Student'),
                    'type' => __($lastNonClassAttendanceLog['type'])]);
            } else {
                $absenceMessage = __('{name} is {type} today.', [
                    'name' =>Format::name('', $preferredName, $surname, 'Student'),
                    'type' => __($lastNonClassAttendanceLog['type'])]);
                $absenceMessage .= '<br/><br/><ul>';

                $isStudentOffTimetableToday =  $this->schoolYearSpecialDayGateway->getIsStudentOffTimetableByDate($gibbonSchoolYearID, $gibbonPersonID, $today);

                if ($isStudentOffTimetableToday) {
                    $absenceMessage .= '<li>'.__('The student is off timetable today for ').$isStudentOffTimetableToday['name'].'</li>';                           
                } else {
                    $classes = $this->timetableGateway->selectTimetabledPeriodsByPersonAndDateRange($gibbonPersonID, $today, $today)->fetchAll();

                    $currentTime = date('H:i:s');
                    $currentClass = null;

                    foreach ($classes as $class) {
                        if ($class['timeStart'] <= $currentTime and $class['timeEnd'] > $currentTime) {
                            $currentClass = $class;
                            break;
                        }
                    }

                    if ($currentClass) {
                        // Handle room changes
                        if (!empty($currentClass['spaceChanged'])) {
                            $currentClass['roomName'] = $currentClass['roomNameChange'] ?? '';
                        }
                                    
                        $currentClassAttendance = $this->attendanceLogGateway->selectClassAttendanceLogsByPersonAndDate($currentClass['gibbonCourseClassID'], $gibbonPersonID, $today)->fetch();

                        $absenceMessage .= '<li>'.__('Currently, {type} in {class}, {room}.', [
                            'type'  => $currentClassAttendance['type'] ?? 'attendance has not been recorded yet',
                            'class' => Format::courseClassName($currentClass['courseNameShort'], $class['classNameShort']),
                            'room'  => $currentClass['roomName']
                        ]).'</li>';
                    }
                }                                
            }
        } else {
            $absenceMessage = __('No attendance has been recorded for {name} today yet.', [
                'name' =>Format::name('', $preferredName, $surname, 'Student')]);
        }

        return $absent ? Format::alert($absenceMessage, 'error') : Format::alert($absenceMessage, 'message');
    }
}