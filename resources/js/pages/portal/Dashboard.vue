<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import PortalLayout from '@/layouts/PortalLayout.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'

interface Invoice {
    id: number
    number: string
    status: string
    total: number
    currency: string
    issued_at: string | null
    due_at: string | null
    client: { name: string }
}

defineProps<{ invoices: Invoice[] }>()

function formatMoney(cents: number, currency: string) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(cents / 100)
}

function formatDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <PortalLayout title="Your invoices">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight">Your invoices</h1>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Issued</TableHead>
                        <TableHead>Due</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!invoices.length" :colspan="5" text="No invoices yet." />
                    <TableRow
                        v-for="inv in invoices"
                        :key="inv.id"
                        class="cursor-pointer"
                        @click="router.visit(route('portal.invoices.show', inv.id))"
                    >
                        <TableCell class="font-mono font-medium">{{ inv.number }}</TableCell>
                        <TableCell><StatusBadge :status="inv.status" /></TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(inv.issued_at) }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(inv.due_at) }}</TableCell>
                        <TableCell class="text-right font-medium">{{ formatMoney(inv.total, inv.currency) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </PortalLayout>
</template>
