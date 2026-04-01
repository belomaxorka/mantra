<?php
/**
 * Seed — generate fake data for development and testing.
 *
 * Usage:
 *   php tools/seed.php                  # defaults: 50 posts, 5 pages
 *   php tools/seed.php --posts=100
 *   php tools/seed.php --posts=30 --pages=10
 *   php tools/seed.php --clear           # delete all seeded data, then generate defaults
 *   php tools/seed.php --clear-only      # delete all seeded data without generating new
 *
 * Seeded documents have "_seed": true so they can be cleared without touching real content.
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────

chdir(dirname(__DIR__));
require 'core/bootstrap.php';

// ── CLI arguments ───────────────────────────────────────────────────────────

$opts = getopt('', array('posts::', 'pages::', 'clear', 'clear-only'));

$doClear = isset($opts['clear']) || isset($opts['clear-only']);
$generateAfter = !isset($opts['clear-only']);

$numPosts = isset($opts['posts']) && $opts['posts'] !== false ? max(0, (int)$opts['posts']) : 50;
$numPages = isset($opts['pages']) && $opts['pages'] !== false ? max(0, (int)$opts['pages']) : 5;

// ── Helpers ─────────────────────────────────────────────────────────────────

function seed_pick($arr) {
    return $arr[array_rand($arr)];
}

function seed_sentence($minWords = 4, $maxWords = 10) {
    $words = array(
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur',
        'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor',
        'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna',
        'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis',
        'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi',
        'aliquip', 'ex', 'ea', 'commodo', 'consequat', 'duis',
        'aute', 'irure', 'in', 'reprehenderit', 'voluptate',
        'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur',
        'excepteur', 'sint', 'occaecat', 'cupidatat', 'non',
        'proident', 'sunt', 'culpa', 'qui', 'officia', 'deserunt',
        'mollit', 'anim', 'id', 'est', 'laborum',
    );
    $count = rand($minWords, $maxWords);
    $out = array();
    for ($i = 0; $i < $count; $i++) {
        $out[] = $words[array_rand($words)];
    }
    $out[0] = ucfirst($out[0]);
    return implode(' ', $out);
}

function seed_paragraph($minSentences = 3, $maxSentences = 7) {
    $count = rand($minSentences, $maxSentences);
    $sentences = array();
    for ($i = 0; $i < $count; $i++) {
        $sentences[] = seed_sentence(6, 14) . '.';
    }
    return implode(' ', $sentences);
}

function seed_html_content($paragraphs = null) {
    $count = $paragraphs ?: rand(2, 5);
    $parts = array();
    for ($i = 0; $i < $count; $i++) {
        $parts[] = '<p>' . seed_paragraph() . '</p>';
    }
    return implode("\n", $parts);
}

function seed_title() {
    $templates = array(
        'How to {verb} {noun}',
        'The {adjective} guide to {noun}',
        '{number} ways to improve your {noun}',
        'Understanding {noun} in {year}',
        'Why {noun} matters',
        'A deep dive into {noun}',
        'Getting started with {noun}',
        '{adjective} {noun}: a practical overview',
        'The future of {noun}',
        'Building {adjective} {noun} from scratch',
    );

    $verbs = array('build', 'optimize', 'manage', 'deploy', 'design', 'scale', 'test', 'automate', 'monitor', 'secure');
    $nouns = array('web apps', 'databases', 'APIs', 'microservices', 'containers', 'workflows', 'pipelines', 'systems', 'servers', 'networks', 'authentication', 'caching', 'logging', 'performance', 'architecture');
    $adjectives = array('complete', 'practical', 'modern', 'essential', 'ultimate', 'simple', 'advanced', 'reliable', 'scalable', 'efficient');

    $title = seed_pick($templates);
    $title = str_replace('{verb}', seed_pick($verbs), $title);
    $title = str_replace('{noun}', seed_pick($nouns), $title);
    $title = str_replace('{adjective}', seed_pick($adjectives), $title);
    $title = str_replace('{number}', rand(3, 15), $title);
    $title = str_replace('{year}', rand(2025, 2027), $title);

    return ucfirst($title);
}

function seed_page_title() {
    $titles = array(
        'About us', 'Contact', 'Privacy policy', 'Terms of service',
        'FAQ', 'Services', 'Portfolio', 'Our team', 'Careers',
        'Documentation', 'Support', 'Pricing', 'Features', 'Partners',
        'Press', 'Resources', 'Roadmap', 'Changelog', 'Status',
        'Getting started',
    );
    return seed_pick($titles);
}

function seed_timestamp($daysAgo = 90) {
    $ts = time() - rand(0, $daysAgo * 86400);
    return date(Clock::STORAGE_FORMAT, $ts);
}

// ── Clear seeded data ───────────────────────────────────────────────────────

function clear_seeded($collection) {
    $dir = MANTRA_CONTENT . '/' . $collection;
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;
    foreach (glob($dir . '/*.json') as $file) {
        if (basename($file) === 'config.json') continue;
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data) && !empty($data['_seed'])) {
            @unlink($file);
            @unlink($file . '.lock');
            $count++;
        }
    }
    return $count;
}

// ── Main ────────────────────────────────────────────────────────────────────

echo "Mantra CMS — Data Seeder\n";
echo str_repeat('-', 40) . "\n";

if ($doClear) {
    $cleared = 0;
    $cleared += clear_seeded('posts');
    $cleared += clear_seeded('pages');
    $cleared += clear_seeded('categories');
    echo "Cleared {$cleared} seeded documents.\n";

    if (!$generateAfter) {
        echo "Done.\n";
        exit(0);
    }
    echo "\n";
}

$db = app()->db();
$statuses = array('published', 'published', 'published', 'draft'); // 75% published

// ── Generate categories ────────────────────────────────────────────────────

$categoryDefs = array(
    array('title' => 'News',      'slug' => 'news',      'description' => 'Latest news and announcements', 'order' => 0),
    array('title' => 'Tutorials',  'slug' => 'tutorials',  'description' => 'Step-by-step guides and how-tos', 'order' => 1),
    array('title' => 'Reviews',    'slug' => 'reviews',    'description' => 'In-depth reviews and comparisons', 'order' => 2),
    array('title' => 'Opinion',    'slug' => 'opinion',    'description' => 'Opinions and editorials',         'order' => 3),
    array('title' => 'Guides',     'slug' => 'guides',     'description' => 'Comprehensive reference guides',  'order' => 4),
);

echo "Generating " . count($categoryDefs) . " categories...";
foreach ($categoryDefs as $catDef) {
    if ($db->exists('categories', $catDef['slug'])) {
        continue;
    }
    $now = clock()->timestamp();
    $db->write('categories', $catDef['slug'], array(
        'title'       => $catDef['title'],
        'slug'        => $catDef['slug'],
        'description' => $catDef['description'],
        'order'       => $catDef['order'],
        'created_at'  => $now,
        'updated_at'  => $now,
        '_seed'       => true,
    ));
}
echo " done.\n";

$categorySlugs = array_map(function ($c) { return $c['slug']; }, $categoryDefs);
$categorySlugs[] = ''; // some posts without category

// Get first user as author
$users = $db->query('users', array(), array('limit' => 1));
$author = !empty($users) ? $users[0]['username'] : 'admin';
$authorId = !empty($users) ? $users[0]['_id'] : '';

// ── Generate posts ──────────────────────────────────────────────────────────

if ($numPosts > 0) {
    echo "Generating {$numPosts} posts...";
    $usedSlugs = array();

    for ($i = 0; $i < $numPosts; $i++) {
        $title = seed_title();
        $slug = slugify($title);

        // Ensure unique slug
        $base = $slug;
        $n = 1;
        while (isset($usedSlugs[$slug]) || $db->read('posts', $slug) !== null) {
            $slug = $base . '-' . (++$n);
        }
        $usedSlugs[$slug] = true;

        $createdAt = seed_timestamp(90);
        $updatedAt = seed_timestamp(30);
        if ($updatedAt < $createdAt) {
            $updatedAt = $createdAt;
        }

        $data = array(
            'title' => $title,
            'slug' => $slug,
            'content' => seed_html_content(),
            'excerpt' => seed_sentence(8, 20) . '.',
            'status' => seed_pick($statuses),
            'category' => seed_pick($categorySlugs),
            'image' => '',
            'author' => $author,
            'author_id' => $authorId,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            '_seed' => true,
        );

        $db->write('posts', $slug, $data);
    }
    echo " done.\n";
}

// ── Generate pages ──────────────────────────────────────────────────────────

if ($numPages > 0) {
    echo "Generating {$numPages} pages...";
    $usedPageSlugs = array();
    $allPageTitles = array(
        'About us', 'Contact', 'Privacy policy', 'Terms of service',
        'FAQ', 'Services', 'Portfolio', 'Our team', 'Careers',
        'Documentation', 'Support', 'Pricing', 'Features', 'Partners',
        'Press', 'Resources', 'Roadmap', 'Changelog', 'Status',
        'Getting started',
    );
    shuffle($allPageTitles);

    for ($i = 0; $i < $numPages; $i++) {
        $title = $i < count($allPageTitles) ? $allPageTitles[$i] : 'Page ' . ($i + 1);
        $slug = slugify($title);

        $base = $slug;
        $n = 1;
        while (isset($usedPageSlugs[$slug]) || $db->read('pages', $slug) !== null) {
            $slug = $base . '-' . (++$n);
        }
        $usedPageSlugs[$slug] = true;

        $createdAt = seed_timestamp(60);

        $data = array(
            'title' => $title,
            'slug' => $slug,
            'content' => seed_html_content(rand(2, 4)),
            'status' => 'published',
            'show_in_navigation' => ($i < 5),
            'navigation_order' => ($i + 1) * 10,
            'author' => $author,
            'author_id' => $authorId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            '_seed' => true,
        );

        $db->write('pages', $slug, $data);
    }
    echo " done.\n";
}

// ── Summary ─────────────────────────────────────────────────────────────────

$totalPosts = $db->count('posts');
$totalPages = $db->count('pages');
$totalCategories = $db->count('categories');

echo "\nSummary:\n";
echo "  Categories: {$totalCategories} total\n";
echo "  Posts: {$totalPosts} total ({$numPosts} seeded)\n";
echo "  Pages: {$totalPages} total ({$numPages} seeded)\n";
echo "Done.\n";
