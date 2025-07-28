<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class RoleHelper
{
    /**
     * التحقق من صحة دور المستخدم
     */
    public static function validateUserRole($user): bool
    {
        return $user &&
               !is_null($user->role) &&
               !empty(trim($user->role)) &&
               in_array($user->role, ['admin', 'user']);
    }

    /**
     * الحصول على المسار الصحيح بناءً على دور المستخدم
     */
    public static function getCorrectRedirectPath($user): string
    {
        if (!self::validateUserRole($user)) {
            return route('login');
        }

        return $user->role === 'admin'
            ? route('admin.dashboard')
            : route('user.dashboard');
    }

    /**
     * التحقق من صلاحية الوصول للإدارة
     */
    public static function canAccessAdmin($user): bool
    {
        return self::validateUserRole($user) && $user->role === 'admin';
    }
}
