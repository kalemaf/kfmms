<?php
$root = realpath(__DIR__ . '/../');
$patterns = [
    'short_echo' => '/<\?\=/',
    'mysql_calls' => '/\bmysql_\w+\b/i',
];
$report = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    // skip vendor
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array($ext, ['php','inc','php3','php4','php5','phtml'])) continue;
    $content = file_get_contents($path);
    foreach ($patterns as $name => $re) {
        if (preg_match_all($re, $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $match) {
                $report[] = [$name, str_replace($root . DIRECTORY_SEPARATOR, '', $path), $match[0]];
            }
        }
    }
}
$out = "Short-tag and legacy mysql_* usage report\n";
$out .= "Generated: " . date('c') . "\n\n";
$byfile = [];
foreach ($report as $r) {
    [$type, $file, $match] = $r;
    $byfile[$file][] = [$type, $match];
}
ksort($byfile);
foreach ($byfile as $file => $items) {
    $out .= "File: $file\n";
    foreach ($items as $it) {
        $out .= "  - {$it[0]}: {$it[1]}\n";
    }
    $out .= "\n";
}
file_put_contents(__DIR__ . '/short_tag_report.txt', $out);
echo "Report written to tools/short_tag_report.txt\n";
?>