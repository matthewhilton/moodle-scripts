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
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/grade/querylib.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/completionlib.php');

// Use all courseids, or replace with array of specific course ids.
$courseids = get_all_courseids();

// Specify module types and their new gradepass.
$modtypes = [
    'assign' => 2,
    'quiz' => 99
];

// First login as admin user.
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());

$timestart = time();

foreach ($courseids as $courseid) {
    cli_heading("Processing course id " . $courseid);

    $modinstances = get_course_mods($courseid);

    foreach ($modinstances as $cm) {
        try {
            if (!in_array($cm->modname, array_keys($modtypes))) {
                continue;
            }

            cli_writeln("Processing cmid: {$cm->id} mod_{$cm->modname} instance {$cm->instance}");

            // Update gradepass on gradeitems associated with this mod.
            $cminfo = cm_info::create($cm);
            $gradeitems = grade_get_grade_items_for_activity($cminfo);
            $newgradepass = $modtypes[$cm->modname];

            if ($gradeitems === false) {
                cli_writeln("cmid: {$cm->id} has no grade items");
                continue;
            }

            foreach ($gradeitems as $gradeitem) {
                $gradeitem->gradepass = $newgradepass;
                $gradeitem->update();
            }

            // Trigger completion update.
            $modinfo = course_modinfo::instance($courseid);
            rebuild_course_cache($courseid, true);
            $modinfo = get_fast_modinfo($courseid);
            $completion = new \completion_info($modinfo->get_course());
            $completion->reset_all_state($modinfo->get_cm($cm->id));
        } catch (Throwable $e) {
            cli_writeln("Error with cmid {$cm->id}: {$e->getMessage()}");
        }
    }
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
