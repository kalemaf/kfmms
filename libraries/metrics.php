<?php
/**
 * Utility functions for KPI metrics (MTTR/MTBF etc).
 * Can be included by pages that need to compute these values.
 */

function calculate_mttr($start=null, $end=null, $equipment_id = null)
{
    global $connection, $db_type;

    $clauses = [];
    if ($start) {
        $clauses[] = "r.start_datetime >= '" . $connection->real_escape_string($start) . "'";
    }
    if ($end) {
        $clauses[] = "r.start_datetime <= '" . $connection->real_escape_string($end) . "'";
    }
    if ($equipment_id) {
        $clauses[] = "f.equipment_id = " . (int)$equipment_id;
    }
    
    // Get tenant filter if available
    $tenantClause = '';
    if (function_exists('apply_tenant_filter')) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenant_id > 0) {
            $tenantClause = " AND r.tenant_id = " . $tenant_id . " AND f.tenant_id = " . $tenant_id;
        }
    }

    $where = $clauses ? 'AND ' . implode(' AND ', $clauses) : '';

    if ($db_type === 'sqlite') {
        $sql = "SELECT AVG((julianday(r.end_datetime) - julianday(r.start_datetime)) * 86400) AS mttr_seconds
                FROM repairs r
                JOIN failures f ON r.failure_id = f.failure_id
                WHERE r.end_datetime IS NOT NULL $where $tenantClause";
    } else {
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND,r.start_datetime,r.end_datetime)) AS mttr_seconds
                FROM repairs r
                JOIN failures f ON r.failure_id = f.failure_id
                WHERE r.end_datetime IS NOT NULL $where $tenantClause";
    }

    $res = $connection->query($sql);
    if (!$res) {
        return null;
    }
    if ($db_type === 'sqlite') {
        $row = $res->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $res->fetch_assoc();
    }
    return $row['mttr_seconds'];
}

function metric_table_exists($table)
{
    global $connection, $db_type;
    $table_name = $connection->real_escape_string($table);
    if ($db_type === 'sqlite') {
        $result = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table_name'");
        return $result && $result->fetch(PDO::FETCH_ASSOC);
    } else {
        $result = $connection->query("SHOW TABLES LIKE '$table_name'");
        return $result && $result->num_rows > 0;
    }
}

function calculate_mtbf($equipment_id = null)
{
    global $connection, $db_type;

    if (!metric_table_exists('failures')) {
        return null;
    }

    $eqClause = $equipment_id ? "AND f.equipment_id = " . (int)$equipment_id : '';
    
    // Get tenant filter if available
    $tenantClause = '';
    if (function_exists('apply_tenant_filter')) {
        // For subqueries, we need to add tenant filter directly
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenant_id > 0) {
            $tenantClause = " AND f.tenant_id = " . $tenant_id;
        }
    }

    if ($db_type === 'sqlite') {
        // SQLite version using self-join instead of window functions
        $sql = "SELECT AVG(diff) AS mtbf_seconds FROM (
                  SELECT (julianday(f2.failure_datetime) - julianday(f1.failure_datetime)) * 86400 AS diff
                  FROM failures f1
                  JOIN failures f2 ON f1.equipment_id = f2.equipment_id
                    AND f2.failure_datetime = (
                      SELECT MIN(failure_datetime) FROM failures
                      WHERE equipment_id = f1.equipment_id AND failure_datetime > f1.failure_datetime
                    )
                  WHERE 1=1 $eqClause $tenantClause
                ) x
                WHERE diff IS NOT NULL";
    } else {
        $sql = "SELECT AVG(diff) AS mtbf_seconds FROM (
                  SELECT TIMESTAMPDIFF(SECOND, failure_datetime,
                           LEAD(failure_datetime) OVER (PARTITION BY equipment_id ORDER BY failure_datetime)) AS diff
                  FROM failures f
                  WHERE 1=1 $eqClause $tenantClause
                ) x
                WHERE diff IS NOT NULL";
    }

    $res = $connection->query($sql);
    if (!$res) {
        return null;
    }
    if ($db_type === 'sqlite') {
        $row = $res->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $res->fetch_assoc();
    }
    return $row['mtbf_seconds'];
}

/**
 * Total uptime seconds between optional start/end dates.
 */
function total_uptime($start=null, $end=null, $equipment_id = null)
{
    global $connection, $db_type;
    $clauses = [];
    if ($start) {
        $clauses[] = "start_time >= '" . $connection->real_escape_string($start) . "'";
    }
    if ($end) {
        $clauses[] = "end_time <= '" . $connection->real_escape_string($end) . "'";
    }
    if ($equipment_id) {
        $clauses[] = "equipment_id = " . (int)$equipment_id;
    }
    $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
    $sql = "SELECT SUM(uptime) AS secs FROM Uptime_Record $where";
    $res = $connection->query($sql);
    if (!$res) {
        return 0;
    }
    if ($db_type === 'sqlite') {
        $row = $res->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $res->fetch_assoc();
    }
    return (int)($row['secs'] ?? 0);
}

/**
 * Availability percentage = uptime / (uptime + downtime) * 100
 */
function availability($start=null, $end=null, $equipment_id = null)
{
    global $connection, $db_type;
    $upt = total_uptime($start,$end, $equipment_id);
    // downtime derived from repairs durations
    $clauses = [];
    if ($start) {
        $clauses[] = "r.start_datetime >= '" . $connection->real_escape_string($start) . "'";
    }
    if ($end) {
        $clauses[] = "r.end_datetime <= '" . $connection->real_escape_string($end) . "'";
    }
    if ($equipment_id) {
        $clauses[] = "f.equipment_id = " . (int)$equipment_id;
    }
    
    // Get tenant filter if available
    $tenantClause = '';
    if (function_exists('apply_tenant_filter')) {
        $tenant_id = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenant_id > 0) {
            $tenantClause = " AND r.tenant_id = " . $tenant_id . " AND f.tenant_id = " . $tenant_id;
        }
    }
    
    $where = $clauses ? 'AND ' . implode(' AND ', $clauses) : '';

    if ($db_type === 'sqlite') {
        $sql = "SELECT SUM((julianday(r.end_datetime) - julianday(r.start_datetime)) * 86400) AS secs
                FROM repairs r
                JOIN failures f ON r.failure_id = f.failure_id
                WHERE r.end_datetime IS NOT NULL $where $tenantClause";
    } else {
        $sql = "SELECT SUM(TIMESTAMPDIFF(SECOND,r.start_datetime,r.end_datetime)) AS secs
                FROM repairs r
                JOIN failures f ON r.failure_id = f.failure_id
                WHERE r.end_datetime IS NOT NULL $where $tenantClause";
    }

    $res = $connection->query($sql);
    if (!$res) {
        return null;
    }
    if ($db_type === 'sqlite') {
        $row = $res->fetch(PDO::FETCH_ASSOC);
    } else {
        $row = $res->fetch_assoc();
    }
    $dow = (int)($row['secs'] ?? 0);
    if ($upt + $dow == 0) return null;
    return ($upt / ($upt + $dow)) * 100;
}

?>