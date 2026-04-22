import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

export type NavigationContext = 'landlord' | 'tenant';

export type SharedNavigationItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: string;
    can: boolean;
};

export type SharedNavigation = {
    context: NavigationContext;
    main: SharedNavigationItem[];
};
