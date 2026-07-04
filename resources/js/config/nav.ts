import {
    LayoutDashboard,
    Users,
    FolderKanban,
    Clock,
    FileText,
} from 'lucide-vue-next'
import type { Component } from 'vue'

export interface NavItem {
    label: string
    route: string
    icon: Component
}

export const navItems: NavItem[] = [
    { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    { label: 'Clients', route: 'clients.index', icon: Users },
    { label: 'Projects', route: 'projects.index', icon: FolderKanban },
    { label: 'Time', route: 'time.index', icon: Clock },
    { label: 'Invoices', route: 'invoices.index', icon: FileText },
]
