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
    type: 'item';
    key: string;
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: string;
    can: boolean;
    ability?: string;
    subject?: string;
    order?: number;
};

export type SharedNavigationSeparator = {
    type: 'separator';
    key: string;
    can: boolean;
    order?: number;
};

export type SharedNavigationSubmenu = {
    type: 'submenu';
    key: string;
    title: string;
    icon?: string;
    can: boolean;
    ability?: string;
    subject?: string;
    order?: number;
    children: SharedNavigationNode[];
};

export type SharedNavigationGroup = {
    type: 'group';
    key: string;
    title: string;
    can: boolean;
    order?: number;
    children: SharedNavigationNode[];
};

export type SharedNavigationNode =
    | SharedNavigationItem
    | SharedNavigationSeparator
    | SharedNavigationSubmenu
    | SharedNavigationGroup;

export type SharedNavigation = {
    context: NavigationContext;
    main: SharedNavigationNode[];
};
