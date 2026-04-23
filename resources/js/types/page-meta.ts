import type { BreadcrumbItem } from '@/types/navigation';

export type LayoutPageHeader = {
    title?: string;
    description?: string;
};

export type CrudPageMeta = {
    headTitle?: string;
    title?: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
};

export type CrudPageMetaDefaults = {
    headTitle: string;
    title: string;
    description?: string;
    breadcrumbs: BreadcrumbItem[];
};
