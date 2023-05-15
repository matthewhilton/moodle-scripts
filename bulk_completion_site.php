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
    global $PAGE;
    cli_heading("Processing course id " . $courseid);

    // These are needed so the completion bulk edit form can determine
    // What rules are available for validation. Also sets the $COURSE properly.
    $PAGE->reset_theme_and_output();
    require_login($courseid);
    $PAGE->initialise_theme_and_output();

    // Get the modules of $modtypes in this course.
    $cms = get_mods_of_type_in_course($courseid, $modtypes);

    if (empty($cms)) {
        continue;
    }

    $manager = new \core_completion\manager($courseid);

    // Process each coursemodule individually.
    // While slower, it means if a coursemodule has an error we can just skip it.
    foreach ($cms as $cm) {
        cli_writeln("Processing cmid: {$cm->id} mod_{$cm->modname} instance {$cm->instance}: {$cm->name}");
        // Setup the data to submit.
        $datatosubmit = array_merge($completiondata, [
            'id' => $courseid,
            'courseid' => $courseid,
            'cms' => [$cm]
        ]);

        try {
            $data = array_merge($completiondata, [
                'cmid' => [$cm->id]
            ]);
            $manager->apply_completion((object) $data, false);
        } catch (Throwable $e) {
            cli_writeln("Threw exception - skipping:");
            cli_writeln($e->getMessage());
        }
    }
}

$scripttime = time() - $timestart;

cli_writeln("Done - took {$scripttime} seconds");
die;

function get_mods_of_type_in_course(int $courseid, array $modtypes) {
    $coursemods = [];

    foreach ($modtypes as $modname) {
        $modules = get_coursemodules_in_course($modname, $courseid);

        // Turn each mod info classes.
        $moduleinfos = array_map(function($m) {
            try {
                return cm_info::create($m);
            } catch (Throwable $e) {
                cli_writeln("Error with cmid: {$m->id}: " . $e->getMessage());
                return null;
            }
        }, $modules);

        // Filter out nulls.
        $moduleinfos = array_filter($moduleinfos);

        $coursemods = [...$coursemods, ...$moduleinfos];
    }

    return $coursemods;
}

function get_all_courseids() {
    global $DB;
    $ids = $DB->get_records('course', [], 'id');
    return array_column($ids, 'id');
}
