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
    ],

    // Default roles: code => [name, workspace, permissions]. Approval permissions arrive with their modules.
    'roles' => [
        'system_admin' => ['System Administrator', 'ict', [
            'users.view', 'users.manage', 'roles.manage', 'grants.manage', 'persons.view',
            'org_units.manage', 'settings.manage', 'audit.view', 'workflows.manage', 'notifications.manage',
        ]],
        'registry_officer' => ['Registry Officer', 'registry', ['persons.view', 'persons.manage', 'users.view']],
        'academic_planning_officer' => ['Academic Planning Officer', 'academic_planning', ['sessions.manage', 'calendar.manage', 'org_units.manage']],
        'bursary_officer' => ['Bursary Officer', 'bursary', []],
        'faculty_officer' => ['Faculty Officer', 'faculty', ['users.view']],
        'department_officer' => ['Department Officer', 'department', ['users.view']],
        'head_of_department' => ['Head of Department', 'department', []],
        'course_adviser' => ['Course Adviser', 'department', []],
        'lecturer' => ['Lecturer', 'lecturer', []],
        'student_affairs_officer' => ['Student Affairs Officer', 'student_affairs', []],
        'management' => ['Management', 'management', ['audit.view']],
        'student' => ['Student', 'student', []],
    ],
];
