<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

interface Paginator {
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

defineProps<{ paginator: Paginator; label?: string }>()
</script>

<template>
    <div v-if="paginator.total > 0" class="flex items-center justify-between pt-4 text-sm">
        <p class="text-muted-foreground">
            Showing {{ paginator.from }} to {{ paginator.to }} of {{ paginator.total }} {{ label ?? 'results' }}
        </p>
        <div v-if="paginator.last_page > 1" class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                as-child
                :disabled="!paginator.prev_page_url"
                :class="{ 'pointer-events-none opacity-50': !paginator.prev_page_url }"
            >
                <Link :href="paginator.prev_page_url ?? '#'" preserve-scroll>
                    <ChevronLeft class="size-4" /> Previous
                </Link>
            </Button>
            <span class="text-muted-foreground tabular-nums">
                Page {{ paginator.current_page }} of {{ paginator.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                as-child
                :disabled="!paginator.next_page_url"
                :class="{ 'pointer-events-none opacity-50': !paginator.next_page_url }"
            >
                <Link :href="paginator.next_page_url ?? '#'" preserve-scroll>
                    Next <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </div>
</template>
