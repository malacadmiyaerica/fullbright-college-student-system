<?php
/**
 * Fullbright College - Student Management System
 * Demo / static content providers.
 *
 * This module is Student-only, so announcements, school events and
 * teacher projects are shown as sample content. (An Admin/Teacher
 * module would manage them later.)
 */

require_once __DIR__ . '/config.php';

/**
 * School announcements shown on the dashboard.
 */
function school_announcements(): array {
    return [
        ['date' => 'Aug 15, 2026', 'title' => 'Midterm Examination Schedule Released',
         'body' => 'Midterm exams for the first semester run September 1–5. Check your section schedule for the exact rooms.'],
        ['date' => 'Aug 10, 2026', 'title' => 'Enrollment for the Current School Year',
         'body' => 'New and continuing students must complete enrollment before classes formally start. Contact the Registrar for concerns.'],
        ['date' => 'Aug 05, 2026', 'title' => 'Scholarship Application Window',
         'body' => 'Academic and athletic scholarship applications are open until August 25 at the Guidance Office.'],
    ];
}

/**
 * School events shown on the dashboard.
 */
function school_events(): array {
    return [
        ['date' => 'Sep 21, 2026', 'title' => 'Fullbright Foundation Day', 'venue' => 'Main Campus Grounds'],
        ['date' => 'Oct 12, 2026', 'title' => 'Intramurals 2026', 'venue' => 'University Gymnasium'],
        ['date' => 'Nov 10, 2026', 'title' => 'Career Guidance Seminar', 'venue' => 'Lecture Hall B'],
    ];
}

/**
 * Sample projects posted by teachers.
 */
function teacher_projects(): array {
    return [
        ['subject' => 'Programming 1', 'teacher' => 'Prof. M. Santos', 'title' => 'Personal Website Showcase',
         'desc' => 'Build a personal portfolio website using only HTML and CSS. Submit via Google Classroom on or before Sept 30.'],
        ['subject' => 'Data Structures', 'teacher' => 'Engr. R. Cruz', 'title' => 'Linked List Visualizer',
         'desc' => 'Implement a linked list visualizer in your chosen language and present a short demo to the class.'],
        ['subject' => 'Networking 1', 'teacher' => 'Engr. L. Ramos', 'title' => 'LAN Topology Design',
         'desc' => 'Submit a packet-tracer file with your proposed LAN design for the college computer laboratory.'],
    ];
}

/**
 * Weekly class schedule placeholder for a program acronym.
 * Used for the section schedule display. Row = time slot, column = day.
 */
function section_schedule(string $acronym): array {
    $subjects = [
        'IT'  => ['IT 101 – Computing Fundamentals', 'IT 102 – Web Development', 'IT 103 – Database Management 1',
                  'IT 104 – Networking 1',          'NSTP 1',                   'PE 1 – Self-Testing Activities'],
        'CS'  => ['CS 111 – Intro to Computing',    'CS 112 – Discrete Structures','CS 113 – Programming 2',
                  'MATH 1 – College Algebra',        'NSTP 1',                   'PE 1 – Self-Testing Activities'],
        'CpE' => ['CpE 101 – Introduction to ECE Engineering', 'CpE 102 – Computer Programming 1', 'CpE 103 – Circuit Analysis 1',
                  'MATH 2 – Trigonometry',           'NSTP 1',                   'PE 1 – Self-Testing Activities'],
        'ECE' => ['ECE 101 – Electronic Fundamentals', 'ECE 102 – Analog Circuits 1', 'ECE 103 – Digital Logic',
                  'MATH 2 – Trigonometry',            'NSTP 1',                   'PE 1 – Self-Testing Activities'],
    ];
    $fallback = ['GEN 101 – General Education 1', 'GEN 102 – General Education 2', 'MATH 1 – College Algebra',
                 'NSTP 1', 'PE 1 – Self-Testing Activities', 'FIL 1 – Komunikasyon sa Akademikong Filipino'];
    $set  = $subjects[$acronym] ?? $fallback;
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    $times = ['7:30 – 9:00 AM', '9:00 – 10:30 AM', '10:30 AM – 12:00 NN', '1:00 – 2:30 PM', '2:30 – 4:00 PM'];
    $schedule = [];

    foreach ($times as $ti => $time) {
        $row = ['time' => $time];
        foreach ($days as $di => $day) {
            $index = ($ti + $di) % count($set);
            $room = 'Rm ' . (floor(($ti * 5 + $di) / 4) + 201);
            // Lunch break on the 3rd slot is handled by leaving an empty column.
            $row[$day] = ($time === '10:30 AM – 12:00 NN' && ($di % 2 === 0))
                ? '' 
                : $set[$index] . '<br><small>' . $room . '</small>';
        }
        $schedule[] = $row;
    }

    return $schedule;
}

/**
 * Human readable section description, e.g. "Information Technology, 1st Year, Section A".
 */
function section_description(string $section): array {
    $letter = preg_match('/([A-Z])$/', $section, $m) ? $m[1] : 'A';
    $yearNo = preg_match('/(\d)/', $section, $m) ? (int) $m[1] : 1;

    $acronym = preg_replace('/[0-9].*$/', '', $section);
    $programNames = [
        'IT'  => 'Information Technology',
        'CS'  => 'Computer Science',
        'CpE' => 'Computer Engineering',
        'ECE' => 'Electronics & Communication Engineering',
    ];

    $ord = ['0th', '1st', '2nd', '3rd', '4th'];
    return [
        'acronym' => $acronym,
        'year'    => $yearNo,
        'letter'  => $letter,
        'label'   => ($programNames[$acronym] ?? 'General') . ', ' . ($ord[$yearNo] ?? $yearNo . 'th') . ' Year, Section ' . $letter,
    ];
}