<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'

const navItems = [
    { label: 'Profile', route: 'settings.profile' },
    { label: 'Workspace', route: 'settings.workspace' },
    { label: 'Members', route: 'settings.members' },
]

function isActive(routeName: string) {
    try { return route().current(routeName) ?? false } catch { return false }
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="p-6 md:p-8 space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>

            <div class="flex gap-6">
                <!-- Settings sidebar -->
                <nav class="w-44 shrink-0 space-y-0.5">
                    <a
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="isActive(item.route)
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <!-- Content -->
                <div class="min-w-0 flex-1">
                    <slot />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
