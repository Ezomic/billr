export interface User {
    id: number
    name: string
    email: string
    type: 'freelancer' | 'client'
}

export interface Workspace {
    id: number
    name: string
    slug: string
    currency: string
    timezone: string
}

export interface SharedProps {
    auth: {
        user: User | null
        workspace: Workspace | null
    }
    flash: {
        success: string | null
        error: string | null
    }
}
