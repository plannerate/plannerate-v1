export function trendToPaths(
    values: number[],
    height: number,
    width: number,
): { line: string; area: string } {
    if (values.length === 0) {
        return { line: '', area: '' };
    }

    const max = Math.max(...values, 1);
    const step = width / (values.length - 1 || 1);
    const points: string[] = [];

    values.forEach((value, index) => {
        const x = index * step;
        const y = height - (value / max) * height;
        points.push(`${x},${y}`);
    });

    const line = `M ${points.join(' L ')}`;
    const area = `${line} L ${width},${height} L 0,${height} Z`;

    return { line, area };
}

export function trendStrokeClass(accent: string): string {
    switch (accent) {
        case 'emerald':
            return 'stroke-emerald-500 dark:stroke-emerald-400';
        case 'sky':
            return 'stroke-sky-500 dark:stroke-sky-400';
        case 'amber':
            return 'stroke-amber-500 dark:stroke-amber-400';
        case 'slate':
            return 'stroke-slate-500 dark:stroke-slate-400';
        default:
            return 'stroke-muted-foreground/50';
    }
}

export function trendFillClass(accent: string): string {
    switch (accent) {
        case 'emerald':
            return 'fill-emerald-500/20 dark:fill-emerald-400/20';
        case 'sky':
            return 'fill-sky-500/20 dark:fill-sky-400/20';
        case 'amber':
            return 'fill-amber-500/20 dark:fill-amber-400/20';
        case 'slate':
            return 'fill-slate-500/20 dark:fill-slate-400/20';
        default:
            return 'fill-muted-foreground/10';
    }
}
