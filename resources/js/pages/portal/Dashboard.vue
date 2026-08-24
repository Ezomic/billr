<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
import PortalLayout from '@/layouts/PortalLayout.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import Pagination from '@/components/Pagination.vue'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { X } from 'lucide-vue-next'

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
    statuses: string[]
    filters: { status: string }
}>()

const filters = reactive({ ...props.filters })

watch(() => filters.status, () => {
    router.get(route('portal.dashboard'), {
        status: filters.status || undefined,
    }, { preserveState: true, replace: true })
})

const hasFilters = computed(() => !!filters.status)

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
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">Your invoices</h1>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-40"><SelectValue placeholder="Any status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ s }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button v-if="hasFilters" variant="ghost" size="sm" @click="filters.status = ''">
                        <X class="size-4" /> Clear
                    </Button>
                </div>
            </div>

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
                    <TableEmpty v-if="!invoices.data.length" :colspan="5" :text="hasFilters ? 'No invoices match this filter.' : 'No invoices yet.'" />
                    <TableRow
                        v-for="inv in invoices.data"
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

            <Pagination :paginator="invoices" label="invoices" />
        </div>
    </PortalLayout>
</template>
