<?php
// Security: Disable directory listing for safety
if (!defined('ALLOWED_ACCESS')) {
    define('ALLOWED_ACCESS', true);
}

// Configuration
$config = [
    'app_name' => 'Laragon',
    'docs_url' => 'https://laragon.org/docs',
    'excluded_folders' => ['.', '..', '.git', '.svn', '.htaccess'],
    'db_config' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
    ]
];

// Get MySQL databases
function getDatabases($config) {
    $databases = [];
    try {
        $mysqli = new mysqli($config['host'], $config['user'], $config['password']);
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        // Get databases
        $result = $mysqli->query("SHOW DATABASES");
        while ($row = $result->fetch_array()) {
            if (!in_array($row[0], ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                $dbName = $row[0];
                // Get tables count for each database
                $mysqli->select_db($dbName);
                $tablesResult = $mysqli->query("SHOW TABLES");
                $databases[$dbName] = [
                    'name' => $dbName,
                    'tables' => $tablesResult->num_rows,
                    'size' => 0
                ];
                
                // Calculate database size
                $sizeResult = $mysqli->query("SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = '$dbName'");
                $sizeRow = $sizeResult->fetch_assoc();
                $databases[$dbName]['size'] = $sizeRow['size'] ?? 0;
            }
        }
        $mysqli->close();
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
    return $databases;
}

// Get system information
function getSystemInfo() {
    return [
        'PHP Version' => phpversion(),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Server OS' => PHP_OS,
        'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'Max Upload Size' => ini_get('upload_max_filesize'),
        'Max Post Size' => ini_get('post_max_size'),
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time') . 's',
        'PHP Modules' => count(get_loaded_extensions()) . ' loaded',
    ];
}

// Get directories with error handling
function getProjectFolders($excluded = []) {
    try {
        $folders = array_filter(glob('*'), 'is_dir');
        return array_values(array_diff($folders, $excluded));
    } catch (Exception $e) {
        error_log("Error reading directories: " . $e->getMessage());
        return [];
    }
}

$folders = getProjectFolders($config['excluded_folders']);
$databases = getDatabases($config['db_config']);
$systemInfo = getSystemInfo();

// Get PHP Extensions
$extensions = get_loaded_extensions();
sort($extensions);
// Get system resource usage
$systemResources = [
    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
    'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2),
    'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : ['N/A'],
    'disk_free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
    'disk_total' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2)
];

// Add to systemInfo array
$systemInfo['Memory Usage'] = $systemResources['memory_usage'] . ' MB';
$systemInfo['Peak Memory'] = $systemResources['peak_memory'] . ' MB';
$systemInfo['CPU Load'] = is_array($systemResources['cpu_load']) ? implode(', ', $systemResources['cpu_load']) : $systemResources['cpu_load'];
$systemInfo['Disk Free Space'] = $systemResources['disk_free'] . ' GB';
$systemInfo['Disk Total Space'] = $systemResources['disk_total'] . ' GB';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', sans-serif;
    }
    </style>
</head>

<body class="bg-gray-50">

    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Laragon Dashboard</h1>
                    <a href="<?php echo htmlspecialchars($config['docs_url']); ?>"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500" target="_blank" rel="noopener">
                        Documentation →
                    </a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="/phpmyadmin"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200"
                        target="_blank">
                        <span class="font-medium text-gray-700">PHP MyAdmin</span>
                    </a>
                    <a href="?q=info"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                        <span class="font-medium text-gray-700">PHP Info</span>
                    </a>
                    <a href="/"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                        <span class="font-medium text-gray-700">Home Directory</span>
                    </a>
                </div>
            </div>

            <!-- System Information -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">System Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($systemInfo as $key => $value): ?>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                        <div class="text-sm font-medium text-gray-500"><?php echo htmlspecialchars($key); ?></div>
                        <div class="mt-1 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($value); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Projects -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Projects</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php if (empty($folders)): ?>
                    <div class="col-span-full">
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                            <div class="text-yellow-700">No projects found</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach($folders as $folder): ?>
                    <a href="/<?php echo htmlspecialchars($folder); ?>"
                        class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow"
                        target="_blank">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                <?php echo htmlspecialchars($folder); ?>
                            </span>
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Databases -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Databases</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (empty($databases)): ?>
                    <div class="col-span-full">
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                            <div class="text-yellow-700">No databases found</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach($databases as $db): ?>
                    <a href="/phpmyadmin/index.php?route=/database/structure&db=<?php echo urlencode($db['name']); ?>"
                        class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow"
                        target="_blank">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 3h16M4 14h16" />
                                </svg>
                                <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                    <?php echo htmlspecialchars($db['name']); ?>
                                </span>
                            </div>
                            <span class="text-sm text-gray-500"><?php echo $db['tables']; ?> tables</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            Size: <?php echo number_format($db['size'], 2); ?> MB
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">PHP Extensions</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
                        <?php foreach($extensions as $ext): ?>
                        <div class="text-sm text-gray-600 bg-gray-50 rounded px-3 py-1">
                            <?php echo htmlspecialchars($ext); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-8">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    Powered by Laragon - <?php echo date('Y'); ?>
                </p>
            </div>
        </footer>
    </div>
</body>

</html>