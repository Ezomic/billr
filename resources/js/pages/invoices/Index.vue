<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Plus, MoreHorizontal, Eye, CheckCheck, Send, Trash2 } from 'lucide-vue-next'
import { useForm } from '@inertiajs/vue3'

interface Invoice {
    id: number
    number: string
    status: string
    total: number
    currency: string
    issued_at: string | null
    due_at: string | null
    client: { id: number; name: string }
}

defineProps<{ invoices: Invoice[] }>()

function formatMoney(cents: number, currency: string) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(cents / 100)
}

function formatDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function markSent(invoice: Invoice) {
    useForm({}).post(route('invoices.sent', invoice.id))
}

function markPaid(invoice: Invoice) {
    useForm({}).post(route('invoices.paid', invoice.id))
}

function destroy(invoice: Invoice) {
    if (!confirm(`Delete invoice ${invoice.number}?`)) return
    useForm({}).delete(route('invoices.destroy', invoice.id))
}
</script>

<template>
    <AppLayout title="Invoices">
        <div class="p-6 md:p-8 space-y-6">
            <PageHeader title="Invoices" description="Create and track invoices for your clients.">
                <Button :href="route('invoices.create')" as="a">
                    <Plus class="size-4" /> New invoice
                </Button>
            </PageHeader>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Issued</TableHead>
                        <TableHead>Due</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!invoices.length" :colspan="7" text="No invoices yet. Create your first one." />
                    <TableRow v-for="inv in invoices" :key="inv.id" class="cursor-pointer" @click="router.visit(route('invoices.show', inv.id))">
                        <TableCell class="font-mono font-medium">{{ inv.number }}</TableCell>
                        <TableCell>{{ inv.client.name }}</TableCell>
                        <TableCell><StatusBadge :status="inv.status" /></TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(inv.issued_at) }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(inv.due_at) }}</TableCell>
                        <TableCell class="text-right font-medium">{{ formatMoney(inv.total, inv.currency) }}</TableCell>
                        <TableCell @click.stop>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem :href="route('invoices.show', inv.id)" as="a" class="gap-2">
                                        <Eye class="size-4" /> View
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem v-if="inv.status === 'draft'" @click="markSent(inv)" class="gap-2">
                                        <Send class="size-4" /> Mark as sent
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="inv.status !== 'paid'" @click="markPaid(inv)" class="gap-2">
                                        <CheckCheck class="size-4" /> Mark as paid
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator v-if="inv.status === 'draft'" />
                                    <DropdownMenuItem v-if="inv.status === 'draft'" @click="destroy(inv)" class="text-destructive focus:text-destructive gap-2">
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
