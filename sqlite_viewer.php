<?php
// SQLite Viewer written in PHP and Tailwind CSS.
// Usage: php -S localhost:8000 sqlite_viewer.php
// Access in browser: http://localhost:8000?db=/path/to/your/database.sqlite

// Read database path from environment variable or command line argument
$dbPath = getenv('SQLITE_DB_PATH') ?: ($argv[1] ?? null);

if (!$dbPath) {
    die("Please provide a database path via SQLITE_DB_PATH environment variable or as a command line argument.");
}

if (!file_exists($dbPath)) {
    die("Database file not found: $dbPath");
}

$db = new SQLite3($dbPath);

function getTables($db) {
    $tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    return $tables;
}

function getTableData($db, $table, $page = 1, $perPage = 20) {
    $offset = ($page - 1) * $perPage;
    $result = $db->query("SELECT *, rowid FROM '$table' LIMIT $perPage OFFSET $offset");
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    return $data;
}

function getTableColumns($db, $table) {
    $result = $db->query("PRAGMA table_info('$table')");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }
    return $columns;
}

function getRecordDetails($db, $table, $id) {
    $result = $db->query("SELECT *, rowid FROM '$table' WHERE rowid = $id");
    return $result->fetchArray(SQLITE3_ASSOC);
}

function getPrimaryKeyColumn($db, $table) {
    $result = $db->query("PRAGMA table_info('$table')");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['pk'] == 1) {
            return $row['name'];
        }
    }
    return null;
}

function format_database_value($value) {
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
                <h2 class="text-3xl font-bold mb-4"><?= htmlspecialchars($currentTable) ?></h2>
                
                <?php if ($action === 'list'): ?>
                    <?php
                    $data = getTableData($db, $currentTable, $page, $perPage);
                    $columns = getTableColumns($db, $currentTable);
                    $primaryKey = getPrimaryKeyColumn($db, $currentTable);
                    ?>
                    <div class="w-full overflow-x-auto">
                        <div class="inline-block min-w-full shadow-md rounded-lg overflow-hidden">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                        <?php foreach ($columns as $column): ?>
                                            <th class="py-3 px-6 text-left whitespace-nowrap"><?= htmlspecialchars($column) ?></th>
                                        <?php endforeach; ?>
                                        <th class="py-3 px-6 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 text-sm font-light">
                                    <?php foreach ($data as $row): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-100">
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
                                                    <a href="?table=<?= urlencode($currentTable) ?>&action=view&id=<?= $idForView ?>" class="text-blue-600 hover:text-blue-900">View</a>
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
                            <a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page - 1 ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Previous</a>
                        <?php endif; ?>
                        <?php if (count($data) == $perPage): ?>
                            <a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page + 1 ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Next</a>
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
                    <a href="?table=<?= urlencode($currentTable) ?>" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back to Table</a>
                <?php endif; ?>
            
            <?php else: ?>
                <p class="text-xl">Select a table from the sidebar to view its contents.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
