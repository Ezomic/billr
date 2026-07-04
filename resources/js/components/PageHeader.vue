<script setup lang="ts">
defineProps<{
    title: string
    description?: string
}>()
</script>

<template>
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-muted-foreground mt-1 text-sm">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
