<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Toaster } from '@/components/ui/sonner'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { LogOut } from 'lucide-vue-next'
import { useFlash } from '@/composables/useFlash'
import type { SharedProps } from '@/types'

defineProps<{ title?: string }>()

const page = usePage<SharedProps>()
useFlash()

function initials(name: string) {
    return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

function logout() { router.post(route('logout')) }
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="border-b">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                <span class="font-semibold">Billr</span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="size-8 rounded-full">
                            <Avatar class="size-8">
                                <AvatarFallback class="text-xs">
                                    {{ initials(page.props.auth.user?.name ?? '?') }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="logout" class="gap-2 text-destructive focus:text-destructive">
                            <LogOut class="size-4" /> Sign out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-8">
            <slot />
        </main>

        <Toaster position="bottom-right" rich-colors />
    </div>
</template>
