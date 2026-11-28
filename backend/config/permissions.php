<?php

/*
| Permission catalogue and default roles. Permissions are synced into the database by
| PermissionSeeder; roles listed here are created if missing, and institutions edit them
| from the admin screens afterwards. Later modules append their own permissions here.
*/

return [
    'permissions' => [
        // Identity and access
        'users.view' => ['identity', 'View user accounts'],
        'users.manage' => ['identity', 'Create and update user accounts', true],
        'roles.manage' => ['identity', 'Create and edit roles and their permissions', true],
        'grants.manage' => ['identity', 'Grant and revoke roles within a scope', true],
        'persons.view' => ['identity', 'View person master records', true],
        'persons.manage' => ['identity', 'Create and correct person master records', true],
        // Institution
        'org_units.manage' => ['institution', 'Manage the organisational structure'],
        'sessions.manage' => ['institution', 'Create academic sessions and semesters, open and close processes'],
        'calendar.manage' => ['institution', 'Manage academic calendar events'],
        'settings.manage' => ['institution', 'Manage institution settings and branding'],
        // Oversight
        'audit.view' => ['audit', 'View audit trails', true],
        'workflows.manage' => ['workflow', 'Configure approval workflows'],
        'notifications.manage' => ['notifications', 'Manage notification templates'],
        // Academic setup (M2)
        'academic_config.manage' => ['programmes', 'Manage award types, study modes and programme categories'],
        'programmes.manage' => ['programmes', 'Create and update programmes'],
        'courses.manage' => ['curriculum', 'Create and update courses and their prerequisites'],
        'curricula.view' => ['curriculum', 'View curriculum versions'],
        'curricula.manage' => ['curriculum', 'Draft curriculum versions and submit them for approval'],
        'curricula.endorse' => ['curriculum', 'Endorse curriculum versions at department or faculty level'],
        'curricula.approve' => ['curriculum', 'Give final approval to curriculum versions'],
        'curricula.publish' => ['curriculum', 'Publish and retire approved curriculum versions'],
        'offerings.view' => ['offerings', 'View course offerings'],
        'offerings.manage' => ['offerings', 'Create, generate, publish and cancel course offerings'],
        'offerings.allocate' => ['offerings', 'Allocate and end staff assignments on course offerings'],
        'staff.view' => ['staff', 'View staff records'],
        'staff.manage' => ['staff', 'Create and update staff records', true],
        // Admissions to matriculation (M3)
        'applications.apply' => ['admissions', 'Apply for admission (applicant portal)'],
        'admissions.configure' => ['admissions', 'Configure admission lists, exercises, clearance units, matric formats and letters'],
        'admissions.view' => ['admissions', 'View applications for programmes in scope', true],
        'admissions.process' => ['admissions', 'Screen, recommend, hold or reject applications and verify documents', true],
        'admissions.change_programme' => ['admissions', 'Move an applicant to another programme', true],
        'admissions.lists' => ['admissions', 'Prepare admission lists and submit them for approval'],
        'admissions.recommend' => ['admissions', 'Recommend admission lists (committee stage)'],
        'admissions.approve' => ['admissions', 'Give final approval to admission lists'],
        'admission_payments.verify' => ['admissions', 'Verify application and acceptance fee payments', true],
        'clearance.review' => ['admissions', 'Review clearance items for a unit'],
        'students.view' => ['registry', 'View student records', true],
        'students.manage' => ['registry', 'Change student status', true],
        'students.matriculate' => ['registry', 'Matriculate cleared candidates and issue matric numbers', true],
    ],

    // Default roles: code => [name, workspace, permissions]. Approval permissions arrive with their modules.
    'roles' => [
        'system_admin' => ['System Administrator', 'ict', [
            'users.view', 'users.manage', 'roles.manage', 'grants.manage', 'persons.view',
            'org_units.manage', 'settings.manage', 'audit.view', 'workflows.manage', 'notifications.manage',
            'staff.view', 'staff.manage',
        ]],
        'registry_officer' => ['Registry Officer', 'registry', [
            'persons.view', 'persons.manage', 'users.view', 'staff.view', 'admissions.configure', 'admissions.view',
            'admissions.process', 'admissions.change_programme', 'admissions.lists', 'students.view', 'students.manage', 'students.matriculate',
        ]],
        'academic_planning_officer' => ['Academic Planning Officer', 'academic_planning', [
            'sessions.manage', 'calendar.manage', 'org_units.manage', 'academic_config.manage',
            'programmes.manage', 'courses.manage', 'curricula.view', 'curricula.approve', 'curricula.publish',
            'offerings.view', 'offerings.manage', 'staff.view',
        ]],
        'bursary_officer' => ['Bursary Officer', 'bursary', ['admission_payments.verify', 'admissions.view']],
        'faculty_officer' => ['Faculty Officer', 'faculty', ['users.view', 'curricula.view', 'curricula.endorse', 'offerings.view', 'staff.view', 'admissions.view', 'students.view']],
        'department_officer' => ['Department Officer', 'department', [
            'users.view', 'courses.manage', 'curricula.view', 'curricula.manage',
            'offerings.view', 'offerings.manage', 'offerings.allocate', 'staff.view', 'admissions.view', 'students.view',
        ]],
        'head_of_department' => ['Head of Department', 'department', [
            'curricula.view', 'curricula.endorse', 'offerings.view', 'offerings.allocate', 'staff.view',
        ]],
        'course_adviser' => ['Course Adviser', 'department', []],
        'lecturer' => ['Lecturer', 'lecturer', ['offerings.view']],
        'student_affairs_officer' => ['Student Affairs Officer', 'student_affairs', []],
        'management' => ['Management', 'management', ['audit.view', 'curricula.view', 'offerings.view', 'admissions.view', 'admissions.approve', 'students.view']],
        'admissions_committee' => ['Admissions Committee', 'registry', ['admissions.view', 'admissions.recommend']],
        'clearance_officer' => ['Clearance Officer', 'clearance', ['clearance.review', 'students.view']],
        'applicant' => ['Applicant', 'applicant', ['applications.apply']],
        'student' => ['Student', 'student', []],
    ],
];
