<?php
namespace app\components;
final class RolePolicy
{
    public const ROLES = ['admin','registration','doctor','pharmacy','laboratory','cashier'];
    public static function permits(string $role, array $allowed): bool
    { return in_array($role, self::ROLES, true) && in_array($role, $allowed, true); }
    public static function publicUser(array $row): array
    { return ['id'=>(string)$row['id'], 'username'=>$row['username'], 'name'=>$row['name'], 'role'=>$row['role'], 'staff_id'=>$row['staff_id'] === null ? null : (string)$row['staff_id']]; }
    public static function validLoginPassword(mixed $value): bool
    { return is_string($value) && strlen($value) >= 1 && strlen($value) <= 72 && !str_contains($value, "\0"); }
    public static function active(mixed $value): bool { return in_array($value, [true, 1, '1', 't', 'true'], true); }
}
