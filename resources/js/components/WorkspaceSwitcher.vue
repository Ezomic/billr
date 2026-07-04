<script setup lang="ts">
import { router, usePage } from "@inertiajs/vue3";
import type { SharedProps } from "@/types";
import { ChevronsUpDown, Building2, Check, Plus } from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";

const page = usePage<SharedProps>();

function switchWorkspace(workspaceId: number) {
    if (workspaceId === page.props.auth.workspace?.id) return;
    router.post(route("workspaces.switch", { workspace: workspaceId }));
}

function createWorkspace() {
    const name = prompt("Workspace name");
    if (!name?.trim()) return;
    router.post(route("workspaces.store"), { name: name.trim() });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                class="hover:bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition-colors"
            >
                <div
                    class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-md"
                >
                    <Building2 class="size-4" />
                </div>
                <span class="min-w-0 flex-1 truncate text-sm font-semibold">
                    {{ page.props.auth.workspace?.name }}
                </span>
                <ChevronsUpDown class="text-muted-foreground size-4 shrink-0" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="bottom" align="start" class="w-56">
            <DropdownMenuLabel class="text-muted-foreground text-xs"
                >Workspaces</DropdownMenuLabel
            >
            <DropdownMenuItem
                v-for="workspace in page.props.auth.workspaces"
                :key="workspace.id"
                class="flex items-center gap-2"
                @click="switchWorkspace(workspace.id)"
            >
                <Check
                    class="size-4 shrink-0"
                    :class="
                        workspace.id === page.props.auth.workspace?.id
                            ? 'opacity-100'
                            : 'opacity-0'
                    "
                />
                <span class="truncate">{{ workspace.name }}</span>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                class="flex items-center gap-2"
                @click="createWorkspace"
            >
                <Plus class="size-4 shrink-0" />
                New workspace
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
