import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Auth } from '@/types';

type SharedProps = {
    auth: Auth;
    [key: string]: unknown;
};

export function useAuth() {
    const page = usePage<SharedProps>();

    const user = computed(() => page.props.auth?.user ?? null);
    const roles = computed<string[]>(() => page.props.auth?.roles ?? []);
    const permissions = computed<string[]>(() => page.props.auth?.permissions ?? []);

    /**
     * Cek apakah user punya role tertentu (atau salah satu dari beberapa role).
     */
    function hasRole(role: string | string[]): boolean {
        if (!roles.value.length) return false;
        const check = Array.isArray(role) ? role : [role];
        return check.some((r) => roles.value.includes(r));
    }

    /**
     * Cek apakah user punya permission tertentu (atau salah satu dari beberapa).
     */
    function can(permission: string | string[]): boolean {
        if (!permissions.value.length) return false;
        const check = Array.isArray(permission) ? permission : [permission];
        return check.some((p) => permissions.value.includes(p));
    }

    /**
     * Cek apakah item nav boleh ditampilkan untuk user ini.
     * Jika item tidak punya filter roles/permissions → selalu tampil.
     */
    function canSeeNavItem(item: { roles?: string[]; permissions?: string[] }): boolean {
        const hasRoleFilter = item.roles && item.roles.length > 0;
        const hasPermissionFilter = item.permissions && item.permissions.length > 0;

        if (!hasRoleFilter && !hasPermissionFilter) return true;
        if (hasRoleFilter && hasRole(item.roles!)) return true;
        if (hasPermissionFilter && can(item.permissions!)) return true;

        return false;
    }

    return { user, roles, permissions, hasRole, can, canSeeNavItem };
}
