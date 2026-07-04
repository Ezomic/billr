<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'

interface Stats {
    totalInvoices: number
    totalOutstanding: number
    paidThisMonth: number
    overdueCount: number
}

interface Props extends SharedProps {
    stats: Stats
}

const page = usePage<Props>()

const { stats } = page.props

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: page.props.auth.workspace?.currency ?? 'USD',
        minimumFractionDigits: 2,
    }).format(cents / 100)
}
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="p-6 md:p-8">
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p class="text-muted-foreground mt-1">
                Welcome back, {{ page.props.auth.user?.name }}
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <p class="text-muted-foreground text-sm font-medium">Total Invoices</p>
                    <p class="mt-2 text-3xl font-bold">{{ stats.totalInvoices }}</p>
                </div>

                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <p class="text-muted-foreground text-sm font-medium">Outstanding</p>
                    <p class="mt-2 text-3xl font-bold">{{ formatCurrency(stats.totalOutstanding) }}</p>
                </div>

                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <p class="text-muted-foreground text-sm font-medium">Paid This Month</p>
                    <p class="mt-2 text-3xl font-bold">{{ formatCurrency(stats.paidThisMonth) }}</p>
                </div>

                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <p class="text-muted-foreground text-sm font-medium">Overdue</p>
                    <p class="mt-2 text-3xl font-bold" :class="stats.overdueCount > 0 ? 'text-destructive' : ''">
                        {{ stats.overdueCount }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
