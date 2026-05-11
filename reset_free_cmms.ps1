# Reset Free CMMS full schema and PM workflow
param()

$DBUSER = 'root'
$DBPASS = 'Kalemaf123@@'
$DBNAME = 'maintenix'
$BasePath = Convert-Path .

Write-Output "[1] Build clean SQL from database.sql"
$all = Get-Content "$BasePath\database.sql" -ErrorAction Stop
$start = -1
for ($i = 0; $i -lt $all.Count; $i++) {
    if ($all[$i].TrimStart().ToUpper().StartsWith('CREATE TABLE')) {
        $start = $i
        break
    }
}
if ($start -lt 0) { throw 'Could not find CREATE TABLE in database.sql' }
$all[$start..($all.Count-1)] | Set-Content "$BasePath\database_clean.sql"

Write-Output "[2] Import schema into $DBNAME"
cmd /c "mysql -u $DBUSER -p$DBPASS $DBNAME < `"$BasePath\database_clean.sql`""

Write-Output "[3] Run PM migration"
& php "$BasePath\migrations\add_pm_professional_structure.php"

Write-Output "[4] Seed users"
& php "$BasePath\add_users.php"

Write-Output "[5] Verify key tables"
cmd /c "mysql -u $DBUSER -p$DBPASS -e `"USE $DBNAME; SHOW TABLES; SHOW TABLES LIKE 'pm_%'; SHOW TABLES LIKE 'inventory_%'; SHOW TABLES LIKE 'work_orders'; SHOW TABLES LIKE 'equipment';`""

Write-Output "[6] Run PM generator"
& php "$BasePath\generate_pm.php"
& php "$BasePath\generate_pm.php"

Write-Output "Reset complete."
