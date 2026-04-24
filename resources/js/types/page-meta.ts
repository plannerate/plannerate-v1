import type { BreadcrumbItem } from '@/types/navigation';

export type LayoutHeaderAction = {
    label: string;
    href: string;
    variant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'gradient' | 'destructive';
};

export type LayoutPageHeader = {
    title?: string;
    description?: string;
    createRoute?: string;
    createLabel?: string;
    actions?: LayoutHeaderAction[];
};

export type CrudPageMeta = {
    headTitle?: string;
    title?: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    createRoute?: string;
    createLabel?: string;
    headerActions?: LayoutHeaderAction[];
};

export type CrudPageMetaDefaults = {
    headTitle: string;
    title: string;
    description?: string;
    breadcrumbs: BreadcrumbItem[];
    createRoute?: string;
    createLabel?: string;
    headerActions?: LayoutHeaderAction[];
};
