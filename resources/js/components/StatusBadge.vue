<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const variant = computed(() => {
    switch (props.status) {
        case 'paid': return 'default'
        case 'sent': return 'secondary'
        case 'overdue': return 'destructive'
        case 'void': return 'outline'
        default: return 'outline'
    }
})
</script>

<template>
    <Badge :variant="variant" class="capitalize">{{ status }}</Badge>
</template>
