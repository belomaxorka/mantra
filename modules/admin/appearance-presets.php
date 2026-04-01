<?php
/**
 * Admin accent color presets.
 *
 * Each key maps to CSS custom property overrides injected into :root
 * via the admin.head hook. Tailwind-derived palette values.
 */

return array(
    'indigo' => array(
        '--mn-primary'               => '#6366f1',
        '--mn-primary-hover'         => '#4f46e5',
        '--mn-primary-soft'          => 'rgba(99,102,241,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(129,140,248,.12)',
        '--mn-sidebar-active-border' => '#818cf8',
    ),
    'blue' => array(
        '--mn-primary'               => '#3b82f6',
        '--mn-primary-hover'         => '#2563eb',
        '--mn-primary-soft'          => 'rgba(59,130,246,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(96,165,250,.12)',
        '--mn-sidebar-active-border' => '#60a5fa',
    ),
    'sky' => array(
        '--mn-primary'               => '#0ea5e9',
        '--mn-primary-hover'         => '#0284c7',
        '--mn-primary-soft'          => 'rgba(14,165,233,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(56,189,248,.12)',
        '--mn-sidebar-active-border' => '#38bdf8',
    ),
    'teal' => array(
        '--mn-primary'               => '#14b8a6',
        '--mn-primary-hover'         => '#0d9488',
        '--mn-primary-soft'          => 'rgba(20,184,166,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(45,212,191,.12)',
        '--mn-sidebar-active-border' => '#2dd4bf',
    ),
    'emerald' => array(
        '--mn-primary'               => '#10b981',
        '--mn-primary-hover'         => '#059669',
        '--mn-primary-soft'          => 'rgba(16,185,129,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(52,211,153,.12)',
        '--mn-sidebar-active-border' => '#34d399',
    ),
    'amber' => array(
        '--mn-primary'               => '#f59e0b',
        '--mn-primary-hover'         => '#d97706',
        '--mn-primary-soft'          => 'rgba(245,158,11,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(251,191,36,.12)',
        '--mn-sidebar-active-border' => '#fbbf24',
    ),
    'orange' => array(
        '--mn-primary'               => '#f97316',
        '--mn-primary-hover'         => '#ea580c',
        '--mn-primary-soft'          => 'rgba(249,115,22,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(251,146,60,.12)',
        '--mn-sidebar-active-border' => '#fb923c',
    ),
    'rose' => array(
        '--mn-primary'               => '#f43f5e',
        '--mn-primary-hover'         => '#e11d48',
        '--mn-primary-soft'          => 'rgba(244,63,94,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(251,113,133,.12)',
        '--mn-sidebar-active-border' => '#fb7185',
    ),
    'violet' => array(
        '--mn-primary'               => '#8b5cf6',
        '--mn-primary-hover'         => '#7c3aed',
        '--mn-primary-soft'          => 'rgba(139,92,246,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(167,139,250,.12)',
        '--mn-sidebar-active-border' => '#a78bfa',
    ),
    'slate' => array(
        '--mn-primary'               => '#64748b',
        '--mn-primary-hover'         => '#475569',
        '--mn-primary-soft'          => 'rgba(100,116,139,.08)',
        '--mn-sidebar-active-bg'     => 'rgba(148,163,184,.12)',
        '--mn-sidebar-active-border' => '#94a3b8',
    ),
);
