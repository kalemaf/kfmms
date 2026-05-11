<?php
/**
 * Performance Service - Calculates and aggregates technician performance metrics
 * 
 * Handles:
 * - Performance metric calculations
 * - Overall score computation
 * - Performance caching
 * - Trend analysis
 */

/**
 * Calculate performance metrics for an artisan for a given period
 * This is the core performance calculation engine for artisans
 * 
 * @param int $artisan_id Artisan ID from artisans table
 * @param string $period_start Start date (YYYY-MM-DD)
 * @param string $period_end End date (YYYY-MM-DD)
 * @param string $period_type 'daily', 'weekly', 'monthly', 'yearly'
 * @return array Performance metrics with overall score
 */
function calculate_artisan_performance($artisan_id, $period_start, $period_end, $period_type = 'monthly') {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        // Get all work orders assigned to artisan in period via artisan_work_order_assignments
        $stmt = $connection->prepare("
            WITH assignment_candidates AS (
                SELECT
                    awa.artisan_id,
                    awa.work_order_id,
                    awa.assignment_date,
                    awa.tenant_id
                FROM artisan_work_order_assignments awa
                WHERE awa.tenant_id = ?
                UNION
                SELECT
                    a.artisan_id,
                    wo.wo_id AS work_order_id,
                    COALESCE(wo.submit_date, wo.created_at) AS assignment_date,
                    wo.tenant_id
                FROM work_orders wo
                JOIN artisans a ON a.tenant_id = wo.tenant_id
                    AND (wo.mechanic_id = a.artisan_id OR wo.mechanic_id = a.user_id)
                WHERE wo.tenant_id = ?
            )
            SELECT
                ac.*,
                wo.wo_id,
                wo.equipment,
                wo.failure_mode,
                wo.wo_status,
                wo.completed_at,
                wo.sla_due_date AS due_date,
                wo.submit_date AS assigned_at,
                wos.response_sla_met,
                wos.completion_sla_met,
                wos.response_time_minutes,
                wos.completion_time_minutes,
                wos.is_overdue,
                a.first_name,
                a.last_name,
                a.employee_id
            FROM assignment_candidates ac
            JOIN work_orders wo ON ac.work_order_id = wo.wo_id
            LEFT JOIN work_order_sla wos ON wo.wo_id = wos.work_order_id AND wos.tenant_id = ac.tenant_id
            JOIN artisans a ON ac.artisan_id = a.artisan_id
            WHERE ac.tenant_id = ?
            AND ac.artisan_id = ?
            AND DATE(ac.assignment_date) >= ?
            AND DATE(ac.assignment_date) <= ?
            ORDER BY ac.assignment_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $artisan_id, $period_start, $period_end]);
        $work_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize metrics
        $metrics = [
            'artisan_id' => $artisan_id,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'period_type' => $period_type,
            'total_assigned' => 0,
            'total_completed' => 0,
            'total_overdue' => 0,
            'response_sla_met' => 0,
            'completion_sla_met' => 0,
            'repeat_failure_count' => 0,
            'response_sla_percentage' => 0,
            'completion_sla_percentage' => 0,
            'first_time_fix_percentage' => 0,
            'completion_rate_percentage' => 0,
            'mttr_hours' => 0,
            'average_response_time_minutes' => 0,
            'overall_score' => 0,
            'rating' => 'Pending'
        ];
        
        if (empty($work_orders)) {
            return $metrics;
        }
        
        $metrics['total_assigned'] = count($work_orders);
        
        // Calculate metrics from work orders
        $total_completion_time = 0;
        $total_response_time = 0;
        $response_count = 0;
        
        foreach ($work_orders as $wo) {
            // Count completions
            if ($wo['completed_at'] || $wo['wo_status'] === 'Completed') {
                $metrics['total_completed']++;
            }
            
            // Count overdue
            if ($wo['is_overdue']) {
                $metrics['total_overdue']++;
            }
            
            // Count SLA met
            if ($wo['response_sla_met']) {
                $metrics['response_sla_met']++;
            }
            
            if ($wo['completion_sla_met']) {
                $metrics['completion_sla_met']++;
            }
            
            // Accumulate times for averaging
            if ($wo['completion_time_minutes']) {
                $total_completion_time += $wo['completion_time_minutes'];
            }
            
            if ($wo['response_time_minutes']) {
                $total_response_time += $wo['response_time_minutes'];
                $response_count++;
            }
        }
        
        // Calculate percentages
        $metrics['response_sla_percentage'] = $metrics['total_assigned'] > 0 
            ? round(($metrics['response_sla_met'] / $metrics['total_assigned']) * 100, 2)
            : 0;
            
        $metrics['completion_sla_percentage'] = $metrics['total_completed'] > 0
            ? round(($metrics['completion_sla_met'] / $metrics['total_completed']) * 100, 2)
            : 0;
            
        $metrics['completion_rate_percentage'] = $metrics['total_assigned'] > 0
            ? round(($metrics['total_completed'] / $metrics['total_assigned']) * 100, 2)
            : 0;
        
        // Calculate averages
        if ($metrics['total_completed'] > 0) {
            $metrics['mttr_hours'] = round($total_completion_time / ($metrics['total_completed'] * 60), 2);
        }
        
        if ($response_count > 0) {
            $metrics['average_response_time_minutes'] = round($total_response_time / $response_count, 2);
        }
        
        // Get repeat failure count for this artisan
        $stmt = $connection->prepare("
            SELECT COUNT(DISTINCT rf.original_work_order_id) as count
            FROM repeat_failures rf
            JOIN work_orders wo ON rf.original_work_order_id = wo.wo_id AND wo.tenant_id = ?
            JOIN artisans a ON a.tenant_id = wo.tenant_id
                AND (wo.mechanic_id = a.artisan_id OR wo.mechanic_id = a.user_id)
            WHERE a.artisan_id = ?
            AND rf.created_at >= ?
            AND rf.created_at <= ?
        ");
        $stmt->execute([$tenant_id, $artisan_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
        $repeat_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['repeat_failure_count'] = $repeat_result['count'] ?? 0;
        
        // Calculate first-time fix percentage
        $first_time_fixes = $metrics['total_completed'] - $metrics['repeat_failure_count'];
        $metrics['first_time_fix_percentage'] = $metrics['total_completed'] > 0
            ? round(($first_time_fixes / $metrics['total_completed']) * 100, 2)
            : 0;
        
        // Calculate overall score using weighted formula
        // Response SLA: 30%, Completion SLA: 40%, First-time fix: 20%, Completion rate: 10%
        $metrics['overall_score'] = round(
            ($metrics['response_sla_percentage'] * 0.30) +
            ($metrics['completion_sla_percentage'] * 0.40) +
            ($metrics['first_time_fix_percentage'] * 0.20) +
            ($metrics['completion_rate_percentage'] * 0.10),
            2
        );
        
        // Determine rating
        if ($metrics['overall_score'] >= 90) {
            $metrics['rating'] = 'Excellent';
        } elseif ($metrics['overall_score'] >= 80) {
            $metrics['rating'] = 'Good';
        } elseif ($metrics['overall_score'] >= 70) {
            $metrics['rating'] = 'Satisfactory';
        } elseif ($metrics['overall_score'] >= 60) {
            $metrics['rating'] = 'Needs Improvement';
        } else {
            $metrics['rating'] = 'Poor';
        }
        
        return $metrics;
    } catch (Exception $e) {
        error_log("Error calculating artisan performance metrics: " . $e->getMessage());
        return [];
    }
}

/**
 * Store calculated performance metrics in cache table
 * 
 * @param array $metrics Performance metrics from calculate_technician_performance
 * @return bool Success status
 */
function store_performance_metrics($metrics) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        // Check if record exists
        $stmt = $connection->prepare("
            SELECT id FROM technician_performance
            WHERE tenant_id = ?
            AND technician_id = ?
            AND period_start = ?
            AND period_end = ?
            AND period_type = ?
        ");
        $stmt->execute([
            $tenant_id,
            $metrics['technician_id'],
            $metrics['period_start'],
            $metrics['period_end'],
            $metrics['period_type']
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $stmt = $connection->prepare("
                UPDATE technician_performance
                SET total_assigned = ?,
                    total_completed = ?,
                    total_overdue = ?,
                    response_sla_met = ?,
                    completion_sla_met = ?,
                    repeat_failure_count = ?,
                    response_sla_percentage = ?,
                    completion_sla_percentage = ?,
                    first_time_fix_percentage = ?,
                    completion_rate_percentage = ?,
                    mttr_hours = ?,
                    average_response_time_minutes = ?,
                    overall_score = ?,
                    rating = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                AND tenant_id = ?
            ");
            $stmt->execute([
                $metrics['total_assigned'],
                $metrics['total_completed'],
                $metrics['total_overdue'],
                $metrics['response_sla_met'],
                $metrics['completion_sla_met'],
                $metrics['repeat_failure_count'],
                $metrics['response_sla_percentage'],
                $metrics['completion_sla_percentage'],
                $metrics['first_time_fix_percentage'],
                $metrics['completion_rate_percentage'],
                $metrics['mttr_hours'],
                $metrics['average_response_time_minutes'],
                $metrics['overall_score'],
                $metrics['rating'],
                $existing['id'],
                $tenant_id
            ]);
        } else {
            // Insert new record
            $stmt = $connection->prepare("
                INSERT INTO technician_performance
                (tenant_id, technician_id, period_start, period_end, period_type,
                 total_assigned, total_completed, total_overdue,
                 response_sla_met, completion_sla_met, repeat_failure_count,
                 response_sla_percentage, completion_sla_percentage,
                 first_time_fix_percentage, completion_rate_percentage,
                 mttr_hours, average_response_time_minutes,
                 overall_score, rating)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id,
                $metrics['technician_id'],
                $metrics['period_start'],
                $metrics['period_end'],
                $metrics['period_type'],
                $metrics['total_assigned'],
                $metrics['total_completed'],
                $metrics['total_overdue'],
                $metrics['response_sla_met'],
                $metrics['completion_sla_met'],
                $metrics['repeat_failure_count'],
                $metrics['response_sla_percentage'],
                $metrics['completion_sla_percentage'],
                $metrics['first_time_fix_percentage'],
                $metrics['completion_rate_percentage'],
                $metrics['mttr_hours'],
                $metrics['average_response_time_minutes'],
                $metrics['overall_score'],
                $metrics['rating']
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error storing performance metrics: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cached performance metrics for a technician
 * 
 * @param int $technician_id
 * @param string $period_start
 * @param string $period_end
 * @param string $period_type
 * @return array|null Performance metrics or null if not cached
 */
function get_cached_performance_metrics($technician_id, $period_start, $period_end, $period_type = 'monthly') {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    try {
        $stmt = $connection->prepare("
            SELECT * FROM technician_performance
            WHERE tenant_id = ?
            AND technician_id = ?
            AND period_start = ?
            AND period_end = ?
            AND period_type = ?
        ");
        $stmt->execute([$tenant_id, $technician_id, $period_start, $period_end, $period_type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching cached performance metrics: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all artisans' performance for current month (for dashboard)
 * 
 * @param string $order_by Column to sort by ('overall_score', 'completion_sla_percentage', etc)
 * @return array List of artisan performance metrics
 */
function get_team_performance_summary($order_by = 'overall_score', $period_start = null, $period_end = null) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    if (!$period_start) {
        $period_start = date('Y-m-01');
    }
    if (!$period_end) {
        $period_end = date('Y-m-t');
    }

    try {
        $allowedSortColumns = [
            'overall_score',
            'response_sla_percentage',
            'completion_sla_percentage',
            'completion_rate_percentage',
            'first_time_fix_percentage',
            'total_completed',
            'repeat_failure_count',
            'mttr_hours',
            'total_overdue',
            'total_assigned'
        ];
        if (!in_array($order_by, $allowedSortColumns, true)) {
            $order_by = 'overall_score';
        }

        $stmt = $connection->prepare(" 
            WITH assignment_candidates AS (
                SELECT
                    awa.artisan_id,
                    awa.work_order_id,
                    awa.assignment_date,
                    awa.tenant_id
                FROM artisan_work_order_assignments awa
                WHERE awa.tenant_id = ?
                UNION
                SELECT
                    a.artisan_id,
                    wo.wo_id AS work_order_id,
                    COALESCE(wo.submit_date, wo.created_at) AS assignment_date,
                    wo.tenant_id
                FROM work_orders wo
                JOIN artisans a ON a.tenant_id = wo.tenant_id
                    AND (wo.mechanic_id = a.artisan_id OR wo.mechanic_id = a.user_id)
                WHERE wo.tenant_id = ?
            )
            SELECT 
                a.artisan_id,
                a.first_name,
                a.last_name,
                a.employee_id,
                a.performance_score,
                u.username,
                u.email,
                COUNT(DISTINCT awa.work_order_id) as total_assigned,
                COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) as total_completed,
                ROUND(
                    AVG(CASE WHEN wos.response_sla_met = 1 THEN 100 ELSE 0 END), 2
                ) as response_sla_percentage,
                ROUND(
                    AVG(CASE WHEN wos.completion_sla_met = 1 THEN 100 ELSE 0 END), 2
                ) as completion_sla_percentage,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) * 100.0 / NULLIF(COUNT(DISTINCT awa.work_order_id), 0)), 2
                ) as completion_rate_percentage,
                ROUND(
                    AVG(CASE WHEN wos.completion_time_minutes > 0 THEN wos.completion_time_minutes / 60.0 ELSE NULL END), 2
                ) as mttr_hours,
                COUNT(DISTINCT CASE WHEN rf.original_work_order_id IS NOT NULL THEN rf.original_work_order_id END) as repeat_failure_count,
                COUNT(DISTINCT CASE WHEN wo.wo_status != 'Completed' AND wo.needed_date IS NOT NULL AND DATE(wo.needed_date) <= DATE(?) THEN wo.wo_id END) as total_overdue,
                ROUND(
                    ((COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) - COUNT(DISTINCT CASE WHEN rf.original_work_order_id IS NOT NULL THEN rf.original_work_order_id END)) * 100.0 / NULLIF(COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END), 0)), 2
                ) as first_time_fix_percentage,
                ROUND(
                    (AVG(CASE WHEN wos.response_sla_met = 1 THEN 100 ELSE 0 END) * 0.30) +
                    (AVG(CASE WHEN wos.completion_sla_met = 1 THEN 100 ELSE 0 END) * 0.40) +
                    (((COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) - COUNT(DISTINCT CASE WHEN rf.original_work_order_id IS NOT NULL THEN rf.original_work_order_id END)) * 100.0 / NULLIF(COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END), 0)) * 0.20) +
                    ((COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) * 100.0 / NULLIF(COUNT(DISTINCT awa.work_order_id), 0)) * 0.10), 2
                ) as overall_score
            FROM artisans a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN assignment_candidates awa ON a.artisan_id = awa.artisan_id AND awa.tenant_id = a.tenant_id
                AND DATE(awa.assignment_date) >= ?
                AND DATE(awa.assignment_date) <= ?
            LEFT JOIN work_orders wo ON awa.work_order_id = wo.wo_id
            LEFT JOIN work_order_sla wos ON wo.wo_id = wos.work_order_id AND wos.tenant_id = a.tenant_id
            LEFT JOIN repeat_failures rf ON wo.wo_id = rf.original_work_order_id AND rf.tenant_id = a.tenant_id AND rf.created_at >= ? AND rf.created_at <= ?
            WHERE a.tenant_id = ?
            AND a.is_active = 1
            GROUP BY a.artisan_id, a.first_name, a.last_name, a.employee_id, a.performance_score, u.username, u.email
            ORDER BY " . $order_by . " DESC
        ");
        
        $stmt->execute([
            $tenant_id,
            $tenant_id,
            $period_end,
            $period_start,
            $period_end,
            $period_start . ' 00:00:00', 
            $period_end . ' 23:59:59',
            $tenant_id
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add rating based on overall_score
        foreach ($results as &$artisan) {
            $score = $artisan['overall_score'] ?? 0;
            if ($score >= 90) {
                $artisan['rating'] = 'Excellent';
            } elseif ($score >= 80) {
                $artisan['rating'] = 'Good';
            } elseif ($score >= 70) {
                $artisan['rating'] = 'Satisfactory';
            } elseif ($score >= 60) {
                $artisan['rating'] = 'Needs Improvement';
            } else {
                $artisan['rating'] = 'Poor';
            }
            
            // Create display name
            $artisan['username'] = trim($artisan['first_name'] . ' ' . $artisan['last_name']);
            if (!empty($artisan['employee_id'])) {
                $artisan['username'] .= ' (' . $artisan['employee_id'] . ')';
            }
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Error fetching team performance summary: " . $e->getMessage());
        return [];
    }
}

/**
 * Get performance trend for an artisan (last N months)
 * 
 * @param int $artisan_id
 * @param int $num_periods Number of periods to retrieve (default 6)
 * @return array Array of performance metrics for each period
 */
function get_performance_trend($artisan_id, $num_periods = 6) {
    global $connection;
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    $trends = [];
    
    try {
        // Calculate performance for each of the last N months
        for ($i = $num_periods - 1; $i >= 0; $i--) {
            $period_date = date('Y-m-01', strtotime("-$i months"));
            $period_start = date('Y-m-01', strtotime($period_date));
            $period_end = date('Y-m-t', strtotime($period_date));
            
            // Get performance data for this artisan in this period
            $stmt = $connection->prepare("
                WITH assignment_candidates AS (
                    SELECT
                        awa.artisan_id,
                        awa.work_order_id,
                        awa.assignment_date,
                        awa.tenant_id
                    FROM artisan_work_order_assignments awa
                    WHERE awa.tenant_id = ?
                    UNION
                    SELECT
                        a.artisan_id,
                        wo.wo_id AS work_order_id,
                        COALESCE(wo.submit_date, wo.created_at) AS assignment_date,
                        wo.tenant_id
                    FROM work_orders wo
                    JOIN artisans a ON a.tenant_id = wo.tenant_id
                        AND (wo.mechanic_id = a.artisan_id OR wo.mechanic_id = a.user_id)
                    WHERE wo.tenant_id = ?
                )
                SELECT 
                    COUNT(DISTINCT awa.work_order_id) as total_assigned,
                    COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) as total_completed,
                    ROUND(AVG(CASE WHEN wos.response_sla_met = 1 THEN 100 ELSE 0 END), 2) as response_sla_percentage,
                    ROUND(AVG(CASE WHEN wos.completion_sla_met = 1 THEN 100 ELSE 0 END), 2) as completion_sla_percentage,
                    ROUND((COUNT(DISTINCT CASE WHEN wo.completed_at IS NOT NULL OR wo.wo_status = 'Completed' THEN wo.wo_id END) * 100.0 / NULLIF(COUNT(DISTINCT awa.work_order_id), 0)), 2) as completion_rate_percentage,
                    COUNT(DISTINCT CASE WHEN rf.original_work_order_id IS NOT NULL THEN rf.original_work_order_id END) as repeat_failure_count
                FROM assignment_candidates awa
                LEFT JOIN work_orders wo ON awa.work_order_id = wo.wo_id
                LEFT JOIN work_order_sla wos ON wo.wo_id = wos.work_order_id AND wos.tenant_id = awa.tenant_id
                LEFT JOIN repeat_failures rf ON wo.wo_id = rf.original_work_order_id AND rf.created_at >= ? AND rf.created_at <= ?
                WHERE awa.tenant_id = ?
                AND awa.artisan_id = ?
                AND DATE(awa.assignment_date) >= ?
                AND DATE(awa.assignment_date) <= ?
            ");
            
            $stmt->execute([
                $tenant_id,
                $tenant_id,
                $period_start . ' 00:00:00',
                $period_end . ' 23:59:59',
                $tenant_id,
                $artisan_id,
                $period_start,
                $period_end
            ]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                // Calculate first-time fix percentage
                $first_time_fixes = $data['total_completed'] - $data['repeat_failure_count'];
                $first_time_fix_percentage = $data['total_completed'] > 0
                    ? round(($first_time_fixes / $data['total_completed']) * 100, 2)
                    : 0;
                
                // Calculate overall score
                $overall_score = round(
                    ($data['response_sla_percentage'] * 0.30) +
                    ($data['completion_sla_percentage'] * 0.40) +
                    ($first_time_fix_percentage * 0.20) +
                    ($data['completion_rate_percentage'] * 0.10),
                    2
                );
                
                // Determine rating
                if ($overall_score >= 90) {
                    $rating = 'Excellent';
                } elseif ($overall_score >= 80) {
                    $rating = 'Good';
                } elseif ($overall_score >= 70) {
                    $rating = 'Satisfactory';
                } elseif ($overall_score >= 60) {
                    $rating = 'Needs Improvement';
                } else {
                    $rating = 'Poor';
                }
                
                $trends[] = [
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'period_type' => 'monthly',
                    'overall_score' => $overall_score,
                    'response_sla_percentage' => $data['response_sla_percentage'] ?? 0,
                    'completion_sla_percentage' => $data['completion_sla_percentage'] ?? 0,
                    'rating' => $rating,
                    'total_completed' => $data['total_completed'] ?? 0,
                    'first_time_fix_percentage' => $first_time_fix_percentage
                ];
            } else {
                // No data for this period
                $trends[] = [
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'period_type' => 'monthly',
                    'overall_score' => 0,
                    'response_sla_percentage' => 0,
                    'completion_sla_percentage' => 0,
                    'rating' => 'No Data',
                    'total_completed' => 0,
                    'first_time_fix_percentage' => 0
                ];
            }
        }
        
        return $trends;
    } catch (Exception $e) {
        error_log("Error fetching performance trend: " . $e->getMessage());
        return [];
    }
}

/**
 * Get chronic/repeat failure assets (most problematic equipment)
 * 
 * @param int $limit Number of assets to return
 * @param int $days Look back N days (default 30)
 * @return array List of assets with failure counts
 */
function get_chronic_failure_assets($limit = 3, $days = 30) {
    global $connection;
    
    if (!$connection) return [];
    
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    try {
        $sql = "SELECT 
                    rf.asset_id,
                    e.description as asset_name,
                    COUNT(rf.id) as failure_count,
                    COUNT(DISTINCT rf.failure_category) as distinct_failures,
                    MAX(rf.created_at) as last_failure
                FROM repeat_failures rf
                LEFT JOIN equipment e ON rf.asset_id = e.id AND e.tenant_id = ?
                WHERE rf.tenant_id = ? 
                  AND rf.created_at >= ?
                GROUP BY rf.asset_id
                ORDER BY failure_count DESC
                LIMIT ?";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([$tenant_id, $tenant_id, $start_date, $limit]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (is_array($result)) {
            return $result;
        }
        return [];
    } catch (Exception $e) {
        error_log("Error fetching chronic failure assets: " . $e->getMessage());
        return [];
    }
}

/**
 * Get repeat failures for a specific technician in a period
 * 
 * @param int $technician_id Technician user ID
 * @param string $period_start Start date (YYYY-MM-DD)
 * @param string $period_end End date (YYYY-MM-DD)
 * @return array List of repeat failures for the technician
 */
function get_artisan_repeat_failures($artisan_id, $period_start = null, $period_end = null) {
    global $connection;
    
    if (!$connection) return [];
    
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    // Default to current month if not specified
    if (!$period_start) {
        $period_start = date('Y-m-01');
    }
    if (!$period_end) {
        $period_end = date('Y-m-t');
    }
    
    try {
        // Get repeat failures for work orders assigned to this artisan
        $sql = "SELECT 
                    rf.*,
                    e.description as asset_name,
                    wo.wo_number,
                    awa.assignment_date
                FROM repeat_failures rf
                JOIN artisan_work_order_assignments awa ON rf.original_work_order_id = awa.work_order_id AND awa.tenant_id = rf.tenant_id
                LEFT JOIN work_orders wo ON rf.original_work_order_id = wo.wo_id
                LEFT JOIN equipment e ON rf.asset_id = e.id AND e.tenant_id = ?
                WHERE rf.tenant_id = ? 
                  AND awa.artisan_id = ?
                  AND rf.created_at >= ?
                  AND rf.created_at <= ?
                ORDER BY rf.created_at DESC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([
            $tenant_id,
            $tenant_id,
            $artisan_id,
            $period_start . ' 00:00:00',
            $period_end . ' 23:59:59'
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching artisan repeat failures: " . $e->getMessage());
        return [];
    }
}
?>

