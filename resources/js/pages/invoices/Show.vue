<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ArrowLeft, Ban, Check, CheckCheck, Copy, CopyPlus, Download, Link, Plus, Send, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

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
    stripe_payment_link: string | null
    client: { name: string; email: string | null; address: string | null; city: string | null; country: string | null; vat_number: string | null }
    lines: InvoiceLine[]
    created_by: { name: string }
    reminders: { id: number; days_overdue: number; sent_to: string; sent_at: string }[]
}

const props = defineProps<{ invoice: Invoice }>()

const isSettled = computed(() => props.invoice.status === 'paid' || props.invoice.status === 'void')
const isDraft = computed(() => props.invoice.status === 'draft')

const lastReminder = computed(() => props.invoice.reminders?.at(-1) ?? null)

const lineForm = useForm({ description: '', quantity: '1', unitPrice: '' })

const detailsForm = useForm({
    notes: props.invoice.notes ?? '',
    issued_at: props.invoice.issued_at?.slice(0, 10) ?? '',
    due_at: props.invoice.due_at?.slice(0, 10) ?? '',
})

function saveDetails() {
    detailsForm.put(route('invoices.update', props.invoice.id), { preserveScroll: true })
}

const canAddLine = computed(() =>
    lineForm.description.trim() !== '' &&
    Number(lineForm.quantity) >= 1 &&
    lineForm.unitPrice !== '' &&
    !Number.isNaN(parseFloat(lineForm.unitPrice))
)

const paymentLink = ref<string | null>(props.invoice.stripe_payment_link)
const generatingLink = ref(false)
const copied = ref(false)

async function generatePaymentLink() {
    generatingLink.value = true
    try {
        const response = await fetch(route("invoices.payment-link", props.invoice.id), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": (document.querySelector("meta[name=\"csrf-token\"]") as HTMLMetaElement)?.content ?? "",
                "Accept": "application/json",
            },
        })
        const data = await response.json()
        paymentLink.value = data.url
    } finally {
        generatingLink.value = false
    }
}

async function copyPaymentLink() {
    if (!paymentLink.value) return
    await navigator.clipboard.writeText(paymentLink.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

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

function markSent() {
    useForm({}).post(route('invoices.sent', props.invoice.id))
}

function markPaid() {
    useForm({}).post(route('invoices.paid', props.invoice.id))
}

function formatRate(line: InvoiceLine) {
    return line.unit === 'hours' ? `${formatMoney(line.unit_price)}/hr` : formatMoney(line.unit_price)
}

function addLine() {
    if (!canAddLine.value) return
    lineForm
        .transform((data) => ({
            description: data.description,
            quantity: Number(data.quantity),
            unit_price: Math.round(parseFloat(data.unitPrice) * 100),
        }))
        .post(route('invoices.lines.store', props.invoice.id), {
            preserveScroll: true,
            onSuccess: () => lineForm.reset(),
        })
}

function removeLine(lineId: number) {
    useForm({}).delete(route('invoices.lines.destroy', [props.invoice.id, lineId]), { preserveScroll: true })
}

function copyInvoice() {
    useForm({}).post(route('invoices.copy', props.invoice.id))
}

function markVoid() {
    if (!confirm(`Void invoice ${props.invoice.number}? Its time entries become billable again.`)) return
    useForm({}).post(route('invoices.void', props.invoice.id))
}

function destroy() {
    if (!confirm(`Delete invoice ${props.invoice.number}?`)) return
    useForm({}).delete(route('invoices.destroy', props.invoice.id))
}
</script>

<template>
    <AppLayout :title="invoice.number">
        <div class="p-6 md:p-8 max-w-3xl space-y-6">
            <!-- Toolbar -->
            <div class="flex items-center justify-between">
                <Button variant="outline" as="a" :href="route('invoices.index')">
                    <ArrowLeft class="size-4" /> Invoices
                </Button>
                <div class="flex items-center gap-2">
                    <StatusBadge :status="invoice.status" />
                    <span v-if="lastReminder" class="text-muted-foreground text-xs">
                        Reminded {{ formatDate(lastReminder.sent_at) }}
                    </span>
                    <Button variant="outline" size="sm" as="a" :href="route('invoices.pdf', invoice.id)">
                        <Download class="size-4" /> PDF
                    </Button>
                    <Button v-if="invoice.status === 'draft'" variant="outline" size="sm" @click="markSent">
                        <Send class="size-4" /> Mark sent
                    </Button>
                    <Button v-if="!isSettled" variant="outline" size="sm" @click="markPaid">
                        <CheckCheck class="size-4" /> Mark paid
                    </Button>
                    <Button v-if="!isSettled" variant="outline" size="sm" @click="generatePaymentLink" :disabled="generatingLink">
                        <Link class="size-4" /> {{ generatingLink ? 'Generating...' : 'Payment link' }}
                    </Button>
                    <Button variant="outline" size="sm" @click="copyInvoice">
                        <CopyPlus class="size-4" /> Copy
                    </Button>
                    <Button v-if="!isSettled" variant="outline" size="sm" @click="markVoid">
                        <Ban class="size-4" /> Void
                    </Button>
                                        <Button v-if="invoice.status === 'draft'" variant="outline" size="sm" @click="destroy" class="text-destructive hover:text-destructive">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Invoice document -->
            <div class="bg-card border rounded-xl p-8 space-y-8 print:border-0 print:shadow-none">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">Invoice</h1>
                        <p class="text-muted-foreground font-mono text-sm mt-1">{{ invoice.number }}</p>
                    </div>
                    <StatusBadge :status="invoice.status" />
                </div>

                <!-- Dates + client -->
                <div class="grid grid-cols-2 gap-8">
                    <div class="space-y-1 text-sm">
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Bill to</p>
                        <p class="font-semibold">{{ invoice.client.name }}</p>
                        <p v-if="invoice.client.email" class="text-muted-foreground">{{ invoice.client.email }}</p>
                        <p v-if="invoice.client.address" class="text-muted-foreground">{{ invoice.client.address }}</p>
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

                <!-- Line items -->
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Duration</TableHead>
                            <TableHead class="text-right">Rate</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead v-if="isDraft" class="w-10"><span class="sr-only">Remove</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="line in invoice.lines" :key="line.id">
                            <TableCell>{{ line.description }}</TableCell>
                            <TableCell class="text-right font-mono">{{ formatQty(line) }}</TableCell>
                            <TableCell class="text-right">{{ formatRate(line) }}</TableCell>
                            <TableCell class="text-right font-medium">{{ formatMoney(line.amount) }}</TableCell>
                            <TableCell v-if="isDraft" class="w-10 text-right">
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="removeLine(line.id)">
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Manual line editor, drafts only -->
                <form v-if="isDraft" class="flex flex-wrap items-end gap-2" @submit.prevent="addLine">
                    <div class="min-w-48 flex-1 space-y-1">
                        <Label class="text-xs">Description</Label>
                        <Input v-model="lineForm.description" placeholder="Fixed fee, expense, discount…" />
                    </div>
                    <div class="w-24 space-y-1">
                        <Label class="text-xs">Qty</Label>
                        <Input v-model="lineForm.quantity" type="number" min="1" step="1" />
                    </div>
                    <div class="w-32 space-y-1">
                        <Label class="text-xs">Unit price</Label>
                        <Input v-model="lineForm.unitPrice" type="number" min="0" step="0.01" placeholder="0.00" />
                    </div>
                    <Button type="submit" variant="outline" size="sm" :disabled="!canAddLine || lineForm.processing">
                        <Plus class="size-4" /> Add line
                    </Button>
                </form>

                <Separator />

                <!-- Totals -->
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
                        <div v-if="invoice.paid_at" class="text-muted-foreground text-xs text-right pt-1">
                            Paid {{ formatDate(invoice.paid_at) }}
                        </div>
                    </div>
                </div>

                <div v-if="invoice.notes && !isDraft" class="text-sm text-muted-foreground border-t pt-4">
                    {{ invoice.notes }}
                </div>

                <!-- Details editor, drafts only -->
                <form v-if="isDraft" class="border-t pt-4 space-y-3" @submit.prevent="saveDetails">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Issue date</Label>
                            <Input v-model="detailsForm.issued_at" type="date" />
                            <p v-if="detailsForm.errors.issued_at" class="text-destructive text-xs">{{ detailsForm.errors.issued_at }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Due date</Label>
                            <Input v-model="detailsForm.due_at" type="date" />
                            <p v-if="detailsForm.errors.due_at" class="text-destructive text-xs">{{ detailsForm.errors.due_at }}</p>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Notes</Label>
                        <textarea
                            v-model="detailsForm.notes"
                            rows="3"
                            placeholder="Payment details, a thank you, terms…"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <p v-if="detailsForm.errors.notes" class="text-destructive text-xs">{{ detailsForm.errors.notes }}</p>
                    </div>
                    <Button type="submit" variant="outline" size="sm" :disabled="detailsForm.processing">
                        Save details
                    </Button>
                </form>

                <div v-if="paymentLink" class="border-t pt-4 flex items-center gap-2">
                    <a :href="paymentLink" target="_blank" rel="noopener" class="text-sm text-primary underline truncate flex-1">{{ paymentLink }}</a>
                    <Button variant="outline" size="sm" @click="copyPaymentLink">
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
