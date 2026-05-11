<?php
// Migration script from MySQL to SQLite

$mysql_host = getenv('DB_HOST') ?: '127.0.0.1';
$mysql_user = getenv('DB_USER') ?: 'root';
$mysql_pass = getenv('DB_PASS') ?: '';
$mysql_db = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: 'maintenix';

$sqlite_file = getenv('DB_FILE') ?: __DIR__ . '/database/maintenix.db';

try {
    // Connect to MySQL
    $mysql = new PDO("mysql:host=$mysql_host;dbname=$mysql_db", $mysql_user, $mysql_pass);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Connect to SQLite
    $sqlite = new PDO("sqlite:$sqlite_file");
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables
    $tables = $mysql->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "Migrating table: $table\n";

        // Get CREATE TABLE statement
        $create = $mysql->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC)['Create Table'];

        // Convert MySQL to SQLite
        $sqlite_create = mysql_to_sqlite_create($create);

        // Drop table if exists
        $sqlite->exec("DROP TABLE IF EXISTS `$table`");

        // Create table in SQLite
        try {
            $sqlite->exec($sqlite_create);
        } catch (Exception $e) {
            echo "Error creating table: " . $e->getMessage() . "\n";
            echo "Full SQL:\n$sqlite_create\n";
            continue;
        }

        // Get data
        $data = $mysql->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($data)) {
            // Insert data
            $columns = array_keys($data[0]);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $stmt = $sqlite->prepare("INSERT INTO `$table` (" . implode(',', array_map(function($c){return "`$c`";}, $columns)) . ") VALUES ($placeholders)");

            foreach ($data as $row) {
                try {
                    $stmt->execute(array_values($row));
                } catch (Exception $e) {
                    echo "Error inserting row: " . $e->getMessage() . "\n";
                    print_r($row);
                    break;
                }
            }
        }
    }

    // Link equipment spares to parts_master in the migrated SQLite database
    if (in_array('equipment_spares', $tables, true) && in_array('parts_master', $tables, true)) {
        echo "Linking equipment_spares to parts_master...\n";
        $columnCheck = $sqlite->query("PRAGMA table_info('equipment_spares')")->fetchAll(PDO::FETCH_ASSOC);
        $hasPartId = false;
        foreach ($columnCheck as $colDef) {
            if ($colDef['name'] === 'part_id') {
                $hasPartId = true;
                break;
            }
        }

        if ($hasPartId) {
            $sqlite->exec("UPDATE equipment_spares SET part_id = (
                SELECT id FROM parts_master pm
                WHERE pm.part_number = equipment_spares.part_number
                LIMIT 1
            ) WHERE part_id IS NULL OR part_id = ''");

            $sqlite->exec("UPDATE equipment_spares SET part_id = (
                SELECT id FROM parts_master pm
                WHERE pm.part_name = equipment_spares.part_name
                LIMIT 1
            ) WHERE part_id IS NULL OR part_id = ''");
        } else {
            echo "Skipping equipment_spares part_id linkage because part_id column is missing in SQLite schema.\n";
        }
    }

    echo "Migration completed!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function mysql_to_sqlite_create($mysql_create) {
    // Remove MySQL specific parts
    $sqlite_create = preg_replace('/ENGINE=\w+/', '', $mysql_create);
    $sqlite_create = preg_replace('/DEFAULT CHARSET=\w+/', '', $sqlite_create);
    // Fix DEFAULT values for INTEGER
    $sqlite_create = preg_replace('/DEFAULT \'(\d+)\'/', 'DEFAULT $1', $sqlite_create);
    $sqlite_create = preg_replace('/COLLATE[ =]\w+/', '', $sqlite_create);
    $sqlite_create = preg_replace('/COMMENT \'[^\']*\'/', '', $sqlite_create);
    $sqlite_create = preg_replace('/AUTO_INCREMENT=\d+/', '', $sqlite_create);

    // Convert AUTO_INCREMENT to AUTOINCREMENT
    $sqlite_create = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $sqlite_create);

    // Convert data types
    $sqlite_create = preg_replace('/\bint(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\btinyint(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\bsmallint(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\bmediumint(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\bbigint(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\bvarchar(\(\d+\))?\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\bchar(\(\d+\))?\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\btext\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\bmediumtext\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\blongtext\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\bdate\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\bdatetime\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\btimestamp\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\btime\b/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/\byear(\(\d+\))?\b/', 'INTEGER', $sqlite_create);
    $sqlite_create = preg_replace('/\bdecimal(\(\d+,\d+\))?\b/', 'REAL', $sqlite_create);
    $sqlite_create = preg_replace('/\bfloat\b/', 'REAL', $sqlite_create);
    $sqlite_create = preg_replace('/\bdouble\b/', 'REAL', $sqlite_create);
    $sqlite_create = preg_replace('/\benum\([^)]+\)/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/TEXT\(\d+\)/', 'TEXT', $sqlite_create);
    $sqlite_create = preg_replace('/INTEGER\(\d+\)/', 'INTEGER', $sqlite_create);

    // Remove MySQL specific defaults
    $sqlite_create = preg_replace('/DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/', 'DEFAULT CURRENT_TIMESTAMP', $sqlite_create);

    // Handle PRIMARY KEY and AUTOINCREMENT
    $sqlite_create = preg_replace('/(INTEGER NOT NULL AUTOINCREMENT)/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sqlite_create);

    // Remove index definitions (UNIQUE KEY, KEY)
    $sqlite_create = preg_replace('/,\s*UNIQUE KEY\s+`[^`]+`\s*\([^)]+\)/', '', $sqlite_create);
    $sqlite_create = preg_replace('/,\s*KEY\s+`[^`]+`\s*\([^)]+\)/', '', $sqlite_create);
    $sqlite_create = preg_replace('/,\s*PRIMARY KEY\s*\([^)]+\)/', '', $sqlite_create);

    // Clean up trailing commas
    $sqlite_create = preg_replace('/,(\s*\))/', '$1', $sqlite_create);

    return $sqlite_create;
}
?>