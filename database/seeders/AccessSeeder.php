<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles and permissions from the design's roles matrix (section 6).
 * "Class teacher" is not a role: it is the teacher set on each class (classes.class_teacher_id).
 */
class AccessSeeder extends Seeder
{
    public const PERMISSIONS = [
        'settings.manage' => 'Change the school profile and settings',
        'calendar.manage' => 'Manage academic years and terms',
        'structure.manage' => 'Manage levels, combinations, classes, subjects and topics',
        'structure.view' => 'See classes, subjects and the timetable structure',
        'staff.manage' => 'Add and edit staff',
        'staff.view' => 'See the staff list',
        'users.manage' => 'Create accounts and assign roles',
    ];

    public const ROLES = [
        'admin' => ['System administrator (company)', ['*']],
        'owner' => ['School owner', ['*']],
        'headteacher' => ['Head teacher', ['*']],
        'dos' => ['Deputy head for studies (DOS)', ['calendar.manage', 'structure.manage', 'structure.view', 'staff.view']],
        'discipline' => ['Deputy head for discipline', ['structure.view', 'staff.view']],
        'bursar' => ['Bursar / accountant', ['structure.view']],
        'teacher' => ['Teacher', ['structure.view']],
        'parent' => ['Parent / guardian', []],
        'student' => ['Student', []],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $code => $description) {
            Permission::query()->updateOrCreate(['code' => $code], ['description' => $description]);
        }
        $all = Permission::query()->pluck('id', 'code');

        foreach (self::ROLES as $name => [$label, $codes]) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $label]);
            $ids = $codes === ['*'] ? $all->values() : $all->only($codes)->values();
            $role->permissions()->sync($ids);
        }
    }
}
