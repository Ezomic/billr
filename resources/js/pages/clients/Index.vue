<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Plus, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next'

interface Client {
    id: number
    name: string
    email: string | null
    phone: string | null
    city: string | null
    country: string | null
    projects_count: number
    invoices_count: number
}

const props = defineProps<{ clients: Client[] }>()

const showDialog = ref(false)
const editing = ref<Client | null>(null)

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    postal_code: '',
    country: '',
    vat_number: '',
    currency: '',
})

function openCreate() {
    editing.value = null
    form.reset()
    showDialog.value = true
}

function openEdit(client: Client) {
    editing.value = client
    form.name = client.name
    form.email = client.email ?? ''
    form.phone = client.phone ?? ''
    form.city = client.city ?? ''
    form.country = client.country ?? ''
    showDialog.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('clients.update', editing.value.id), {
            onSuccess: () => { showDialog.value = false },
        })
    } else {
        form.post(route('clients.store'), {
            onSuccess: () => { showDialog.value = false; form.reset() },
        })
    }
}

function destroy(client: Client) {
    if (!confirm(`Delete ${client.name}? This cannot be undone.`)) return
    useForm({}).delete(route('clients.destroy', client.id))
}
</script>

<template>
    <AppLayout title="Clients">
        <div class="p-6 md:p-8 space-y-6">
            <PageHeader title="Clients" description="Manage your clients and their details.">
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    New client
                </Button>
            </PageHeader>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Location</TableHead>
                        <TableHead class="text-right">Projects</TableHead>
                        <TableHead class="text-right">Invoices</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!clients.length" :colspan="6" text="No clients yet. Add your first one." />
                    <TableRow v-for="client in clients" :key="client.id">
                        <TableCell class="font-medium">{{ client.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ client.email ?? '—' }}</TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ [client.city, client.country].filter(Boolean).join(', ') || '—' }}
                        </TableCell>
                        <TableCell class="text-right">{{ client.projects_count }}</TableCell>
                        <TableCell class="text-right">{{ client.invoices_count }}</TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEdit(client)" class="gap-2">
                                        <Pencil class="size-4" /> Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="destroy(client)" class="text-destructive focus:text-destructive gap-2">
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
                    <DialogTitle>{{ editing ? 'Edit client' : 'New client' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-1">
                            <Label for="name">Name *</Label>
                            <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                            <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" />
                        </div>
                        <div class="space-y-1">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" />
                        </div>
                        <div class="space-y-1">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" />
                        </div>
                        <div class="space-y-1">
                            <Label for="country">Country (2-letter)</Label>
                            <Input id="country" v-model="form.country" maxlength="2" placeholder="NL" />
                        </div>
                        <div class="space-y-1">
                            <Label for="vat_number">VAT number</Label>
                            <Input id="vat_number" v-model="form.vat_number" />
                        </div>
                        <div class="space-y-1">
                            <Label for="currency">Currency override</Label>
                            <Input id="currency" v-model="form.currency" maxlength="3" placeholder="EUR" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Create client' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
