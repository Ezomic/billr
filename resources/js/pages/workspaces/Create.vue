<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

const form = useForm({
    name: '',
})

function submit() {
    form.post(route('workspaces.store'))
}
</script>

<template>
    <AuthLayout>
        <Card>
            <CardHeader>
                <CardTitle>Create your workspace</CardTitle>
                <CardDescription>You need a workspace before you can track time or send invoices</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="name">Workspace name</Label>
                        <p class="text-muted-foreground text-xs">Your business or freelancer name, visible on invoices</p>
                        <Input id="name" v-model="form.name" type="text" :class="{ 'border-destructive': form.errors.name }" />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        Create workspace
                    </Button>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
