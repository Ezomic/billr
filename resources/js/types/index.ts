export interface User {
    id: number;
    name: string;
    email: string;
    type: "freelancer" | "client";
}

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    currency: string;
    timezone: string;
    payment_terms_days: number;
}

export interface PortalApp {
    slug: string;
    name: string;
    initials: string;
    accent: string | null;
    launch_url: string;
    current: boolean;
}

export interface PortalCategory {
    category: string;
    apps: PortalApp[];
}

export interface SharedProps {
    auth: {
        user: User | null;
        workspace: Workspace | null;
        workspaces: Workspace[];
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    portalApps: PortalApp[];
    portalCategories: PortalCategory[];
}
