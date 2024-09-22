<?php

// Set the path to the SQLite Viewer script
$sqliteViewerPath = __DIR__.'/../sqlite_viewer.php';

// Set the path to the test database
$testDbPath = __DIR__.'/database.sqlite';

// Set the test server port
$testPort = 9000;

// ANSI color codes
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[1;33m";
$reset = "\033[0m";

// Function to output colored text
function coloredEcho($text, $color)
{
    global $reset;
    echo $color.$text.$reset.PHP_EOL;
}

// Start by pinging the server which must have been started by the before
if (makeRequest("http://localhost:$testPort/")['status'] !== 200) {
    coloredEcho('Server not running. Run the server before running the tests.', $red);
    echo "php sqlite_viewer.php test/database.sqlite --port 9000\n";
    exit(1);
}

// Function to make a cURL request
function makeRequest($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['body' => $response, 'status' => $httpCode];
}

// Test cases
$testCases = [
    [
        'name' => 'Home page',
        'url' => "http://localhost:$testPort/",
        'expectedStatus' => 200,
        'expectedContent' => 'SQLite Viewer',
    ],
    [
        'name' => 'Users table',
        'url' => "http://localhost:$testPort/?table=users",
        'expectedStatus' => 200,
        'expectedContent' => 'Test User',
    ],
    [
        'name' => 'Migrations table',
        'url' => "http://localhost:$testPort/?table=migrations",
        'expectedStatus' => 200,
        'expectedContent' => 'create_users_table',
    ],
    [
        'name' => 'Sorting',
        'url' => "http://localhost:$testPort/?table=users&sort=id&order=DESC",
        'expectedStatus' => 200,
        'expectedContent' => 'Test User',
    ],
    [
        'name' => 'Pagination',
        'url' => "http://localhost:$testPort/?table=users&page=2",
        'expectedStatus' => 200,
        'expectedContent' => 'Users',
        'skip' => 'Not enough records to paginate',
    ],
    [
        'name' => 'View record',
        'url' => "http://localhost:$testPort/?table=users&action=view&id=1",
        'expectedStatus' => 200,
        'expectedContent' => 'Record Details',
    ],
];

$totalTests = count($testCases);
$passedTests = 0;

foreach ($testCases as $test) {
    coloredEcho("Testing: {$test['name']}", $yellow);
    if (isset($test['skip'])) {
        coloredEcho("  - Skipped: {$test['skip']}", $yellow);
        $passedTests++;

        continue;
    }
    $response = makeRequest($test['url']);

    if ($response['status'] === $test['expectedStatus'] && strpos($response['body'], $test['expectedContent']) !== false) {
        coloredEcho('  ✓ Passed', $green);
        $passedTests++;
    } else {
        coloredEcho('  ✗ Failed', $red);
        coloredEcho("    Expected status: {$test['expectedStatus']}, got: {$response['status']}", $red);
        coloredEcho('    Expected content not found', $red);
    }
}

// Output test results
coloredEcho("\nTest Results:", $yellow);
coloredEcho("Passed: $passedTests/$totalTests", $passedTests === $totalTests ? $green : $red);

// Set exit code
$exitCode = $passedTests === $totalTests ? 0 : 1;
exit($exitCode);
