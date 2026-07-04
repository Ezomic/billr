<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ArrowLeft, Printer } from 'lucide-vue-next'

interface InvoiceLine {
    id: number
    description: string
    quantity: number
    unit: string
    unit_price: number
    amount: number
}

interface Invoice {
    id: number
    number: string
    status: string
    currency: string
    subtotal: number
    tax_amount: number
    tax_rate: string
    total: number
    notes: string | null
    issued_at: string | null
    due_at: string | null
    paid_at: string | null
    client: { name: string; email: string | null; vat_number: string | null }
    workspace: { name: string }
    lines: InvoiceLine[]
}

const props = defineProps<{ invoice: Invoice }>()

function formatMoney(cents: number) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: props.invoice.currency }).format(cents / 100)
}

function formatDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatQty(line: InvoiceLine) {
    if (line.unit === 'hours') {
        const h = Math.floor(line.quantity / 60)
        const m = line.quantity % 60
        return h > 0 ? `${h}h ${m}m` : `${m}m`
    }
    return String(line.quantity)
}
</script>

<template>
    <PortalLayout :title="invoice.number">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Button variant="outline" as="a" :href="route('portal.dashboard')">
                    <ArrowLeft class="size-4" /> Back
                </Button>
                <Button variant="outline" @click="window.print()">
                    <Printer class="size-4" /> Print
                </Button>
            </div>

            <div class="bg-card border rounded-xl p-8 space-y-8">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Invoice</h1>
                        <p class="text-muted-foreground font-mono text-sm mt-1">{{ invoice.number }}</p>
                        <p class="text-muted-foreground text-sm mt-1">From {{ invoice.workspace.name }}</p>
                    </div>
                    <StatusBadge :status="invoice.status" />
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div class="space-y-1 text-sm">
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Bill to</p>
                        <p class="font-semibold">{{ invoice.client.name }}</p>
                        <p v-if="invoice.client.email" class="text-muted-foreground">{{ invoice.client.email }}</p>
                        <p v-if="invoice.client.vat_number" class="text-muted-foreground">VAT: {{ invoice.client.vat_number }}</p>
                    </div>
                    <div class="space-y-2 text-sm text-right">
                        <div>
                            <p class="text-muted-foreground text-xs">Issued</p>
                            <p>{{ formatDate(invoice.issued_at) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Due</p>
                            <p>{{ formatDate(invoice.due_at) }}</p>
                        </div>
                    </div>
                </div>

                <Separator />

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Duration</TableHead>
                            <TableHead class="text-right">Rate</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="line in invoice.lines" :key="line.id">
                            <TableCell>{{ line.description }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatQty(line) }}</TableCell>
                            <TableCell class="text-right">{{ formatMoney(line.unit_price) }}/hr</TableCell>
                            <TableCell class="text-right font-medium">{{ formatMoney(line.amount) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Separator />

                <div class="flex justify-end">
                    <div class="w-56 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ formatMoney(invoice.subtotal) }}</span>
                        </div>
                        <div v-if="invoice.tax_amount > 0" class="flex justify-between">
                            <span class="text-muted-foreground">Tax ({{ invoice.tax_rate }}%)</span>
                            <span>{{ formatMoney(invoice.tax_amount) }}</span>
                        </div>
                        <Separator class="my-2" />
                        <div class="flex justify-between font-semibold text-base">
                            <span>Total</span>
                            <span>{{ formatMoney(invoice.total) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="invoice.notes" class="text-sm text-muted-foreground border-t pt-4">
                    {{ invoice.notes }}
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
