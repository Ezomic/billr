<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    workspace_name: '',
})

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <AuthLayout>
        <Card>
            <CardHeader>
                <CardTitle>Create your account</CardTitle>
                <CardDescription>Start tracking time and sending invoices</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="name">Your name</Label>
                        <Input id="name" v-model="form.name" type="text" autocomplete="name" :class="{ 'border-destructive': form.errors.name }" />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" autocomplete="email" :class="{ 'border-destructive': form.errors.email }" />
                        <p v-if="form.errors.email" class="text-destructive text-sm">{{ form.errors.email }}</p>
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

                    <Separator />

                    <div class="space-y-1">
                        <Label for="workspace_name">Workspace name</Label>
                        <p class="text-muted-foreground text-xs">Your business or freelancer name — visible on invoices</p>
                        <Input id="workspace_name" v-model="form.workspace_name" type="text" :class="{ 'border-destructive': form.errors.workspace_name }" />
                        <p v-if="form.errors.workspace_name" class="text-destructive text-sm">{{ form.errors.workspace_name }}</p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        Create account
                    </Button>

                    <p class="text-muted-foreground text-center text-sm">
                        Already have an account?
                        <a :href="route('login')" class="text-foreground underline">Sign in</a>
                    </p>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
