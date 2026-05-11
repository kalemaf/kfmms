<?php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getcwd()));
foreach ($it as $f) {
    if ($f->isFile() && strtolower(pathinfo($f->getPathname(), PATHINFO_EXTENSION)) === 'php') {
        $path = $f->getPathname();
        echo "--- $path\n";
        $cmd = 'php -l ' . escapeshellarg($path);
        passthru($cmd, $ret);
    }
}
?>