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

export function applyBrandColor(hex: string | null | undefined): void {
    if (typeof document === 'undefined') return;
    const triplet = hex ? hexToHslTriplet(hex) : null;
    const root = document.documentElement;
    // Only a small accent variable; buttons/links keep the neutral design tokens.
    if (triplet) root.style.setProperty('--brand', triplet);
    else root.style.removeProperty('--brand');
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
