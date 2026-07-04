<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Send, CheckCircle, Clock } from 'lucide-vue-next'

interface Project {
    id: number
    name: string
    type: string
    status: string
}

interface TimeEntry {
    id: number
    description: string | null
    started_at: string
    duration_minutes: number | null
    hourly_rate: number | null
    billable: boolean
    client_approved: boolean
    project: { id: number; name: string }
}

interface Client {
    id: number
    name: string
    email: string | null
    phone: string | null
    city: string | null
    country: string | null
    currency: string | null
    vat_number: string | null
    portal_token: string | null
    projects: Project[]
}

const props = defineProps<{
    client: Client
    pendingEntries: TimeEntry[]
}>()

const sendForm = useForm({})

function sendPortalAccess() {
    sendForm.post(route('clients.portal-access', props.client.id))
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatHours(minutes: number | null) {
    if (!minutes) return '0h'
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return m > 0 ? `${h}h ${m}m` : `${h}h`
}
</script>

<template>
    <AppLayout :title="client.name">
        <div class="p-6 md:p-8 space-y-8">
            <PageHeader :title="client.name" :description="[client.city, client.country].filter(Boolean).join(', ') || ''">
                <Button
                    v-if="client.email"
                    variant="outline"
                    :disabled="sendForm.processing"
                    @click="sendPortalAccess"
                >
                    <Send class="size-4" />
                    Send approval request
                </Button>
            </PageHeader>

            <section class="space-y-4">
                <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Pending time entries</h2>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Project</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Duration</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!pendingEntries.length" :colspan="5" text="No unbilled time entries." />
                        <TableRow v-for="entry in pendingEntries" :key="entry.id">
                            <TableCell class="text-muted-foreground whitespace-nowrap">{{ formatDate(entry.started_at) }}</TableCell>
                            <TableCell class="font-medium">{{ entry.project.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ entry.description ?? '—' }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ formatHours(entry.duration_minutes) }}</TableCell>
                            <TableCell>
                                <Badge v-if="entry.client_approved" variant="secondary" class="gap-1 text-green-700 bg-green-100">
                                    <CheckCircle class="size-3" /> Approved
                                </Badge>
                                <Badge v-else variant="secondary" class="gap-1 text-amber-700 bg-amber-100">
                                    <Clock class="size-3" /> Awaiting approval
                                </Badge>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </div>
    </AppLayout>
</template>
