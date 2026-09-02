import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';

/** "#C9A24B" → "41 54% 54%" (the space-separated HSL triplet Tailwind tokens expect) */
export function hexToHslTriplet(hex: string): string | null {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!m) return null;
    const n = parseInt(m[1], 16);
    const r = ((n >> 16) & 255) / 255;
    const g = ((n >> 8) & 255) / 255;
    const b = (n & 255) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;
    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) * 60;
        else if (max === g) h = ((b - r) / d + 2) * 60;
        else h = ((r - g) / d + 4) * 60;
    }
    return `${Math.round(h)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

/** Relative luminance, to pick dark or light text on the brand colour. */
function isLight(hex: string): boolean {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!m) return true;
    const n = parseInt(m[1], 16);
    const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.45;
}

export function applyBrandColor(hex: string | null | undefined): void {
    if (typeof document === 'undefined') return;
    const triplet = hex ? hexToHslTriplet(hex) : null;
    const root = document.documentElement;
    const vars = ['--primary', '--ring', '--sidebar-primary', '--sidebar-ring'];
    if (!triplet) {
        vars.forEach((v) => root.style.removeProperty(v));
        root.style.removeProperty('--primary-foreground');
        root.style.removeProperty('--sidebar-primary-foreground');
        return;
    }
    vars.forEach((v) => root.style.setProperty(v, triplet));
    const fg = isLight(hex as string) ? '24 30% 8%' : '0 0% 100%';
    root.style.setProperty('--primary-foreground', fg);
    root.style.setProperty('--sidebar-primary-foreground', fg);
}

/** Applies the owner's brand colour (Settings → Salon) to the design tokens. */
export function useBrand(): void {
    const page = usePage<SharedData>();
    onMounted(() => applyBrandColor(page.props.salon?.brand_color));
    watch(
        () => page.props.salon?.brand_color,
        (c) => applyBrandColor(c),
    );
}
