<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Workspace } from '@/types'

const props = defineProps<{ workspace: Workspace }>()

const form = useForm({
    name: props.workspace.name,
    currency: props.workspace.currency,
    timezone: props.workspace.timezone,
})

const currencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD']
</script>

<template>
    <SettingsLayout>
        <div class="space-y-6 max-w-lg">
            <div>
                <h2 class="text-base font-semibold">Workspace</h2>
                <p class="text-muted-foreground text-sm">These details appear on your invoices.</p>
            </div>
            <form @submit.prevent="form.put(route('settings.workspace.update'))" class="space-y-4">
                <div class="space-y-1">
                    <Label for="wname">Workspace name</Label>
                    <Input id="wname" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-1">
                    <Label>Default currency</Label>
                    <Select v-model="form.currency">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="c in currencies" :key="c" :value="c">{{ c }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1">
                    <Label for="timezone">Timezone</Label>
                    <Input id="timezone" v-model="form.timezone" placeholder="UTC" />
                    <p v-if="form.errors.timezone" class="text-destructive text-xs">{{ form.errors.timezone }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">Save changes</Button>
            </form>
        </div>
    </SettingsLayout>
</template>
