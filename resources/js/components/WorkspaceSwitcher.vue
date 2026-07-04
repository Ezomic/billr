<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'
import { ChevronsUpDown, Building2 } from 'lucide-vue-next'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
} from '@/components/ui/dropdown-menu'

const page = usePage<SharedProps>()
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="hover:bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition-colors">
                <div class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-md">
                    <Building2 class="size-4" />
                </div>
                <span class="min-w-0 flex-1 truncate text-sm font-semibold">
                    {{ page.props.auth.workspace?.name }}
                </span>
                <ChevronsUpDown class="text-muted-foreground size-4 shrink-0" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="bottom" align="start" class="w-56">
            <DropdownMenuLabel class="text-muted-foreground text-xs">Workspaces</DropdownMenuLabel>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
