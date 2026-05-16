<?php
/**
 * Skill name resolver — reads cached JSON built by tools/build_skill_index.php.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/skill_names.php';
 *   $name = skill_name(1177); // "Wind Strike" or null
 */

function _skill_names_load(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = __DIR__ . '/../data/skill_names.json';
    if (!is_file($path)) {
        $cache = [];
        return $cache;
    }
    $raw = file_get_contents($path);
    $data = $raw ? json_decode($raw, true) : null;
    $cache = (is_array($data) && isset($data['names']) && is_array($data['names']))
        ? $data['names']
        : [];
    return $cache;
}

function skill_name(int $skillId): ?string {
    if ($skillId <= 0) return null;
    $map = _skill_names_load();
    // JSON keys are strings
    return $map[(string)$skillId] ?? $map[$skillId] ?? null;
}
