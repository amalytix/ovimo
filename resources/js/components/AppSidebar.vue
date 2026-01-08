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
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    FileText,
    History,
    Image,
    LayoutGrid,
    MessageSquare,
    PenTool,
    Rss,
    Settings,
    Shield,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Sources',
        href: '/sources',
        icon: Rss,
    },
    {
        title: 'News',
        href: '/news',
        icon: FileText,
    },
    {
        title: 'Content',
        href: '/content-pieces',
        icon: PenTool,
    },
    {
        title: 'Media',
        href: '/media',
        icon: Image,
    },
    {
        title: 'Prompts',
        href: '/prompts',
        icon: MessageSquare,
    },
    {
        title: 'Settings',
        href: '/team-settings',
        icon: Settings,
    },
];

const footerNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Activities',
            href: '/derivative-activities',
            icon: History,
        },
        {
            title: 'Logs',
            href: '/activity-logs',
            icon: Activity,
        },
        {
            title: 'Usage',
            href: '/usage',
            icon: BarChart3,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: 'Admin',
            href: '/admin',
            icon: Shield,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
