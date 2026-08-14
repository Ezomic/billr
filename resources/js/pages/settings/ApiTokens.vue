<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty } from '@/components/ui/table'
import { Check, Copy, Plus, Trash2 } from 'lucide-vue-next'

interface Token {
    id: number
    name: string
    abilities: string[] | null
    last_used_at: string | null
    created_at: string
}

const props = defineProps<{
    tokens: Token[]
    abilities: string[]
    newToken: string | null
}>()

const form = useForm({
    name: '',
    abilities: [...props.abilities] as string[],
})

const copied = ref(false)

const canSubmit = computed(() => form.name.trim() !== '' && form.abilities.length > 0)

function submit() {
    if (!canSubmit.value) return
    form.post(route('settings.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}

async function copyToken() {
    if (!props.newToken) return
    await navigator.clipboard.writeText(props.newToken)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

function revoke(token: Token) {
    if (!confirm(`Revoke "${token.name}"? Anything using it stops working immediately.`)) return
    useForm({}).delete(route('settings.api-tokens.destroy', token.id), { preserveScroll: true })
}

function toggleAbility(ability: string) {
    const i = form.abilities.indexOf(ability)
    if (i === -1) form.abilities.push(ability)
    else form.abilities.splice(i, 1)
}

function formatDate(iso: string | null) {
    if (!iso) return 'Never'
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <SettingsLayout>
        <div class="max-w-2xl space-y-8">
            <div>
                <h2 class="text-lg font-semibold">API tokens</h2>
                <p class="text-muted-foreground text-sm">
                    Let another app report time entries into Billr. A token acts as you.
                </p>
            </div>

            <!-- Shown exactly once, straight after creation -->
            <div v-if="newToken" class="space-y-2 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
                <p class="text-sm font-medium text-green-900 dark:text-green-200">
                    Copy this token now. It will not be shown again.
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 overflow-x-auto rounded border bg-background px-3 py-2 font-mono text-xs">{{ newToken }}</code>
                    <Button variant="outline" size="sm" @click="copyToken">
                        <component :is="copied ? Check : Copy" class="size-4" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                </div>
            </div>

            <form class="space-y-4 rounded-lg border p-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label for="tname">Token name</Label>
                    <Input id="tname" v-model="form.name" placeholder="Chronos time sync" />
                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label>Abilities</Label>
                    <div v-for="ability in abilities" :key="ability" class="flex items-center gap-2">
                        <input
                            :id="ability"
                            type="checkbox"
                            class="size-4"
                            :checked="form.abilities.includes(ability)"
                            @change="toggleAbility(ability)"
                        >
                        <Label :for="ability" class="font-mono text-xs font-normal">{{ ability }}</Label>
                    </div>
                    <p v-if="form.errors.abilities" class="text-destructive text-xs">{{ form.errors.abilities }}</p>
                </div>

                <Button type="submit" :disabled="!canSubmit || form.processing">
                    <Plus class="size-4" /> Create token
                </Button>
            </form>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Abilities</TableHead>
                        <TableHead>Last used</TableHead>
                        <TableHead>Created</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!tokens.length" :colspan="5" text="No API tokens yet." />
                    <TableRow v-for="token in tokens" :key="token.id">
                        <TableCell class="font-medium">{{ token.name }}</TableCell>
                        <TableCell>
                            <Badge v-for="a in token.abilities ?? []" :key="a" variant="outline" class="mr-1 font-mono text-xs">
                                {{ a }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(token.last_used_at) }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDate(token.created_at) }}</TableCell>
                        <TableCell>
                            <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive size-8" @click="revoke(token)">
                                <Trash2 class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </SettingsLayout>
</template>
