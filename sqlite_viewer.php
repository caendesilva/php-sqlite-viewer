<?php

// SQLite Viewer written in PHP and Tailwind CSS.
// Usage: ./sqlite_viewer.php ./database/database.sqlite
// Access in browser: http://localhost:9000

// Tip: Add an alias to your shell profile for quick access,
// e.g. alias db="php /usr/local/bin/sqlite_viewer.php"
// and then run `db ./database/database.sqlite`

// Todo: Remove in production
if (file_exists(__DIR__.'/dev-lib.php')) {
    require __DIR__.'/dev-lib.php';
}

/**
 * Default port for the SQLite Viewer web server.
 *
 * Customize using one of these:
 *
 * @environmentVariable SQLITE_VIEWER_PORT
 *
 * @commandLineOption --port <int>
 */
const SQLITE_VIEWER_DEFAULT_PORT = 9000;

/**
 * Default setting for if the page should be opened automatically in the browser.
 *
 * Customize using one of these:
 *
 * @environmentVariable SQLITE_VIEWER_DEFAULT_OPEN_PAGE
 *
 * @commandLineOption --open <bool>
 */
const SQLITE_VIEWER_DEFAULT_OPEN = true;

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

    // Check if --port option is set else use a random port
    $portOption = array_search('--port', $argv);

    if ($portOption !== false && isset($argv[$portOption + 1])) {
        // Custom port is set
        $portSelection = $argv[$portOption + 1];

        if ($portSelection === 'random') {
            $port = rand(49152, 65535);
        } else {
            $port = $portSelection;
        }
    } else {
        // Use env variable or default port
        $port = getenv('SQLITE_VIEWER_PORT') ?: SQLITE_VIEWER_DEFAULT_PORT;
    }

    // Check if --open option is set
    $openOption = array_search('--open', $argv);

    if ($openOption !== false && isset($argv[$openOption + 1])) {
        $openSelection = $argv[$openOption + 1];
        $open = filter_var($openSelection, FILTER_VALIDATE_BOOLEAN);
    } else {
        $open = getenv('SQLITE_VIEWER_DEFAULT_OPEN') ?: SQLITE_VIEWER_DEFAULT_OPEN;
    }

    $process = proc_open("php -S localhost:$port ".__FILE__, $descriptor_spec, $pipes);

    if (is_resource($process)) {
        // Close stdin as we don't need to send input
        fclose($pipes[0]);

        // Open the page in the browser
        if ($open) {
            $url = "http://localhost:$port";

            if (! defined('PHP_OS_FAMILY')) {
                // Symfony polyfill pre PHP 7.2
                define('PHP_OS_FAMILY', (function () {
                    if ('\\' === DIRECTORY_SEPARATOR) {
                        return 'Windows';
                    }

                    if (strpos(PHP_OS, 'Darwin') !== false) {
                        return 'Darwin';
                    }

                    return 'Linux'; // Probably Linux
                })());
            }

            if (PHP_OS_FAMILY === 'Darwin') {
                exec("open $url");
            } elseif (PHP_OS_FAMILY === 'Windows') {
                exec("start $url");
            } elseif (PHP_OS_FAMILY === 'Linux') {
                exec("xdg-open $url");
            }
        }

        // Read output in real-time
        while ($line = fgets($pipes[2])) {
            // Filter out unwanted log lines
            if (! preg_match('/^\[.*] \[::1]:\d+ Accepted|Closing/', $line)) {
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

function getTables(SQLite3 $db): array
{
    $tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }

    return $tables;
}

function getTableData(SQLite3 $db, string $table, int $page = 1, int $perPage = 20, string $sortColumn = null, string $sortOrder = null): array
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

function getEntireTableData(SQLite3 $db, string $table): array
{
    $query = "SELECT * FROM '$table'";
    $result = $db->query($query);
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}

function getTableColumns(SQLite3 $db, string $table): array
{
    $result = $db->query("PRAGMA table_info('$table')");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    return $columns;
}

function getTableStructure(SQLite3 $db, string $table): array
{
    $result = $db->query("PRAGMA table_info('$table')");
    $structure = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Parse the type to extract additional information
        preg_match('/(\w+)(?:\((\d+)(?:,(\d+))?\))?/', $row['type'], $matches);
        $baseType = $matches[1] ?? $row['type'];
        $size = $matches[2] ?? null;
        $scale = $matches[3] ?? null;

        $detailedType = $baseType;
        if ($size !== null) {
            $detailedType .= "($size";
            if ($scale !== null) {
                $detailedType .= ",$scale";
            }
            $detailedType .= ')';
        }

        $row['detailed_type'] = $detailedType;
        $structure[] = $row;
    }

    return $structure;
}

function getRecordDetails(SQLite3 $db, string $table, int $id): array
{
    $result = $db->query("SELECT *, rowid FROM '$table' WHERE rowid = $id");

    return $result->fetchArray(SQLITE3_ASSOC);
}

/**
 * @return string|null
 */
function getPrimaryKeyColumn(SQLite3 $db, string $table)
{
    $result = $db->query("PRAGMA table_info('$table')");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['pk'] == 1) {
            return $row['name'];
        }
    }

    return null;
}

/**
 * @param mixed $value
 */
function format_database_value($value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_numeric($value)) {
        return $value;
    } elseif (is_null($value)) {
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
    header('Content-Disposition: attachment; filename="'.$currentTable.'.json"');
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
    <style>[x-cloak] { display: none !important; }</style>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="flex h-screen" x-data="{ showStructure: false }" @keydown.escape="showStructure = false">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white p-4">
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
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-8 overflow-auto">
        <?php
        function findColumnWidth(string $column): int
        {
            if ($column === 'id') {
                return 3;
            }

            if ($column === 'password') {
                return 16;
            }

            return 32;
        }
        ?>

        <?php if ($currentTable): ?>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-3xl font-bold"><?= htmlspecialchars($currentTable) ?></h2>
                <div class="flex space-x-2">
                    <button @click="showStructure = true" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 text-sm rounded leading-1">
                        View Structure
                    </button>
                    <a href="?table=<?= urlencode($currentTable) ?>&action=download_json" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-1 px-2 text-sm rounded leading-1">
                        Download JSON
                    </a>
                </div>
            </div>

            <!-- Structure Modal -->
            <div x-show="showStructure" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" x-cloak @click="showStructure = false">
                <div class="relative top-20 mx-auto py-4 px-3 border min-w-96 w-fit shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Table Structure: <?= htmlspecialchars($currentTable) ?></h3>
                        <div class="mt-2 px-7 py-3">
                            <table class="min-w-full">
                                <thead>
                                <tr class="bg-gray-200 text-gray-700 uppercase text-xs leading-normal">
                                    <th class="py-3 px-6 text-left">Column</th>
                                    <th class="py-3 px-6 text-left">Type</th>
                                    <th class="py-3 px-6 text-center">Nullable</th>
                                    <th class="py-3 px-6 text-center">Primary Key?</th>
                                    <th class="py-3 px-6 text-left">Default Value</th>
                                </tr>
                                </thead>
                                <tbody class="text-gray-700 text-sm font-light">
                                <?php
                                $structure = getTableStructure($db, $currentTable);
                                foreach ($structure as $column):
                                    ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="py-3 px-6 text-left whitespace-nowrap">
                                            <?= htmlspecialchars($column['name']) ?>
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            <?= htmlspecialchars($column['detailed_type']) ?>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <?= $column['notnull'] ? 'No' : 'Yes' ?>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <?= $column['pk'] ? 'Yes' : 'No' ?>
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            <?= $column['dflt_value'] !== null ? htmlspecialchars($column['dflt_value']) : '<span class="text-gray-400">NULL</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="items-center px-4 py-3">
                            <button @click="showStructure = false" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
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
                            <tr class="bg-gray-200 text-gray-600 uppercase text-xs leading-normal">
                                <?php foreach ($columns as $key => $column): ?>
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
                                    <th class="py-3 px-3 <?= $key === 0 ? 'pl-4' : '' ?> text-left whitespace-nowrap">
                                        <a href="?table=<?= urlencode($currentTable) ?><?= $sortParams ?>&page=<?= $page ?>" class="hover:text-gray-900">
                                            <?= htmlspecialchars($column) ?> <?= $sortIndicator ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                                <th class="py-3 px-3 pr-4 text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($data as $row): ?>
                                <tr class="border-b border-gray-200 hover:bg-[#eaecef] transition-colors duration-50">
                                    <?php foreach ($columns as $key => $column): ?>
                                        <td class="py-2 px-3 <?= $key === 0 ? 'pl-4' : '' ?> text-left">
                                            <div class="whitespace-nowrap overflow-hidden overflow-ellipsis max-w-xs" style="max-width: <?= findColumnWidth($column); ?>ch;">
                                                <?php
                                                $idForView = $row['rowid'] ?? $row[$primaryKey] ?? null;
                                                if ($idForView !== null && $column === $primaryKey): ?>
                                                    <a href="?table=<?= urlencode($currentTable) ?>&action=view&id=<?= $idForView ?><?= $sortColumn ? "&sort=$sortColumn&order=$sortOrder" : '' ?>" class="text-blue-600 hover:text-blue-900">
                                                        <?= htmlspecialchars(substr($row[$column] ?? '', 0, 255)) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(substr($row[$column] ?? '', 0, 255)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-2 px-3 pr-4 text-right whitespace-nowrap">
                                        <?php
                                        $idForView = $row['rowid'] ?? $row[$primaryKey] ?? null;
                                        if ($idForView !== null): ?>
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
    </main>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
