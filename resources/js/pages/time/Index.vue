<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, reactive } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import Pagination from '@/components/Pagination.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Play, Square, Plus, MoreHorizontal, Pencil, Trash2, Timer, Download } from 'lucide-vue-next'

interface Project { id: number; name: string; client: { name: string }; hourly_rate: number | null }
interface TimeEntry {
    id: number
    description: string | null
    started_at: string
    stopped_at: string | null
    duration_minutes: number | null
    billable: boolean
    project: { id: number; name: string; client: { name: string } }
    user?: { id: number; name: string }
}

const page = usePage<SharedProps>()

const props = defineProps<{
    entries: { data: TimeEntry[]; current_page: number; last_page: number; from: number | null; to: number | null; total: number; prev_page_url: string | null; next_page_url: string | null }
    projects: Project[]
    running: TimeEntry | null
    isOwner: boolean
    members: { id: number; name: string }[]
    filterProjects: { id: number; name: string; client: { name: string } | null }[]
    totals: { minutes: number; amount: number }
    filters: { user_id: string; project_id: string; from: string; to: string }
}>()

const filters = reactive({ ...props.filters })

function applyFilters() {
    router.get(route('time.index'), {
        user_id: filters.user_id,
        project_id: filters.project_id || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
    }, { preserveState: true, replace: true })
}

watch(() => [filters.user_id, filters.project_id, filters.from, filters.to], applyFilters)

const hasFilters = computed(() => !!(filters.project_id || filters.from || filters.to))

function clearFilters() {
    filters.project_id = ''
    filters.from = ''
    filters.to = ''
}

// The export mirrors what is on screen, so it carries the same filters.
const exportUrl = computed(() => {
    const params = new URLSearchParams()
    if (filters.user_id) params.set('user_id', filters.user_id)
    if (filters.project_id) params.set('project_id', filters.project_id)
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    const qs = params.toString()
    return route('time.export') + (qs ? `?${qs}` : '')
})

function formatTotalDuration(minutes: number) {
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return `${h}h ${m}m`
}

// Only meaningful for an owner looking at somebody else's entries: editing and
// deleting stay with whoever logged the time.
const isMine = (entry: TimeEntry) => !entry.user || entry.user.id === page.props.auth.user.id

// Live timer
const elapsed = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

function startTimer() {
    if (!props.running) return
    const start = new Date(props.running.started_at).getTime()
    elapsed.value = Math.floor((Date.now() - start) / 1000)
    timer = setInterval(() => { elapsed.value++ }, 1000)
}

onMounted(startTimer)
onUnmounted(() => { if (timer) clearInterval(timer) })

const elapsedFormatted = computed(() => {
    const h = Math.floor(elapsed.value / 3600)
    const m = Math.floor((elapsed.value % 3600) / 60)
    const s = elapsed.value % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

// Start timer form
const timerProjectId = ref('')
const timerDescription = ref('')

function startRunning() {
    if (!timerProjectId.value) return
    router.post(route('time.start', timerProjectId.value), { description: timerDescription.value }, {
        onSuccess: () => {
            timerDescription.value = ''
        }
    })
}

function stopRunning() {
    if (!props.running) return
    router.post(route('time.stop', props.running.id))
}

// Manual entry
const showManual = ref(false)
const editingEntry = ref<TimeEntry | null>(null)

const manualForm = useForm({
    project_id: '',
    description: '',
    started_at: '',
    stopped_at: '',
    billable: true,
})

function openManual() {
    editingEntry.value = null
    manualForm.reset()
    const now = new Date()
    manualForm.started_at = formatDatetimeLocal(now)
    manualForm.stopped_at = formatDatetimeLocal(now)
    showManual.value = true
}

function openEdit(entry: TimeEntry) {
    editingEntry.value = entry
    manualForm.project_id = String(entry.project.id)
    manualForm.description = entry.description ?? ''
    manualForm.started_at = formatDatetimeLocal(new Date(entry.started_at))
    manualForm.stopped_at = entry.stopped_at ? formatDatetimeLocal(new Date(entry.stopped_at)) : ''
    manualForm.billable = entry.billable
    showManual.value = true
}

function formatDatetimeLocal(d: Date) {
    return d.toISOString().slice(0, 16)
}

function submitManual() {
    if (editingEntry.value) {
        manualForm.put(route('time.update', editingEntry.value.id), {
            onSuccess: () => { showManual.value = false },
        })
    } else {
        manualForm.post(route('time.store'), {
            onSuccess: () => { showManual.value = false; manualForm.reset() },
        })
    }
}

function destroy(entry: TimeEntry) {
    if (!confirm('Delete this time entry?')) return
    useForm({}).delete(route('time.destroy', entry.id))
}

function formatDuration(minutes: number | null) {
    if (!minutes) return '—'
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return h > 0 ? `${h}h ${m}m` : `${m}m`
}

function formatDate(iso: string) {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}
</script>

<template>
    <AppLayout title="Time">
        <div class="p-6 md:p-8 space-y-6">
            <PageHeader title="Time tracking" description="Log and manage your time entries.">
                <div class="flex items-center gap-2">
                    <Select v-if="isOwner" v-model="filters.user_id">
                        <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Everyone</SelectItem>
                            <SelectItem v-for="m in members" :key="m.id" :value="String(m.id)">{{ m.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button variant="outline" as="a" :href="exportUrl">
                        <Download class="size-4" /> Export CSV
                    </Button>
                    <Button variant="outline" @click="openManual">
                        <Plus class="size-4" /> Manual entry
                    </Button>
                </div>
            </PageHeader>

            <!-- Timer card -->
            <div class="bg-card border rounded-xl p-5 space-y-4">
                <div v-if="running" class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-destructive/10 text-destructive flex size-9 items-center justify-center rounded-full animate-pulse">
                            <Timer class="size-4" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">{{ running.project.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ running.description ?? 'No description' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-xl font-semibold">{{ elapsedFormatted }}</span>
                        <Button variant="destructive" size="sm" @click="stopRunning">
                            <Square class="size-4" /> Stop
                        </Button>
                    </div>
                </div>

                <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1 space-y-1">
                        <Label>Project</Label>
                        <Select v-model="timerProjectId">
                            <SelectTrigger><SelectValue placeholder="Select project…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                                    {{ p.client.name }} — {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex-1 space-y-1">
                        <Label>What are you working on?</Label>
                        <Input v-model="timerDescription" placeholder="Description…" @keyup.enter="startRunning" />
                    </div>
                    <Button @click="startRunning" :disabled="!timerProjectId">
                        <Play class="size-4" /> Start
                    </Button>
                </div>
            </div>

            <!-- Entries table -->
            <div class="flex flex-wrap items-center gap-2">
                <Select v-model="filters.project_id">
                    <SelectTrigger class="w-56"><SelectValue placeholder="Any project" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="p in filterProjects" :key="p.id" :value="String(p.id)">
                            {{ p.client?.name ? `${p.client.name} / ${p.name}` : p.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="filters.from" type="date" class="w-40" aria-label="From" />
                <Input v-model="filters.to" type="date" class="w-40" aria-label="To" />
                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters">
                    Clear
                </Button>

                <div class="ml-auto flex items-center gap-4 text-sm">
                    <span class="text-muted-foreground">Total</span>
                    <span class="font-mono font-semibold tabular-nums">{{ formatTotalDuration(totals.minutes) }}</span>
                    <span class="font-semibold tabular-nums">
                        {{ new Intl.NumberFormat('en-GB', { style: 'currency', currency: page.props.auth.workspace?.currency ?? 'EUR' }).format(totals.amount / 100) }}
                    </span>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Project</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Duration</TableHead>
                        <TableHead>Billable</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!entries.data.length" :colspan="6" text="No time entries yet. Start a timer or add a manual entry." />
                    <TableRow v-for="entry in entries.data" :key="entry.id">
                        <TableCell class="text-muted-foreground text-sm">{{ formatDate(entry.started_at) }}</TableCell>
                        <TableCell class="font-medium">
                            <span class="text-muted-foreground text-xs">{{ entry.project.client.name }} /</span>
                            {{ entry.project.name }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ entry.description ?? '—' }}
                            <span v-if="isOwner && entry.user && !isMine(entry)" class="text-muted-foreground/70 ml-1 text-xs">
                                · {{ entry.user.name }}
                            </span>
                        </TableCell>
                        <TableCell class="font-mono">{{ formatDuration(entry.duration_minutes) }}</TableCell>
                        <TableCell>
                            <Badge :variant="entry.billable ? 'default' : 'outline'">
                                {{ entry.billable ? 'Billable' : 'Non-billable' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem v-if="isMine(entry)" @click="openEdit(entry)" class="gap-2">
                                        <Pencil class="size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="isMine(entry)" @click="destroy(entry)" class="text-destructive focus:text-destructive gap-2">
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-else disabled class="gap-2">
                                        Logged by {{ entry.user?.name }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <Pagination :paginator="entries" label="entries" />
        </div>

        <!-- Manual entry dialog -->
        <Dialog v-model:open="showManual">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingEntry ? 'Edit entry' : 'Manual entry' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitManual" class="space-y-4">
                    <div class="space-y-1">
                        <Label>Project *</Label>
                        <Select v-model="manualForm.project_id">
                            <SelectTrigger><SelectValue placeholder="Select project" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                                    {{ p.client.name }} — {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label>Description</Label>
                        <Input v-model="manualForm.description" placeholder="What did you work on?" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <Label>Start</Label>
                            <Input v-model="manualForm.started_at" type="datetime-local" />
                        </div>
                        <div class="space-y-1">
                            <Label>End</Label>
                            <Input v-model="manualForm.stopped_at" type="datetime-local" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showManual = false">Cancel</Button>
                        <Button type="submit" :disabled="manualForm.processing">Save entry</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
