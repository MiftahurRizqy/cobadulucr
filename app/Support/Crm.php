<?php

namespace App\Support;

class Crm
{
    public const AUTHORITY_LEVELS = [
        'master_admin' => 'Master Admin',
        'manager' => 'Manager',
        'supervisor' => 'Supervisor',
        'staff' => 'Staff',
    ];

    public const USER_TYPES = [
        'frontliner' => 'Frontliner',
        'backliner' => 'Backliner',
    ];

    public const TASK_STATUSES = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'review' => 'Menunggu Review',
        'done' => 'Done',
        'blocked' => 'Blocked',
        'cancelled' => 'Cancelled',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];
}
