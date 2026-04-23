<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    FileText,
    LayoutGrid,
    Receipt,
    Settings,
    Stethoscope,
    Users,
    UserSquare2,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAuth } from '@/composables/useAuth';
import * as AdminDashboard from '@/actions/App/Http/Controllers/Admin/DashboardController';
import * as AdminOrders from '@/actions/App/Http/Controllers/Admin/OrderController';
import * as AdminStaff from '@/actions/App/Http/Controllers/Admin/StaffController';
import * as AdminPatients from '@/actions/App/Http/Controllers/Admin/PatientController';
import * as AdminSchedules from '@/actions/App/Http/Controllers/Admin/ScheduleController';
import * as AdminVisits from '@/actions/App/Http/Controllers/Admin/VisitController';
import * as AdminInvoices from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import * as AdminReports from '@/actions/App/Http/Controllers/Admin/ReportController';
import * as AdminServices from '@/actions/App/Http/Controllers/Admin/ServiceController';
import * as AdminSettings from '@/actions/App/Http/Controllers/Admin/SettingController';
import * as PetugasDashboard from '@/actions/App/Http/Controllers/Petugas/DashboardController';
import * as DokterDashboard from '@/actions/App/Http/Controllers/Dokter/DashboardController';
import * as PasienDashboard from '@/actions/App/Http/Controllers/Pasien/DashboardController';
import type { NavItem } from '@/types';

const { hasRole, canSeeNavItem } = useAuth();

// Definisi semua menu — field `roles` menentukan siapa yang bisa melihatnya
const allNavItems: NavItem[] = [
    // ── Admin ──────────────────────────────────────────────
    {
        title: 'Dashboard',
        href: AdminDashboard.index.url(),
        icon: LayoutGrid,
        roles: ['admin'],
    },
    {
        title: 'Manajemen Order',
        href: AdminOrders.index.url(),
        icon: ClipboardList,
        roles: ['admin'],
    },
    {
        title: 'Petugas',
        href: AdminStaff.index.url(),
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'Pasien',
        href: AdminPatients.index.url(),
        icon: UserSquare2,
        roles: ['admin'],
    },
    {
        title: 'Jadwal',
        href: AdminSchedules.index.url(),
        icon: CalendarDays,
        roles: ['admin'],
    },
    {
        title: 'Kunjungan',
        href: AdminVisits.index.url(),
        icon: Stethoscope,
        roles: ['admin'],
    },
    {
        title: 'Invoice',
        href: AdminInvoices.index.url(),
        icon: Receipt,
        roles: ['admin'],
    },
    {
        title: 'Laporan',
        href: AdminReports.index.url(),
        icon: BarChart3,
        roles: ['admin'],
    },
    {
        title: 'Layanan',
        href: AdminServices.index.url(),
        icon: Wrench,
        roles: ['admin'],
    },
    {
        title: 'Pengaturan',
        href: AdminSettings.index.url(),
        icon: Settings,
        roles: ['admin'],
    },

    // ── Petugas ────────────────────────────────────────────
    {
        title: 'Dashboard',
        href: PetugasDashboard.index.url(),
        icon: LayoutGrid,
        roles: ['petugas'],
    },

    // ── Dokter ─────────────────────────────────────────────
    {
        title: 'Dashboard',
        href: DokterDashboard.index.url(),
        icon: LayoutGrid,
        roles: ['dokter'],
    },

    // ── Pasien ─────────────────────────────────────────────
    {
        title: 'Dashboard',
        href: PasienDashboard.index.url(),
        icon: LayoutGrid,
        roles: ['pasien'],
    },
];

// Dashboard link untuk logo — arahkan ke dashboard role yang tepat
const homeDashboard = computed(() => {
    if (hasRole('admin')) return AdminDashboard.index.url();
    if (hasRole('petugas')) return PetugasDashboard.index.url();
    if (hasRole('dokter')) return DokterDashboard.index.url();
    if (hasRole('pasien')) return PasienDashboard.index.url();
    return '/';
});

// Filter menu berdasarkan role user
const visibleNavItems = computed(() =>
    allNavItems.filter((item) => canSeeNavItem(item)),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeDashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="visibleNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
