<?php
/**
 * Script for updating inv_weight values of artefacts inside containers.
 * PHP 7.4 compatible.
 * Adds a single WEIGHT FIXES section as a separator before overrides.
 * Reads configuration from config.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// === CONFIG ===
$config = require __DIR__ . '/config.php';
$artefactsFile = $config['artefactsFile'];
$containers = $config['containers'];
$is_debug = (bool) $config['is_debug'] ?? false;

// === FUNCTIONS ===

function readFileLines($filename)
{
    if (!file_exists($filename)) {
        exit("File $filename not found\n");
    }
    return file($filename, FILE_IGNORE_NEW_LINES);
}

function parseArtefacts($lines)
{
    $artefacts = [];
    $current = null;
    $isOverride = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if (preg_match('/^(!?)\[(af_[^\]]+)\]/i', $trim, $m)) {
            $isOverride = $m[1] === '!';
            $current = $m[2];
            if (!isset($artefacts[$current])) {
                $artefacts[$current] = ['weight' => null, 'is_override' => false];
            }
            continue;
        }

        if ($current && preg_match('/inv_weight\s*=\s*([0-9.]+)/i', $trim, $m)) {
            $weight = floatval($m[1]);
            if ($isOverride) {
                $artefacts[$current]['weight'] = $weight;
                $artefacts[$current]['is_override'] = true;
            } elseif ($artefacts[$current]['weight'] === null) {
                $artefacts[$current]['weight'] = $weight;
            }
        }
    }

    $result = [];
    foreach ($artefacts as $name => $data) {
        if ($data['weight'] !== null) {
            $result[$name] = $data['weight'];
        }
    }

    return $result;
}

function getContainerWeight($lines, $containerName)
{
    $pattern = '/^\[' . preg_quote($containerName, '/') . '\]/i';
    $inSection = false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if (preg_match($pattern, $trim)) {
            $inSection = true;
            continue;
        }
        if ($inSection && preg_match('/^\[/', $trim)) {
            break;
        }
        if ($inSection && preg_match('/inv_weight\s*=\s*([0-9.]+)/i', $trim, $m)) {
            return floatval($m[1]);
        }
    }
    return null;
}

function detectContainerName($filename, $content)
{
    $result = null;

    if (empty($filename)
        || empty($content)
    ) {
        return $result;
    }

    // llmc special case: container is "lead_box"
    if (stripos($filename, 'llmc') !== false) {
        if (preg_match('/^\[(lead_box)\]/mi', $content, $m)) {
            $result = $m[1];
        }
    }

    // fallback: find first [af_<container>] section
    if ($result === null
        && preg_match('/^\[(af_[a-zA-Z0-9_-]+)\]/mi', $content, $m)
    ) {
        $result = $m[1];
    }

    return $result;
}

function updateContainerFile($filename, $artefacts, $is_debug)
{
    echo "Processing file \"$filename\":\n\n";

    $lines = readFileLines($filename);
    $content = implode("\n", $lines);

    $containerName = detectContainerName($filename, $content);

    if (!$containerName) {
        echo "  Container section not found!\n";
        return;
    }

    $containerWeight = getContainerWeight($lines, $containerName);

    if ($containerWeight === null) {
        echo "  Container inv_weight not found for $containerName\n";
        return;
    }

    // Match [af_<artefact>_<container>]<anything except \n>
    preg_match_all('/\[(af_[a-z0-9_]+)_' . preg_quote($containerName, '/') . '\][^\n]*/i', $content, $matches);
    $artefactNames = array_unique($matches[1]);

    if (empty($artefactNames)) {
        echo "  No artefacts found inside container\n";
        return;
    }

    $updatedContent = $content;
    $added = 0;
    $updated = 0;

    // Collect existing overrides
    preg_match_all('/!\[([a-z0-9_]+)\][\s\S]*?(?=!\[|\z)/i', $updatedContent, $existingMatches);

    $existingOverrides = [];

    if (!empty($existingMatches[0])) {
        foreach ($existingMatches[0] as $block) {
            if (preg_match('/!\[([a-z0-9_]+)\]/i', $block, $m)) {
                // [section name] => whole override block
                $existingOverrides[$m[1]] = $block;
            }

            // remove from content
            $updatedContent = str_replace($block, '', $updatedContent);
        }
    }

    $weightFixesHeader = "\n\n\n;;--==============| WEIGHT FIXES |==============\n\n";
    $weightFixesInserted = false;
    $finalOverrides = [];

    // Generate overrides for artefacts
    foreach ($artefactNames as $afName) {
        if (!isset($artefacts[$afName])) {
            echo "  ⚠ Missing artefact weight for $afName — skipped\n";
            continue;
        }

        $afWeight = $artefacts[$afName];
        $newWeight = round($afWeight + $containerWeight, 3);
        $sectionName = "{$afName}_{$containerName}";

        // update existing override
        if (isset($existingOverrides[$sectionName])) {
            $oldBlock = $existingOverrides[$sectionName];

            $newBlock = preg_replace(
                '/inv_weight\s*=\s*[0-9.]+/i',
                "inv_weight = {$newWeight}",
                $oldBlock
            );

            $finalOverrides[$sectionName] = rtrim($newBlock);

            unset($existingOverrides[$sectionName]);

            $updated++;
        } else {
            // insert new override
            $finalOverrides[$sectionName] = "![{$sectionName}]:{$afName}, {$containerName}\ninv_weight = {$newWeight}";

            $added++;
        }
    }

    // Insert WEIGHT FIXES header
    if (!empty($finalOverrides)) {
        $updatedContent .= $weightFixesHeader;

        // insert finalized overrides
        foreach ($finalOverrides as $block) {
            $updatedContent .= $block . "\n\n";
        }
    }

    if ($is_debug) {
        print_r($updatedContent);
    } else {
        file_put_contents($filename, $updatedContent);
    }

    echo "  ✅ Updated: {$updated}, Added: {$added}\n\n";
}

// === MAIN ===

echo "\n=== Reading artefacts ===\n";
$artefacts = parseArtefacts(readFileLines($artefactsFile));
echo "\nFound artefacts: " . count($artefacts) . "\n\n";

foreach ($containers as $file) {
    updateContainerFile($file, $artefacts, $is_debug);
}

echo "\nDone!\n";
