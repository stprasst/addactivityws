<?php
defined('MOODLE_INTERNAL') || die();

use core_availability\api as availability_api;

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/url/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

class local_addactivityws_external extends external_api
{

    public static function add_activity_parameters()
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (starting from 0)'),
            'activitytype' => new external_value(PARAM_ALPHA, 'Activity type: "assign" or "url"'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Activity description', VALUE_DEFAULT, ''),
            // Dates are timestamps (unix time)
            'allowfrom' => new external_value(PARAM_INT, 'Allow submission from (assign only)', VALUE_DEFAULT, 0),
            'duedate' => new external_value(PARAM_INT, 'Due date (assign only)', VALUE_DEFAULT, 0),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date (assign only)', VALUE_DEFAULT, 0),
            // Restrict access
            'restrictfrom' => new external_value(PARAM_INT, 'Restrict access start date', VALUE_DEFAULT, 0),
            'restricttill' => new external_value(PARAM_INT, 'Restrict access end date', VALUE_DEFAULT, 0),
            'restrictgroup' => new external_value(PARAM_INT, 'Restrict access group ID', VALUE_DEFAULT, 0),
            // Completion tracking: 0 = no tracking, 1 = automatic on view, 2 = manual
            'completion' => new external_value(PARAM_INT, 'Completion tracking mode', VALUE_DEFAULT, 0),
            // URL specific param (if activitytype=url)
            'url' => new external_value(PARAM_URL, 'URL for url activity', VALUE_DEFAULT, ''),
        ]);
    }

    public static function add_activity($courseid, $sectionnum, $activitytype, $name, $intro = '',
                                        $allowfrom = 0, $duedate = 0, $cutoffdate = 0,
                                        $restrictfrom = 0, $restricttill = 0, $restrictgroup = 0,
                                        $completion = 0,
                                        $url = '')
    {
        global $DB, $USER;

        // Parameter validation
        $params = self::validate_parameters(self::add_activity_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'activitytype' => $activitytype,
            'name' => $name,
            'intro' => $intro,
            'allowfrom' => $allowfrom,
            'duedate' => $duedate,
            'cutoffdate' => $cutoffdate,
            'restrictfrom' => $restrictfrom,
            'restricttill' => $restricttill,
            'restrictgroup' => $restrictgroup,
            'completion' => $completion,
            'url' => $url,
        ]);

        require_login($courseid);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = context_course::instance($courseid);

        // Capability check
        if (!has_capability('moodle/course:manageactivities', $context)) {
            throw new moodle_exception('nopermissions', '', '', 'manage activities in course');
        }

        // Get course sections
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        if (!isset($sections[$sectionnum])) {
            throw new moodle_exception('invalidsection', 'local_addactivityws');
        }
        $sectioninfo = $sections[$sectionnum];

        // Validate activity type
        if ($activitytype !== 'assign' && $activitytype !== 'url') {
            throw new moodle_exception('invalidactivitytype', 'local_addactivityws');
        }

        // Get Module record From database
        $modulerecord = $DB->get_record('modules', ['name' => $activitytype], '*', MUST_EXIST);

        // Siapkan data umum untuk modul
        $moduledata = new stdClass();
        $moduledata->name = $name;
        $moduledata->intro = $intro;
        $moduledata->introformat = FORMAT_HTML;
        $moduledata->course = $courseid;
        $moduledata->section = $sectioninfo->id;
        $moduledata->module = $modulerecord->id;
        $moduledata->modulename = $activitytype;
        $moduledata->instance = 0;
        $moduledata->visible = 1;
        $moduledata->groupmode = 0;
        $moduledata->groupingid = 0;
        $moduledata->completion = $completion;

        // Tambahkan properti spesifik per modul
        if ($activitytype === 'assign') {
            $moduledata->allowsubmissionsfromdate = ($allowfrom > 0) ? $allowfrom : 0;
            $moduledata->duedate = ($duedate > 0) ? $duedate : 0;
            $moduledata->cutoffdate = ($cutoffdate > 0) ? $cutoffdate : 0;
            $moduledata->grade = 100;
            $moduledata->assignsubmission_onlinetext_enabled = 1;
            $moduledata->assignsubmission_file_enabled = 1;
            $moduledata->requiresubmissionstatement = 0;
            $moduledata->sendnotifications = 0;
            $moduledata->sendlatenotifications = 0;
            $moduledata->assignsubmission_onlinetext_wordlimit = 0;
            $moduledata->assignsubmission_onlinetext_wordlimitenabled = 0;
            $moduledata->assignsubmission_file_maxfiles = 20;
            $moduledata->assignsubmission_file_maxsizebytes = 0;
            $moduledata->submissiondrafts = 1; // 1 = Mahasiswa harus klik tombol "Submit"
            $moduledata->gradingduedate = 0; // "Remind me to grade by", 0 = dinonaktifkan
            $moduledata->teamsubmission = 0; // Bukan tugas kelompok
            $moduledata->requireallteammemberssubmit = 0; // Tidak berlaku jika bukan tugas kelompok
            $moduledata->blindmarking = 0; // Penilaian tidak anonim
            $moduledata->markingworkflow = 0; // Tidak menggunakan alur kerja penilaian
            $moduledata->markingallocation = 0; // Tidak menggunakan alokasi penilai


            if ($completion == 1) { // 'Show activity as complete when conditions are met'
                $moduledata->completionsubmit = 1;
            } else {
                // 0 (None) or 2 (Manual)
                $moduledata->completionsubmit = 0;
            }
            // =========================================================

        } else if ($activitytype === 'url') {
            if (empty($url)) {
                throw new moodle_exception('missingparam', '', '', 'url');
            }
            $moduledata->externalurl = $url;
            $moduledata->display = 0; // Default display option
            $moduledata->completion = $completion;
        }

        try {
            $cm = add_moduleinfo($moduledata, $course);
            $newcmid = $cm->id;
            $instance = $DB->get_record($activitytype, ['id' => $cm->instance]);
        } catch (Exception $e) {
            debugging('Error adding activity: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new moodle_exception('erroraddingactivity', '', '', $e->getMessage());
        }

        if ($restrictfrom > 0 || $restricttill > 0 || $restrictgroup > 0) {

            require_once($CFG->dirroot . '/lib/accesslib.php');

            /** @var cm_info */
            $cm = get_coursemodule_from_id('', $newcmid, 0, false, MUST_EXIST);

            list($conditions,) = \core_availability\info::get_conditions_from_json([]);

            /** @var \core_availability\api */

            $availabilityconditions = [];

            if ($restrictfrom > 0) {
                // Date from condition
                $availabilityconditions[] = [
                    'type' => 'date',
                    'op' => '>=',
                    'time' => (int)$restrictfrom,
                ];
            }

            if ($restricttill > 0) {
                // Date until condition
                $availabilityconditions[] = [
                    'type' => 'date',
                    'op' => '<=',
                    'time' => (int)$restricttill,
                ];
            }

            if ($restrictgroup > 0) {
                // Group restriction condition
                $availabilityconditions[] = [
                    'type' => 'group',
                    'id' => (int)$restrictgroup,
                ];
            }


            if (!empty($availabilityconditions)) {
                $availabilityinfoarray = ['op' => '&', 'c' => $availabilityconditions];
                set_course_module_availability($newcmid, json_encode($availabilityinfoarray));
            }

        }

        return [
            'status' => true,
            'message' => get_string('success', 'local_addactivityws'),
            'cmid' => $newcmid,
            'instanceid' => isset($instance->id) ? $instance->id : null,
        ];
    }


    public static function add_activity_returns()
    {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the operation'),
            'message' => new external_value(PARAM_TEXT, 'Operation message'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'instanceid' => new external_value(PARAM_INT, 'Module instance ID'),
        ]);
    }

    public static function get_course_sections_parameters()
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function get_course_sections($courseid)
    {
        global $DB;

        $params = self::validate_parameters(self::get_course_sections_parameters(), ['courseid' => $courseid]);

        if (!$course = $DB->get_record('course', ['id' => $params['courseid']], '*', IGNORE_MISSING)) {
            throw new moodle_exception('invalidcourse', 'local_addactivityws');
        }

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();

        $result = [];

        foreach ($sections as $key => $section) {
            $result[] = [
                'sectionnum' => $key,
                'id' => $section->id,
                'name' => isset($section->name) ? $section->name : '',
                'visible' => $section->visible,
            ];
        }

        return $result;
    }

    public static function get_course_sections_returns()
    {
        return new external_multiple_structure(
            new external_single_structure([
                'sectionnum' => new external_value(PARAM_INT, 'Section number'),
                'id' => new external_value(PARAM_INT, 'Section ID'),
                'name' => new external_value(PARAM_TEXT, 'Section name', VALUE_OPTIONAL),
                'visible' => new external_value(PARAM_INT, 'Visible status'),
            ])
        );
    }

}