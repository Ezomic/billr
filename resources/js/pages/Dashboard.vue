<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { SharedProps } from '@/types'
import { ArrowRight } from 'lucide-vue-next'

interface Stats {
    totalInvoices: number
    overdueCount: number
}

interface CurrencyTotal {
    currency: string
    total: number
}

interface OverdueInvoice {
    id: number
    number: string
    client: string
    currency: string
    balance: number
    due_at: string | null
    days_overdue: number
}

interface Props extends SharedProps {
    stats: Stats
    outstanding: CurrencyTotal[]
    paidThisMonth: CurrencyTotal[]
    workspaceCurrency: string
    revenueByMonth: { month: string; total: number }[]
    unbilled: { minutes: number; amount: number }
    overdueInvoices: OverdueInvoice[]
}

const page = usePage<Props>()

const { stats, outstanding, paidThisMonth, workspaceCurrency, revenueByMonth, unbilled, overdueInvoices } = page.props

function formatCurrency(cents: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(cents / 100)
}

function rowsOrZero(rows: CurrencyTotal[]): CurrencyTotal[] {
    return rows.length ? rows : [{ currency: workspaceCurrency, total: 0 }]
}

function formatHours(minutes: number): string {
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return `${h}h ${m}m`
}

function monthLabel(month: string): string {
    const [year, m] = month.split('-')
    return new Date(Number(year), Number(m) - 1, 1).toLocaleDateString('en-GB', { month: 'short' })
}

const peakRevenue = computed(() => Math.max(...revenueByMonth.map(r => r.total), 1))
const hasRevenue = computed(() => revenueByMonth.some(r => r.total > 0))
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="p-6 md:p-8 space-y-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-muted-foreground mt-1">
                    Welcome back, {{ page.props.auth.user?.name }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    :href="route('invoices.index')"
                    class="bg-card text-card-foreground hover:border-foreground/20 rounded-xl border p-6 shadow-sm transition-colors"
                >
                    <p class="text-muted-foreground text-sm font-medium">Total Invoices</p>
                    <p class="mt-2 text-3xl font-bold">{{ stats.totalInvoices }}</p>
                </Link>

                <Link
                    :href="route('invoices.index', { status: 'sent' })"
                    class="bg-card text-card-foreground hover:border-foreground/20 rounded-xl border p-6 shadow-sm transition-colors"
                >
                    <p class="text-muted-foreground text-sm font-medium">Outstanding</p>
                    <div class="mt-2 space-y-1">
                        <p v-for="row in rowsOrZero(outstanding)" :key="row.currency" class="text-3xl font-bold tabular-nums">
                            {{ formatCurrency(row.total, row.currency) }}
                        </p>
                    </div>
                </Link>

                <Link
                    :href="route('invoices.index', { status: 'paid' })"
                    class="bg-card text-card-foreground hover:border-foreground/20 rounded-xl border p-6 shadow-sm transition-colors"
                >
                    <p class="text-muted-foreground text-sm font-medium">Paid This Month</p>
                    <div class="mt-2 space-y-1">
                        <p v-for="row in rowsOrZero(paidThisMonth)" :key="row.currency" class="text-3xl font-bold tabular-nums">
                            {{ formatCurrency(row.total, row.currency) }}
                        </p>
                    </div>
                </Link>

                <Link
                    :href="route('time.index')"
                    class="bg-card text-card-foreground hover:border-foreground/20 rounded-xl border p-6 shadow-sm transition-colors"
                >
                    <p class="text-muted-foreground text-sm font-medium">Unbilled time</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ formatCurrency(unbilled.amount, workspaceCurrency) }}</p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ formatHours(unbilled.minutes) }} not yet invoiced</p>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Revenue trend -->
                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <div class="flex items-baseline justify-between">
                        <h2 class="font-semibold">Revenue</h2>
                        <p class="text-muted-foreground text-xs">Last 12 months, {{ workspaceCurrency }}</p>
                    </div>

                    <div v-if="hasRevenue" class="mt-6 flex h-40 items-end gap-1.5">
                        <div v-for="row in revenueByMonth" :key="row.month" class="flex flex-1 flex-col items-center gap-1.5">
                            <div
                                class="bg-primary/80 hover:bg-primary w-full rounded-t transition-colors"
                                :style="{ height: `${Math.max((row.total / peakRevenue) * 100, row.total > 0 ? 4 : 0)}%` }"
                                :title="formatCurrency(row.total, workspaceCurrency)"
                            />
                            <span class="text-muted-foreground text-[10px]">{{ monthLabel(row.month) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground mt-6 text-sm">
                        Nothing paid yet. Once invoices are settled they show up here.
                    </p>
                </div>

                <!-- Overdue -->
                <div class="bg-card text-card-foreground rounded-xl border p-6 shadow-sm">
                    <div class="flex items-baseline justify-between">
                        <h2 class="font-semibold">Overdue</h2>
                        <Link
                            v-if="stats.overdueCount > 0"
                            :href="route('invoices.index', { status: 'overdue' })"
                            class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs"
                        >
                            See all {{ stats.overdueCount }} <ArrowRight class="size-3" />
                        </Link>
                    </div>

                    <div v-if="overdueInvoices.length" class="mt-4 space-y-3">
                        <Link
                            v-for="inv in overdueInvoices"
                            :key="inv.id"
                            :href="route('invoices.show', inv.id)"
                            class="hover:bg-muted/50 -mx-2 flex items-center justify-between rounded px-2 py-1.5 text-sm transition-colors"
                        >
                            <div>
                                <span class="font-mono font-medium">{{ inv.number }}</span>
                                <span class="text-muted-foreground"> · {{ inv.client }}</span>
                            </div>
                            <div class="text-right">
                                <p class="font-medium tabular-nums">{{ formatCurrency(inv.balance, inv.currency) }}</p>
                                <p class="text-destructive text-xs">{{ inv.days_overdue }}d overdue</p>
                            </div>
                        </Link>
                    </div>
                    <p v-else class="text-muted-foreground mt-4 text-sm">
                        Nothing overdue. Every sent invoice is within its terms.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
