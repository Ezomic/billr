<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Checkbox } from '@/components/ui/checkbox'
import { ArrowLeft, Loader2 } from 'lucide-vue-next'
import axios from 'axios'

interface Client { id: number; name: string; currency: string | null }
interface TimeEntry {
    id: number
    description: string | null
    started_at: string
    duration_minutes: number | null
    hourly_rate: number | null
    project: { name: string }
}

const props = defineProps<{ clients: Client[] }>()

const selectedClientId = ref('')
const taxRate = ref('0')
const unbilledEntries = ref<TimeEntry[]>([])
const selectedIds = ref<Set<number>>(new Set())
const loading = ref(false)

watch(selectedClientId, async (id) => {
    if (!id) return
    loading.value = true
    selectedIds.value = new Set()
    try {
        const res = await axios.get(route('invoices.unbilled-entries'), { params: { client_id: id } })
        unbilledEntries.value = res.data
    } finally {
        loading.value = false
    }
})

function toggleEntry(id: number) {
    if (selectedIds.value.has(id)) selectedIds.value.delete(id)
    else selectedIds.value.add(id)
}

function toggleAll() {
    if (selectedIds.value.size === unbilledEntries.value.length) {
        selectedIds.value = new Set()
    } else {
        selectedIds.value = new Set(unbilledEntries.value.map(e => e.id))
    }
}

const subtotal = computed(() => {
    return unbilledEntries.value
        .filter(e => selectedIds.value.has(e.id))
        .reduce((sum, e) => {
            const mins = e.duration_minutes ?? 0
            const rate = e.hourly_rate ?? 0
            return sum + Math.round((mins / 60) * rate)
        }, 0)
})

const tax = computed(() => Math.round(subtotal.value * (parseFloat(taxRate.value) / 100)))
const total = computed(() => subtotal.value + tax.value)

const form = useForm({
    client_id: '',
    time_entry_ids: [] as number[],
    tax_rate: '0',
})

function submit() {
    form.client_id = selectedClientId.value
    form.time_entry_ids = Array.from(selectedIds.value)
    form.tax_rate = taxRate.value
    form.post(route('invoices.store'))
}

function formatDuration(minutes: number | null) {
    if (!minutes) return '—'
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return h > 0 ? `${h}h ${m}m` : `${m}m`
}

function formatMoney(cents: number) {
    return `€${(cents / 100).toFixed(2)}`
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}
</script>

<template>
    <AppLayout title="New Invoice">
        <div class="p-6 md:p-8 space-y-6 max-w-3xl">
            <PageHeader title="New invoice">
                <Button variant="outline" as="a" :href="route('invoices.index')">
                    <ArrowLeft class="size-4" /> Back
                </Button>
            </PageHeader>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <Label>Client *</Label>
                        <Select v-model="selectedClientId">
                            <SelectTrigger><SelectValue placeholder="Select client…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in clients" :key="c.id" :value="String(c.id)">
                                    {{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label>Tax rate (%)</Label>
                        <Input v-model="taxRate" type="number" min="0" max="100" step="0.01" placeholder="0" />
                    </div>
                </div>

                <div v-if="selectedClientId" class="space-y-2">
                    <Label>Unbilled time entries</Label>

                    <div v-if="loading" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                        <Loader2 class="size-4 animate-spin" /> Loading entries…
                    </div>

                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-10">
                                    <Checkbox
                                        :checked="selectedIds.size === unbilledEntries.length && unbilledEntries.length > 0"
                                        @update:checked="toggleAll"
                                    />
                                </TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Project</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="text-right">Duration</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="!unbilledEntries.length" :colspan="6" text="No unbilled entries for this client." />
                            <TableRow
                                v-for="entry in unbilledEntries"
                                :key="entry.id"
                                class="cursor-pointer"
                                :class="{ 'bg-muted/50': selectedIds.has(entry.id) }"
                                @click="toggleEntry(entry.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox :checked="selectedIds.has(entry.id)" @update:checked="toggleEntry(entry.id)" />
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ formatDate(entry.started_at) }}</TableCell>
                                <TableCell>{{ entry.project.name }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ entry.description ?? '—' }}</TableCell>
                                <TableCell class="text-right font-mono">{{ formatDuration(entry.duration_minutes) }}</TableCell>
                                <TableCell class="text-right font-medium">
                                    {{ formatMoney(Math.round(((entry.duration_minutes ?? 0) / 60) * (entry.hourly_rate ?? 0))) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Totals -->
                <div v-if="selectedIds.size > 0" class="border-t pt-4 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span>{{ formatMoney(subtotal) }}</span>
                    </div>
                    <div v-if="tax > 0" class="flex justify-between">
                        <span class="text-muted-foreground">Tax ({{ taxRate }}%)</span>
                        <span>{{ formatMoney(tax) }}</span>
                    </div>
                    <div class="flex justify-between font-semibold text-base pt-1">
                        <span>Total</span>
                        <span>{{ formatMoney(total) }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button variant="outline" as="a" :href="route('invoices.index')">Cancel</Button>
                    <Button
                        @click="submit"
                        :disabled="!selectedClientId || selectedIds.size === 0 || form.processing"
                    >
                        Create invoice
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
