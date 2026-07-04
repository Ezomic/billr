<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { LogOut, Settings } from 'lucide-vue-next'

const page = usePage<SharedProps>()

const initials = (name: string) =>
    name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)

function logout() {
    router.post(route('logout'))
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="hover:bg-accent flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors">
                <Avatar class="size-8 shrink-0">
                    <AvatarFallback class="text-xs">
                        {{ initials(page.props.auth.user?.name ?? '?') }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ page.props.auth.user?.name }}</p>
                    <p class="text-muted-foreground truncate text-xs">{{ page.props.auth.user?.email }}</p>
                </div>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="start" class="w-56">
            <DropdownMenuItem as-child>
                <a :href="route('settings.profile')" class="flex items-center gap-2">
                    <Settings class="size-4" />
                    Settings
                </a>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="logout" class="text-destructive focus:text-destructive flex items-center gap-2">
                <LogOut class="size-4" />
                Sign out
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
