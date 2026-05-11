<?php
//
// Modernized browser detection
//

// Prevent multiple function declarations
if (defined('BROWSER_INC_LOADED')) {
    return;
}
define('BROWSER_INC_LOADED', true);

unset($BROWSER_AGENT, $BROWSER_VER, $BROWSER_PLATFORM);

function browser_get_agent(): string {
    global $BROWSER_AGENT;
    return $BROWSER_AGENT ?? 'OTHER';
}

function browser_get_version(): string {
    global $BROWSER_VER;
    return $BROWSER_VER ?? '0';
}

function browser_get_platform(): string {
    global $BROWSER_PLATFORM;
    return $BROWSER_PLATFORM ?? 'Other';
}

function browser_is_mac(): bool {
    return browser_get_platform() === 'Mac';
}

function browser_is_windows(): bool {
    return browser_get_platform() === 'Win';
}

function browser_is_ie(): bool {
    return browser_get_agent() === 'IE';
}

function browser_is_netscape(): bool {
    return browser_get_agent() === 'MOZILLA';
}

/*
 * Determine browser and version
 */
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (preg_match('/MSIE ([0-9]+\.[0-9]+)/', $user_agent, $matches)) {
    $BROWSER_VER   = $matches[1];
    $BROWSER_AGENT = 'IE';
} elseif (preg_match('/Opera\/([0-9]+\.[0-9]+)/', $user_agent, $matches)) {
    $BROWSER_VER   = $matches[1];
    $BROWSER_AGENT = 'OPERA';
} elseif (preg_match('/Mozilla\/([0-9]+\.[0-9]+)/', $user_agent, $matches)) {
    $BROWSER_VER   = $matches[1];
    $BROWSER_AGENT = 'MOZILLA';
} else {
    $BROWSER_VER   = '0';
    $BROWSER_AGENT = 'OTHER';
}

/*
 * Determine platform
 */
if (str_contains($user_agent, 'Win')) {
    $BROWSER_PLATFORM = 'Win';
} elseif (str_contains($user_agent, 'Mac')) {
    $BROWSER_PLATFORM = 'Mac';
} elseif (str_contains($user_agent, 'Linux')) {
    $BROWSER_PLATFORM = 'Linux';
} elseif (str_contains($user_agent, 'Unix')) {
    $BROWSER_PLATFORM = 'Unix';
} else {
    $BROWSER_PLATFORM = 'Other';
}

/*
// Debugging example
// echo "Agent: $user_agent";
// echo "<br>IE: ".browser_is_ie();
// echo "<br>Mac: ".browser_is_mac();
// echo "<br>Windows: ".browser_is_windows();
// echo "<br>Platform: ".browser_get_platform();
// echo "<br>Version: ".browser_get_version();
// echo "<br>Agent: ".browser_get_agent();
*/
?>
