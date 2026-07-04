<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Play, Square, Plus, MoreHorizontal, Pencil, Trash2, Timer } from 'lucide-vue-next'

interface Project { id: number; name: string; client: { name: string }; hourly_rate: number | null }
interface TimeEntry {
    id: number
    description: string | null
    started_at: string
    stopped_at: string | null
    duration_minutes: number | null
    billable: boolean
    project: { id: number; name: string; client: { name: string } }
}

const props = defineProps<{
    entries: { data: TimeEntry[] }
    projects: Project[]
    running: TimeEntry | null
}>()

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
                <Button variant="outline" @click="openManual">
                    <Plus class="size-4" /> Manual entry
                </Button>
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
                        <TableCell class="text-muted-foreground">{{ entry.description ?? '—' }}</TableCell>
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
                                    <DropdownMenuItem @click="openEdit(entry)" class="gap-2">
                                        <Pencil class="size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="destroy(entry)" class="text-destructive focus:text-destructive gap-2">
                                        <Trash2 class="size-4" /> Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
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
