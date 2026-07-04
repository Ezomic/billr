<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

const props = defineProps<{
    token: string
    email: string
    type: 'workspace' | 'client'
    workspaceName?: string
    clientName?: string
}>()

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
})

const title = props.type === 'workspace'
    ? `Join ${props.workspaceName}`
    : `Access ${props.clientName} portal`

const description = props.type === 'workspace'
    ? 'Set up your account to collaborate on this workspace'
    : 'Create an account to view your invoices and project updates'

function submit() {
    form.post(route('invitations.accept', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <AuthLayout>
        <Card>
            <CardHeader>
                <CardTitle>{{ title }}</CardTitle>
                <CardDescription>{{ description }}</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label>Email</Label>
                        <Input :value="email" disabled />
                    </div>

                    <div class="space-y-1">
                        <Label for="name">Your name</Label>
                        <Input id="name" v-model="form.name" type="text" autocomplete="name" :class="{ 'border-destructive': form.errors.name }" />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1">
                        <Label for="password">Password</Label>
                        <Input id="password" v-model="form.password" type="password" autocomplete="new-password" :class="{ 'border-destructive': form.errors.password }" />
                        <p v-if="form.errors.password" class="text-destructive text-sm">{{ form.errors.password }}</p>
                    </div>

                    <div class="space-y-1">
                        <Label for="password_confirmation">Confirm password</Label>
                        <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        Accept invitation
                    </Button>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
