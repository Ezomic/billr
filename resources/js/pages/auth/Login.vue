<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

defineProps<{ devLogin?: boolean }>()

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <AuthLayout>
        <Card>
            <CardHeader>
                <CardTitle>Sign in</CardTitle>
                <CardDescription>Enter your email and password to continue</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            :class="{ 'border-destructive': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="text-destructive text-sm">{{ form.errors.email }}</p>
                    </div>

                    <div class="space-y-1">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            :class="{ 'border-destructive': form.errors.password }"
                        />
                        <p v-if="form.errors.password" class="text-destructive text-sm">{{ form.errors.password }}</p>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        Sign in
                    </Button>

                    <p class="text-muted-foreground text-center text-sm">
                        Don't have an account?
                        <a :href="route('register')" class="text-foreground underline">Create one</a>
                    </p>

                    <template v-if="devLogin">
                        <Separator />
                        <Button as="a" :href="route('dev-login')" variant="outline" class="w-full">
                            Dev login
                        </Button>
                    </template>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
