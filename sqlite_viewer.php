<?php

// SQLite Viewer written in PHP and Tailwind CSS.
// Usage: ./sqlite_viewer.php ./database/database.sqlite
// Access in browser: http://localhost:8000

// Tip: Add an alias to your shell profile for quick access,
// e.g. alias db="php /usr/local/bin/sqlite_viewer.php"
// and then run `db ./database/database.sqlite`

// Todo: Remove in production
if (file_exists(__DIR__.'/dev-lib.php')) {
    require __DIR__.'/dev-lib.php';
}

// If running in console, start a web server
if (php_sapi_name() === 'cli') {
    if (empty($argv[1])) {
        echo "Error: Please provide the path to the SQLite database as an argument.\n";
        echo 'Usage: '.$argv[0]." ./database/database.sqlite\n";
        exit(1);
    }

    if (! file_exists($argv[1])) {
        echo 'Error: Database file not found: '.$argv[1]."\n";
        exit(1);
    }

    $descriptor_spec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],   // stderr
    ];

    putenv('SQLITE_VIEWER_DATABASE='.$argv[1]);

    // Check if a port is set in env
    $port = getenv('SQLITE_VIEWER_PORT') ?: null;

    if (! $port) {
        // Check if --port option is set else use a random port
        $portOption = array_search('--port', $argv);

        if ($portOption !== false && isset($argv[$portOption + 1])) {
            $port = $argv[$portOption + 1];
        } else {
            $port = rand(49152, 65535);
        }
    }

    $process = proc_open("php -S localhost:$port ".__FILE__, $descriptor_spec, $pipes);

    if (is_resource($process)) {
        // Close stdin as we don't need to send input
        fclose($pipes[0]);

        // Read output in real-time
        while ($line = fgets($pipes[2])) {
            // Filter out unwanted log lines
            if (! preg_match('/^\[.*\] \[::1\]:\d+ Accepted|Closing/', $line)) {
                echo $line; // Output the line if it doesn't match the pattern
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    return;
}

// Read database path from environment variable or command line argument
$dbPath = getenv('SQLITE_VIEWER_DATABASE') ?: ($argv[1] ?? null);

if (! $dbPath) {
    exit('Please provide a database path via SQLITE_VIEWER_DATABASE environment variable or as a command line argument.');
}

if (! file_exists($dbPath)) {
    exit("Database file not found: $dbPath");
}

$db = new SQLite3($dbPath);

function getTables($db)
{
    $tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }

    return $tables;
}

function getTableData($db, $table, $page = 1, $perPage = 20, $sortColumn = null, $sortOrder = null)
{
    $offset = ($page - 1) * $perPage;
    $query = "SELECT *, rowid FROM '$table'";
    if ($sortColumn && $sortOrder) {
        $query .= ' ORDER BY '.SQLite3::escapeString($sortColumn)." $sortOrder";
    }
    $query .= " LIMIT $perPage OFFSET $offset";
    $result = $db->query($query);
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}

function getEntireTableData($db, $table)
{
    $query = "SELECT * FROM '$table'";
    $result = $db->query($query);
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    return $data;
}

function getTableColumns($db, $table)
{
    $result = $db->query("PRAGMA table_info('$table')");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    return $columns;
}

function getRecordDetails($db, $table, $id)
{
    $result = $db->query("SELECT *, rowid FROM '$table' WHERE rowid = $id");

    return $result->fetchArray(SQLITE3_ASSOC);
}

function getPrimaryKeyColumn($db, $table)
{
    $result = $db->query("PRAGMA table_info('$table')");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['pk'] == 1) {
            return $row['name'];
        }
    }

    return null;
}

function format_database_value($value)
{
    if (is_null($value)) {
        return '<span class="text-gray-400">NULL</span>';
    } elseif ($value === '') {
        return '<span class="text-gray-400">Empty string</span>';
    } else {
        return htmlspecialchars($value);
    }
}

$tables = getTables($db);
$currentTable = $_GET['table'] ?? $tables[0] ?? null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 100;

$action = $_GET['action'] ?? 'list';
$recordId = $_GET['id'] ?? null;

$sortColumn = $_GET['sort'] ?? null;
$sortOrder = $_GET['order'] ?? null;

// Handle JSON download
if ($action === 'download_json' && $currentTable) {
    $tableData = getEntireTableData($db, $currentTable);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $currentTable . '.json"');
    echo json_encode($tableData, JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLite Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="flex h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white p-4">
        <h1 class="text-2xl font-bold mb-4">SQLite Viewer</h1>
        <ul>
            <?php foreach ($tables as $table): ?>
                <li class="mb-2">
                    <a href="?table=<?= urlencode($table) ?>" class="hover:text-gray-300">
                        <?= htmlspecialchars($table) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Main content -->
    <div class="flex-1 p-8 overflow-auto">
        <?php if ($currentTable): ?>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-3xl font-bold"><?= htmlspecialchars($currentTable) ?></h2>
                <a href="?table=<?= urlencode($currentTable) ?>&action=download_json" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Download JSON
                </a>
            </div>

            <?php if ($action === 'list'): ?>
                <?php
                $data = getTableData($db, $currentTable, $page, $perPage, $sortColumn, $sortOrder);
                $columns = getTableColumns($db, $currentTable);
                $primaryKey = getPrimaryKeyColumn($db, $currentTable);
                ?>
                <div class="w-full overflow-x-auto">
                    <div class="inline-block min-w-full shadow-md rounded-lg overflow-hidden">
                        <table class="min-w-full">
                            <thead>
                            <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <?php foreach ($columns as $column): ?>
                                    <?php
                                    $newSortOrder = 'ASC';
                                    $sortIndicator = '';
                                    if ($sortColumn === $column) {
                                        if ($sortOrder === 'ASC') {
                                            $newSortOrder = 'DESC';
                                            $sortIndicator = '▲';
                                        } elseif ($sortOrder === 'DESC') {
                                            $newSortOrder = '';
                                            $sortIndicator = '▼';
                                        }
                                    }
                                    $sortParams = $newSortOrder ? "&sort=$column&order=$newSortOrder" : '';
                                    ?>
                                    <th class="py-3 px-6 text-left whitespace-nowrap">
                                        <a href="?table=<?= urlencode($currentTable) ?><?= $sortParams ?>&page=<?= $page ?>" class="hover:text-gray-900">
                                            <?= htmlspecialchars($column) ?> <?= $sortIndicator ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                                <th class="py-3 px-6 text-left">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($data as $row): ?>
                                <tr class="border-b border-gray-200 hover:bg-[#eaecef] transition-colors duration-50">
                                    <?php foreach ($columns as $column): ?>
                                        <td class="py-3 px-6 text-left">
                                            <div class="whitespace-nowrap overflow-hidden overflow-ellipsis max-w-xs" style="max-width: 255px;">
                                                <?= htmlspecialchars(substr($row[$column] ?? '', 0, 255)) ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-3 px-6 text-left whitespace-nowrap">
                                        <?php
                                        $idForView = $row['rowid'] ?? $row[$primaryKey] ?? null;
                                        if ($idForView !== null):
                                            ?>
                                            <a href="?table=<?= urlencode($currentTable) ?>&action=view&id=<?= $idForView ?><?= $sortColumn ? "&sort=$sortColumn&order=$sortOrder" : '' ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                        <?php else: ?>
                                            <span class="text-gray-400">No ID</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex justify-between items-center">
                    <?php if ($page > 1): ?>
                        <a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page - 1 ?><?= $sortColumn ? "&sort=$sortColumn&order=$sortOrder" : '' ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Previous</a>
                    <?php endif; ?>
                    <?php if (count($data) == $perPage): ?>
                        <a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page + 1 ?><?= $sortColumn ? "&sort=$sortColumn&order=$sortOrder" : '' ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Next</a>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'view' && $recordId): ?>
                <?php
                $record = getRecordDetails($db, $currentTable, $recordId);
                ?>
                <h3 class="text-xl font-bold mb-4">Record Details</h3>
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <?php foreach ($record as $column => $value): ?>
                        <div class="py-3 px-6 border-b border-gray-200">
                            <strong><?= htmlspecialchars($column) ?>:</strong>
                            <?= format_database_value($value) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="?table=<?= urlencode($currentTable) ?><?= $sortColumn ? "&sort=$sortColumn&order=$sortOrder" : '' ?>" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back to Table</a>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-xl">Select a table from the sidebar to view its contents.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
