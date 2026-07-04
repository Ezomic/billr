<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import { Trash2 } from 'lucide-vue-next'

interface Member { id: number; name: string; email: string; pivot: { role: string } }
interface PendingInvite { id: number; email: string; role: string; created_at: string }

const props = defineProps<{
    members: Member[]
    invitations: PendingInvite[]
    isOwner: boolean
}>()

const inviteForm = useForm({ email: '', role: 'member' })

function initials(name: string) {
    return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

function removeMember(member: Member) {
    if (!confirm(`Remove ${member.name} from the workspace?`)) return
    useForm({}).delete(route('settings.members.remove', member.id))
}
</script>

<template>
    <SettingsLayout>
        <div class="space-y-8 max-w-lg">
            <div>
                <h2 class="text-base font-semibold">Members</h2>
                <p class="text-muted-foreground text-sm">Manage who has access to this workspace.</p>
            </div>

            <!-- Current members -->
            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between"
                >
                    <div class="flex items-center gap-3">
                        <Avatar class="size-8">
                            <AvatarFallback class="text-xs">{{ initials(member.name) }}</AvatarFallback>
                        </Avatar>
                        <div>
                            <p class="text-sm font-medium">{{ member.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ member.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="outline" class="capitalize">{{ member.pivot.role }}</Badge>
                        <Button
                            v-if="isOwner && member.pivot.role !== 'owner'"
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-destructive"
                            @click="removeMember(member)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Pending invitations -->
            <div v-if="invitations.length" class="space-y-3">
                <p class="text-sm font-medium text-muted-foreground">Pending invitations</p>
                <div v-for="inv in invitations" :key="inv.id" class="flex items-center justify-between">
                    <p class="text-sm">{{ inv.email }}</p>
                    <Badge variant="outline">Pending</Badge>
                </div>
            </div>

            <Separator v-if="isOwner" />

            <!-- Invite form -->
            <div v-if="isOwner" class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold">Invite someone</h3>
                    <p class="text-muted-foreground text-xs mt-0.5">They'll receive a link to join the workspace.</p>
                </div>
                <form @submit.prevent="inviteForm.post(route('settings.members.invite'), { onSuccess: () => inviteForm.reset() })" class="flex gap-2">
                    <Input
                        v-model="inviteForm.email"
                        type="email"
                        placeholder="colleague@example.com"
                        class="flex-1"
                        :class="{ 'border-destructive': inviteForm.errors.email }"
                    />
                    <Button type="submit" :disabled="inviteForm.processing">Send invite</Button>
                </form>
                <p v-if="inviteForm.errors.email" class="text-destructive text-xs">{{ inviteForm.errors.email }}</p>
            </div>
        </div>
    </SettingsLayout>
</template>
