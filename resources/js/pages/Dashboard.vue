<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'

const page = usePage<SharedProps>()
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="p-6 md:p-8">
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p class="text-muted-foreground mt-1">
                Welcome back, {{ page.props.auth.user?.name }}
            </p>
        </div>
    </AppLayout>
</template>
