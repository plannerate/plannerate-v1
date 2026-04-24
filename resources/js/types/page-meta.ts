import type { BreadcrumbItem } from '@/types/navigation';

export type LayoutPageHeader = {
    title?: string;
    description?: string;
    createRoute?: string;
    createLabel?: string;
};

export type CrudPageMeta = {
    headTitle?: string;
    title?: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    createRoute?: string;
    createLabel?: string;
};

export type CrudPageMetaDefaults = {
    headTitle: string;
    title: string;
    description?: string;
    breadcrumbs: BreadcrumbItem[];
    createRoute?: string;
    createLabel?: string;
};
