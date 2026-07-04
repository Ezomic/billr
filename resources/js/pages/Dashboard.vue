<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'

const page = usePage<SharedProps>()
</script>

<template>
    <div class="p-8">
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <p class="text-muted-foreground mt-1">Welcome back, {{ page.props.auth.user?.name }}</p>
    </div>
</template>
