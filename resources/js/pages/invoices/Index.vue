<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import Pagination from '@/components/Pagination.vue'
import { Plus, MoreHorizontal, Eye, CheckCheck, Send, Trash2, X, Download } from 'lucide-vue-next'
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

interface Paginator {
    data: Invoice[]
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    invoices: Paginator
    clients: { id: number; name: string }[]
    statuses: string[]
    filters: { status: string; client_id: string; q: string }
}>()

const filters = reactive({ ...props.filters })

// Server-side filtering, so the query string is the source of truth and a
// filtered list stays linkable and survives a refresh.
function applyFilters() {
    router.get(route('invoices.index'), {
        status: filters.status || undefined,
        client_id: filters.client_id || undefined,
        q: filters.q || undefined,
    }, { preserveState: true, replace: true })
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => filters.q, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 300)
})

watch([() => filters.status, () => filters.client_id], applyFilters)

const hasFilters = computed(() => !!(filters.status || filters.client_id || filters.q))

// The export mirrors whatever is on screen, so it carries the same filters.
const exportUrl = computed(() => {
    const params = new URLSearchParams()
    if (filters.status) params.set('status', filters.status)
    if (filters.client_id) params.set('client_id', filters.client_id)
    if (filters.q) params.set('q', filters.q)
    const qs = params.toString()
    return route('invoices.export') + (qs ? `?${qs}` : '')
})

function clearFilters() {
    filters.status = ''
    filters.client_id = ''
    filters.q = ''
}

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
                <div class="flex items-center gap-2">
                    <Button variant="outline" as="a" :href="exportUrl">
                        <Download class="size-4" /> Export CSV
                    </Button>
                    <Button :href="route('invoices.create')" as="a">
                        <Plus class="size-4" /> New invoice
                    </Button>
                </div>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-2">
                <Input v-model="filters.q" placeholder="Search invoice number…" class="max-w-56" />
                <Select v-model="filters.status">
                    <SelectTrigger class="w-40"><SelectValue placeholder="Any status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ s }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.client_id">
                    <SelectTrigger class="w-48"><SelectValue placeholder="Any client" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="c in clients" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters">
                    <X class="size-4" /> Clear
                </Button>
            </div>

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
                    <TableEmpty v-if="!invoices.data.length" :colspan="7" :text="hasFilters ? 'No invoices match these filters.' : 'No invoices yet. Create your first one.'" />
                    <TableRow v-for="inv in invoices.data" :key="inv.id" class="cursor-pointer" @click="router.visit(route('invoices.show', inv.id))">
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

            <Pagination :paginator="invoices" label="invoices" />
        </div>
    </AppLayout>
</template>
