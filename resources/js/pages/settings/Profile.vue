<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import type { SharedProps } from '@/types'

const page = usePage<SharedProps>()

const profileForm = useForm({
    name: page.props.auth.user?.name ?? '',
    email: page.props.auth.user?.email ?? '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})
</script>

<template>
    <SettingsLayout>
        <div class="space-y-8 max-w-lg">
            <!-- Profile info -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold">Profile</h2>
                    <p class="text-muted-foreground text-sm">Update your name and email address.</p>
                </div>
                <form @submit.prevent="profileForm.put(route('settings.profile.update'))" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="profileForm.name" :class="{ 'border-destructive': profileForm.errors.name }" />
                        <p v-if="profileForm.errors.name" class="text-destructive text-xs">{{ profileForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="profileForm.email" type="email" :class="{ 'border-destructive': profileForm.errors.email }" />
                        <p v-if="profileForm.errors.email" class="text-destructive text-xs">{{ profileForm.errors.email }}</p>
                    </div>
                    <Button type="submit" :disabled="profileForm.processing">Save changes</Button>
                </form>
            </div>

            <Separator />

            <!-- Change password -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold">Password</h2>
                    <p class="text-muted-foreground text-sm">Choose a strong password.</p>
                </div>
                <form @submit.prevent="passwordForm.put(route('settings.profile.password'), { onSuccess: () => passwordForm.reset() })" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="current_password">Current password</Label>
                        <Input id="current_password" v-model="passwordForm.current_password" type="password"
                            :class="{ 'border-destructive': passwordForm.errors.current_password }" />
                        <p v-if="passwordForm.errors.current_password" class="text-destructive text-xs">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="new_password">New password</Label>
                        <Input id="new_password" v-model="passwordForm.password" type="password"
                            :class="{ 'border-destructive': passwordForm.errors.password }" />
                        <p v-if="passwordForm.errors.password" class="text-destructive text-xs">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="password_confirmation">Confirm new password</Label>
                        <Input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" />
                    </div>
                    <Button type="submit" :disabled="passwordForm.processing">Update password</Button>
                </form>
            </div>
        </div>
    </SettingsLayout>
</template>
