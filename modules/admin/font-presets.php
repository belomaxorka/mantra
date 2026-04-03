<?php declare(strict_types=1);
/**
 * Admin font presets.
 *
 * Each key maps to a font-family value and an optional Google Fonts import URL.
 * The 'inter' preset needs no import — it is loaded in the admin layout.
 */

return [
    'inter' => [
        'family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        'import' => null,
    ],
    'system' => [
        'family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
        'import' => null,
    ],
    'roboto' => [
        'family' => "'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'import' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap',
    ],
    'nunito' => [
        'family' => "'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'import' => 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap',
    ],
    'source-sans' => [
        'family' => "'Source Sans 3', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'import' => 'https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap',
    ],
    'jetbrains-mono' => [
        'family' => "'JetBrains Mono', 'Fira Code', 'SF Mono', Consolas, monospace",
        'import' => 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap',
    ],
];
