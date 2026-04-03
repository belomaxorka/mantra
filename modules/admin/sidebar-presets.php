<?php declare(strict_types=1);
/**
 * Admin sidebar color presets.
 *
 * Each key maps to CSS custom property overrides for the sidebar,
 * injected into :root via the admin.head hook.
 */

return [
    'dark' => [
        '--mn-sidebar-bg' => '#0f172a',
        '--mn-sidebar-text' => '#94a3b8',
        '--mn-sidebar-text-hover' => '#cbd5e1',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.05)',
        '--mn-sidebar-group' => '#475569',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.08)',
    ],
    'midnight' => [
        '--mn-sidebar-bg' => '#030712',
        '--mn-sidebar-text' => '#9ca3af',
        '--mn-sidebar-text-hover' => '#d1d5db',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.05)',
        '--mn-sidebar-group' => '#4b5563',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.06)',
    ],
    'charcoal' => [
        '--mn-sidebar-bg' => '#1c1917',
        '--mn-sidebar-text' => '#a8a29e',
        '--mn-sidebar-text-hover' => '#d6d3d1',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.05)',
        '--mn-sidebar-group' => '#57534e',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.08)',
    ],
    'ocean' => [
        '--mn-sidebar-bg' => '#0c4a6e',
        '--mn-sidebar-text' => '#7dd3fc',
        '--mn-sidebar-text-hover' => '#bae6fd',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.07)',
        '--mn-sidebar-group' => '#0369a1',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.08)',
    ],
    'forest' => [
        '--mn-sidebar-bg' => '#064e3b',
        '--mn-sidebar-text' => '#6ee7b7',
        '--mn-sidebar-text-hover' => '#a7f3d0',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.07)',
        '--mn-sidebar-group' => '#047857',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.08)',
    ],
    'plum' => [
        '--mn-sidebar-bg' => '#3b0764',
        '--mn-sidebar-text' => '#c4b5fd',
        '--mn-sidebar-text-hover' => '#ddd6fe',
        '--mn-sidebar-text-active' => '#fff',
        '--mn-sidebar-hover-bg' => 'rgba(255,255,255,.06)',
        '--mn-sidebar-group' => '#6b21a8',
        '--mn-sidebar-divider' => 'rgba(255,255,255,.08)',
    ],
    'light' => [
        '--mn-sidebar-bg' => '#f8fafc',
        '--mn-sidebar-text' => '#64748b',
        '--mn-sidebar-text-hover' => '#334155',
        '--mn-sidebar-text-active' => '#0f172a',
        '--mn-sidebar-hover-bg' => 'rgba(0,0,0,.04)',
        '--mn-sidebar-group' => '#94a3b8',
        '--mn-sidebar-divider' => 'rgba(0,0,0,.08)',
    ],
];
