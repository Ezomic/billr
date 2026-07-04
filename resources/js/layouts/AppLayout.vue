<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from '@/components/AppSidebar.vue'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import { Toaster } from '@/components/ui/sonner'
import { Button } from '@/components/ui/button'
import { Menu } from 'lucide-vue-next'
import { useFlash } from '@/composables/useFlash'

defineProps<{ title?: string }>()

const mobileOpen = ref(false)

useFlash()
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Desktop sidebar -->
        <div class="hidden md:flex">
            <AppSidebar />
        </div>

        <!-- Mobile sidebar -->
        <Sheet v-model:open="mobileOpen">
            <SheetContent side="left" class="w-60 p-0">
                <AppSidebar />
            </SheetContent>
        </Sheet>

        <!-- Main content -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Mobile topbar -->
            <header class="flex items-center gap-3 border-b px-4 py-3 md:hidden">
                <Button variant="ghost" size="icon" @click="mobileOpen = true">
                    <Menu class="size-5" />
                </Button>
                <span class="text-sm font-semibold">{{ title ?? 'Billr' }}</span>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>

        <Toaster position="bottom-right" rich-colors />
    </div>
</template>
