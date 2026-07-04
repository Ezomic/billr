<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { navItems } from '@/config/nav'
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue'
import UserMenu from '@/components/UserMenu.vue'
import { Separator } from '@/components/ui/separator'

const page = usePage()

function isActive(routeName: string): boolean {
    try {
        return route().current(routeName) ?? false
    } catch {
        return false
    }
}
</script>

<template>
    <aside class="bg-sidebar text-sidebar-foreground flex h-full w-60 shrink-0 flex-col border-r">
        <!-- Workspace switcher -->
        <div class="p-2">
            <WorkspaceSwitcher />
        </div>

        <Separator />

        <!-- Navigation -->
        <nav class="flex-1 space-y-0.5 p-2">
            <a
                v-for="item in navItems"
                :key="item.route"
                :href="route(item.route)"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                :class="isActive(item.route)
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
            >
                <component :is="item.icon" class="size-4 shrink-0" />
                {{ item.label }}
            </a>
        </nav>

        <Separator />

        <!-- User menu -->
        <div class="p-2">
            <UserMenu />
        </div>
    </aside>
</template>
