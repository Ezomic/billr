import { usePage } from '@inertiajs/vue3'
import { watchEffect } from 'vue'
import { toast } from 'vue-sonner'
import type { SharedProps } from '@/types'

export function useFlash() {
    const page = usePage<SharedProps>()

    watchEffect(() => {
        if (page.props.flash.success) toast.success(page.props.flash.success)
        if (page.props.flash.error) toast.error(page.props.flash.error)
    })
}
