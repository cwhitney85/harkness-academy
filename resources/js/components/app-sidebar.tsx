import { Link } from '@inertiajs/react';
import { LayoutGrid, Plus } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { create } from '@/routes/courses';
import type { NavItem } from '@/types';

/**
 * The rail the course surfaces are framed in — S1 and S12 (spec 8.10).
 *
 * The two edges leaving the dashboard that are not a course: back to the
 * dashboard itself, and S2. The starter kit's footer links pointed a student
 * at the React starter kit's GitHub repository, which is the same placeholder
 * 8.10.1 came here to replace.
 */
const mainNavItems: NavItem[] = [
    {
        title: 'Your courses',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Start a course',
        href: create(),
        icon: Plus,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
