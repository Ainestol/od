<?php
/**
 * CLI: parses L2 skill XMLs and outputs JSON map { skill_id: "Skill Name" }.
 *
 * Usage on server:
 *   sudo php /var/www/ordodraconis/tools/build_skill_index.php
 *
 * Optional argument — custom XML directory (default below):
 *   php tools/build_skill_index.php /opt/l2/ClassicLude/game/data/stats/skills
 *
 * Output: /var/www/ordodraconis/data/skill_names.json
 *   (gitignored on production; regenerate after server data update)
 */

declare(strict_types=1);

$xmlDir  = $argv[1] ?? '/opt/l2/ClassicLude/game/data/stats/skills';
$outPath = __DIR__ . '/../data/skill_names.json';

if (!is_dir($xmlDir)) {
    fwrite(STDERR, "ERR: directory not found: $xmlDir\n");
    exit(1);
}

// Ensure output dir exists
$outDir = dirname($outPath);
if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0775, true)) {
        fwrite(STDERR, "ERR: cannot create $outDir\n");
        exit(1);
    }
}

$names = [];      // skill_id => name
$totalFiles = 0;
$totalSkills = 0;

// Walk all XMLs (including subdirectories — L2J Mobius often groups them)
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($xmlDir));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'xml') continue;
    $totalFiles++;

    $path = $file->getPathname();
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        fwrite(STDERR, "WARN: failed to parse $path\n");
        continue;
    }

    // Common patterns:
    //   <list><skill id="1" name="Power Strike" ...></skill></list>
    //   <skills><skill id="..." name="..."/></skills>
    //   root <skill> directly
    // Use XPath to grab any <skill> with id+name
    foreach ($xml->xpath('//skill[@id and @name]') as $sk) {
        $id   = (int)$sk['id'];
        $name = trim((string)$sk['name']);
        if ($id > 0 && $name !== '') {
            // Keep first encountered name (skill XMLs often define multiple levels of same id)
            if (!isset($names[$id])) {
                $names[$id] = $name;
                $totalSkills++;
            }
        }
    }
}

ksort($names, SORT_NUMERIC);

$json = json_encode([
    'generated_at' => date('c'),
    'source_dir'   => $xmlDir,
    'count'        => count($names),
    'names'        => $names,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($outPath, $json) === false) {
    fwrite(STDERR, "ERR: cannot write $outPath\n");
    exit(1);
}

echo "OK: parsed {$totalFiles} XML files, indexed " . count($names) . " skills.\n";
echo "Output: $outPath\n";
