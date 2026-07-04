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
import { Plus, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next'

interface Client { id: number; name: string }
interface Project {
    id: number
    name: string
    type: 'hourly' | 'fixed'
    hourly_rate: number | null
    fixed_price: number | null
    status: string
    time_entries_count: number
    client: Client
}

const props = defineProps<{ projects: Project[]; clients: Client[] }>()

const showDialog = ref(false)
const editing = ref<Project | null>(null)

const form = useForm({
    client_id: '',
    name: '',
    description: '',
    type: 'hourly',
    hourly_rate: '',
    fixed_price: '',
})

function openCreate() {
    editing.value = null
    form.reset()
    showDialog.value = true
}

function openEdit(p: Project) {
    editing.value = p
    form.client_id = String(p.client.id)
    form.name = p.name
    form.type = p.type
    form.hourly_rate = p.hourly_rate ? String(p.hourly_rate / 100) : ''
    form.fixed_price = p.fixed_price ? String(p.fixed_price / 100) : ''
    showDialog.value = true
}

function submit() {
    const data = {
        ...form.data(),
        hourly_rate: form.hourly_rate ? Math.round(parseFloat(form.hourly_rate) * 100) : null,
        fixed_price: form.fixed_price ? Math.round(parseFloat(form.fixed_price) * 100) : null,
    }

    if (editing.value) {
        form.transform(() => data).put(route('projects.update', editing.value!.id), {
            onSuccess: () => { showDialog.value = false },
        })
    } else {
        form.transform(() => data).post(route('projects.store'), {
            onSuccess: () => { showDialog.value = false; form.reset() },
        })
    }
}

function destroy(p: Project) {
    if (!confirm(`Delete "${p.name}"?`)) return
    useForm({}).delete(route('projects.destroy', p.id))
}

function formatRate(p: Project) {
    if (p.type === 'hourly' && p.hourly_rate) return `€${(p.hourly_rate / 100).toFixed(0)}/hr`
    if (p.type === 'fixed' && p.fixed_price) return `€${(p.fixed_price / 100).toFixed(0)} fixed`
    return '—'
}
</script>

<template>
    <AppLayout title="Projects">
        <div class="p-6 md:p-8 space-y-6">
            <PageHeader title="Projects" description="Track work across clients and projects.">
                <Button @click="openCreate" :disabled="!clients.length">
                    <Plus class="size-4" />
                    New project
                </Button>
            </PageHeader>

            <p v-if="!clients.length" class="text-muted-foreground text-sm">
                Add a client first before creating projects.
            </p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Client</TableHead>
                        <TableHead>Rate</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Entries</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!projects.length" :colspan="6" text="No projects yet." />
                    <TableRow v-for="p in projects" :key="p.id">
                        <TableCell class="font-medium">{{ p.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ p.client.name }}</TableCell>
                        <TableCell>{{ formatRate(p) }}</TableCell>
                        <TableCell>
                            <Badge :variant="p.status === 'active' ? 'default' : 'outline'" class="capitalize">
                                {{ p.status }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">{{ p.time_entries_count }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEdit(p)" class="gap-2">
                                        <Pencil class="size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="destroy(p)" class="text-destructive focus:text-destructive gap-2">
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
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit project' : 'New project' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label>Client *</Label>
                        <Select v-model="form.client_id">
                            <SelectTrigger><SelectValue placeholder="Select client" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in clients" :key="c.id" :value="String(c.id)">
                                    {{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label for="pname">Project name *</Label>
                        <Input id="pname" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>Type *</Label>
                        <Select v-model="form.type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="hourly">Hourly</SelectItem>
                                <SelectItem value="fixed">Fixed price</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="form.type === 'hourly'" class="space-y-1">
                        <Label for="hourly_rate">Hourly rate (€)</Label>
                        <Input id="hourly_rate" v-model="form.hourly_rate" type="number" min="0" step="0.01" placeholder="0.00" />
                    </div>
                    <div v-if="form.type === 'fixed'" class="space-y-1">
                        <Label for="fixed_price">Fixed price (€)</Label>
                        <Input id="fixed_price" v-model="form.fixed_price" type="number" min="0" step="0.01" placeholder="0.00" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Create project' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
