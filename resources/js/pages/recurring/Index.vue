<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, MoreHorizontal, Pencil, Trash2, Pause, Play, X } from 'lucide-vue-next'

interface Line { id?: number; description: string; quantity: number; unit_price: number; amount: number }
interface Schedule {
    id: number
    name: string
    interval: string
    start_on: string
    end_on: string | null
    next_run_on: string
    currency: string
    tax_rate: string
    notes: string | null
    status: string
    invoices_count: number
    client: { id: number; name: string }
    lines: Line[]
}

const props = defineProps<{
    schedules: Schedule[]
    clients: { id: number; name: string; currency: string | null }[]
    intervals: string[]
}>()

const showDialog = ref(false)
const editing = ref<Schedule | null>(null)

interface FormLine { description: string; quantity: string; unit_price: string }

const form = useForm({
    client_id: '',
    name: '',
    interval: 'monthly',
    start_on: new Date().toISOString().slice(0, 10),
    end_on: '',
    tax_rate: '0',
    notes: '',
    lines: [{ description: '', quantity: '1', unit_price: '' }] as FormLine[],
})

function openCreate() {
    editing.value = null
    form.reset()
    form.lines = [{ description: '', quantity: '1', unit_price: '' }]
    showDialog.value = true
}

function openEdit(schedule: Schedule) {
    editing.value = schedule
    form.client_id = String(schedule.client.id)
    form.name = schedule.name
    form.interval = schedule.interval
    form.start_on = schedule.start_on.slice(0, 10)
    form.end_on = schedule.end_on?.slice(0, 10) ?? ''
    form.tax_rate = String(schedule.tax_rate ?? '0')
    form.notes = schedule.notes ?? ''
    form.lines = schedule.lines.map((l) => ({
        description: l.description,
        quantity: String(l.quantity),
        unit_price: (l.unit_price / 100).toFixed(2),
    }))
    showDialog.value = true
}

function addLine() {
    form.lines.push({ description: '', quantity: '1', unit_price: '' })
}

function removeLine(index: number) {
    if (form.lines.length > 1) form.lines.splice(index, 1)
}

const previewTotal = computed(() => {
    const subtotal = form.lines.reduce((sum, l) => {
        const qty = Number(l.quantity) || 0
        const price = Math.round(parseFloat(l.unit_price || '0') * 100) || 0
        return sum + qty * price
    }, 0)
    const tax = Math.round(subtotal * (parseFloat(form.tax_rate || '0') / 100))
    return subtotal + tax
})

function submit() {
    const payload = form.transform((data) => ({
        ...data,
        end_on: data.end_on || null,
        notes: data.notes || null,
        lines: data.lines.map((l) => ({
            description: l.description,
            quantity: Number(l.quantity),
            unit_price: Math.round(parseFloat(l.unit_price || '0') * 100),
        })),
    }))

    if (editing.value) {
        payload.put(route('recurring.update', editing.value.id), {
            onSuccess: () => { showDialog.value = false; form.reset() },
        })
    } else {
        payload.post(route('recurring.store'), {
            onSuccess: () => { showDialog.value = false; form.reset() },
        })
    }
}

function pause(s: Schedule) {
    useForm({}).post(route('recurring.pause', s.id), { preserveScroll: true })
}

function resume(s: Schedule) {
    useForm({}).post(route('recurring.resume', s.id), { preserveScroll: true })
}

function destroy(s: Schedule) {
    if (!confirm(`Delete "${s.name}"? Invoices it already generated are kept.`)) return
    useForm({}).delete(route('recurring.destroy', s.id))
}

function formatMoney(cents: number, currency: string) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(cents / 100)
}

function formatDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function scheduleTotal(s: Schedule) {
    const subtotal = s.lines.reduce((sum, l) => sum + l.amount, 0)
    return subtotal + Math.round(subtotal * (parseFloat(s.tax_rate) / 100))
}
</script>

<template>
    <AppLayout title="Recurring invoices">
        <div class="p-6 md:p-8 space-y-6">
            <PageHeader title="Recurring invoices" description="Bill a retainer on a schedule. Generated invoices always start as drafts.">
                <Button @click="openCreate">
                    <Plus class="size-4" /> New schedule
                </Button>
            </PageHeader>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead>Every</TableHead>
                        <TableHead>Next run</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Generated</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!schedules.length" :colspan="8" text="No recurring invoices yet." />
                    <TableRow v-for="s in schedules" :key="s.id">
                        <TableCell class="font-medium">{{ s.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ s.client.name }}</TableCell>
                        <TableCell class="capitalize">{{ s.interval }}</TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ s.status === 'active' ? formatDate(s.next_run_on) : '—' }}
                        </TableCell>
                        <TableCell>
                            <Badge :variant="s.status === 'active' ? 'default' : 'outline'" class="capitalize">
                                {{ s.status }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right font-medium">{{ formatMoney(scheduleTotal(s), s.currency) }}</TableCell>
                        <TableCell class="text-right">{{ s.invoices_count }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEdit(s)" class="gap-2">
                                        <Pencil class="size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="s.status === 'active'" @click="pause(s)" class="gap-2">
                                        <Pause class="size-4" /> Pause
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-else @click="resume(s)" class="gap-2">
                                        <Play class="size-4" /> Resume
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="destroy(s)" class="text-destructive focus:text-destructive gap-2">
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit schedule' : 'New recurring invoice' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label>Client *</Label>
                            <Select v-model="form.client_id">
                                <SelectTrigger><SelectValue placeholder="Select client" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in clients" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.client_id" class="text-destructive text-xs">{{ form.errors.client_id }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label for="rname">Name *</Label>
                            <Input id="rname" v-model="form.name" placeholder="Monthly retainer" />
                            <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <Label>Interval</Label>
                            <Select v-model="form.interval">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="i in intervals" :key="i" :value="i" class="capitalize">{{ i }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label for="start">Starts</Label>
                            <Input id="start" v-model="form.start_on" type="date" />
                            <p v-if="form.errors.start_on" class="text-destructive text-xs">{{ form.errors.start_on }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label for="end">Ends (optional)</Label>
                            <Input id="end" v-model="form.end_on" type="date" />
                            <p v-if="form.errors.end_on" class="text-destructive text-xs">{{ form.errors.end_on }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Lines *</Label>
                        <div v-for="(line, i) in form.lines" :key="i" class="flex items-end gap-2">
                            <div class="flex-1 space-y-1">
                                <Input v-model="line.description" placeholder="Description" />
                            </div>
                            <div class="w-20 space-y-1">
                                <Input v-model="line.quantity" type="number" min="1" step="1" />
                            </div>
                            <div class="w-32 space-y-1">
                                <Input v-model="line.unit_price" type="number" min="0" step="0.01" placeholder="0.00" />
                            </div>
                            <Button type="button" variant="ghost" size="icon" class="size-9" :disabled="form.lines.length === 1" @click="removeLine(i)">
                                <X class="size-4" />
                            </Button>
                        </div>
                        <p v-if="form.errors.lines" class="text-destructive text-xs">{{ form.errors.lines }}</p>
                        <Button type="button" variant="outline" size="sm" @click="addLine">
                            <Plus class="size-4" /> Add line
                        </Button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label for="tax">Tax rate (%)</Label>
                            <Input id="tax" v-model="form.tax_rate" type="number" min="0" max="100" step="0.01" />
                        </div>
                        <div class="space-y-1">
                            <Label>Total per invoice</Label>
                            <p class="pt-2 text-lg font-semibold tabular-nums">
                                {{ formatMoney(previewTotal, clients.find(c => String(c.id) === form.client_id)?.currency ?? 'EUR') }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="notes">Notes</Label>
                        <Input id="notes" v-model="form.notes" placeholder="Shown on every generated invoice" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Create schedule' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
