<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
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
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    Briefcase,
    HeartPulse,
    LayoutDashboard,
    Rss,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin',
        icon: LayoutDashboard,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Teams',
        href: '/admin/teams',
        icon: Briefcase,
    },
];

const systemNavItems: NavItem[] = [
    {
        title: 'System Health',
        href: '/admin/system',
        icon: HeartPulse,
    },
    {
        title: 'Job Queue',
        href: '/admin/jobs',
        icon: Activity,
    },
    {
        title: 'Source Health',
        href: '/admin/sources',
        icon: Rss,
    },
    {
        title: 'Error Logs',
        href: '/admin/errors',
        icon: AlertTriangle,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Back to App',
        href: '/dashboard',
        icon: ArrowLeft,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Admin" />
            <NavMain :items="systemNavItems" label="System" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
