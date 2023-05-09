<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/completionlib.php');

global $PAGE;

// Specify what should be set here.
$completiondata = [
    'completion' => COMPLETION_TRACKING_AUTOMATIC,
    'completionunlocked' => 1,
    'completionview' => COMPLETION_VIEW_NOT_REQUIRED,
    'completionusegrade' => 1,
    'completionpassgrade' => 1,
    'completionsubmit' => 0,
    'completionexpected' => 0,
];

// Use all courseids, or replace with array of specific course ids.
$courseids = get_all_courseids();

// Specify module types.
$modtypes = ['assign', 'quiz'];

// Script logic.

// First login as admin user.
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());

$timestart = time();

foreach ($courseids as $courseid) {
    cli_heading("Processing course id " . $courseid);

    $modules = get_mods_of_type_in_course($courseid, $modtypes);
    $modids = array_column($modules, 'id');

    $data = array_merge($completiondata, [
        'id' => $courseid,
        'modids' => $modids
    ]);

    $manager = new \core_completion\manager($courseid);
    $manager->apply_default_completion((object) $data, true);
}

$scripttime = time() - $timestart;

cli_writeln("Done - took {$scripttime} seconds");
die;

function get_mods_of_type_in_course(int $courseid, array $modtypes) {
    $manager = new \core_completion\manager($courseid);
    $allmodules = $manager->get_activities_and_resources();

    $modules = [];
    foreach ($allmodules->modules as $module) {
        if ($module->canmanage && in_array($module->name, $modtypes)) {
            $modules[$module->id] = $module;
        }
    }

    return $modules;
}

function get_all_courseids() {
    global $DB;
    $ids = $DB->get_records('course', [], 'id');
    return array_column($ids, 'id');
}
