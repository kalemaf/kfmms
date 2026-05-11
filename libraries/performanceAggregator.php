<?php
/**
 * Performance Aggregator Job
 * 
 * Scheduled job that recalculates all technician performance metrics
 * Should be run:
 * - Daily (to update daily metrics)
 * - At end of month (for monthly metrics)
 * - Weekly (for weekly metrics)
 * 
 * Can be triggered:
 * - Via cron job: php /path/to/performance_aggregator.php
 * - Via admin dashboard button
 * - Automatically during work order completion
 */

require_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../common.inc.php';
require_once __DIR__ . '/slaService.php';
require_once __DIR__ . '/performanceService.php';
require_once __DIR__ . '/repeatFailureService.php';

/**
 * Aggregate performance metrics for all technicians
 * 
 * @param string $period_type 'daily', 'weekly', 'monthly', 'yearly'
 * @param string $date Optional specific date to aggregate for (YYYY-MM-DD)
 * @return array Summary of aggregation results
 */
function aggregate_all_technician_performance($period_type = 'monthly', $date = null) {
    global $connection;
    
    // Determine period dates
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    switch ($period_type) {
        case 'daily':
            $period_start = $date;
            $period_end = $date;
            break;
        case 'weekly':
            $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $period_start = $week_start;
            $period_end = $week_end;
            break;
        case 'monthly':
            $month_start = date('Y-m-01', strtotime($date));
            $month_end = date('Y-m-t', strtotime($date));
            $period_start = $month_start;
            $period_end = $month_end;
            break;
        case 'yearly':
            $year = date('Y', strtotime($date));
            $period_start = $year . '-01-01';
            $period_end = $year . '-12-31';
            break;
        default:
            $period_start = date('Y-m-01');
            $period_end = date('Y-m-t');
    }
    
    $results = [
        'period_type' => $period_type,
        'period_start' => $period_start,
        'period_end' => $period_end,
        'technicians_processed' => 0,
        'technicians_updated' => 0,
        'errors' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Get all active technicians and supervisors across all tenants
        $stmt = $connection->prepare("
            SELECT DISTINCT user_id, username, role
            FROM users
            WHERE role IN ('technician', 'supervisor', 'manager')
            AND is_active = 1
            ORDER BY user_id
        ");
        $stmt->execute();
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($technicians as $tech) {
            try {
                // Calculate fresh metrics
                $metrics = calculate_artisan_performance(
                    $tech['user_id'],
                    $period_start,
                    $period_end,
                    $period_type
                );
                
                if (!empty($metrics)) {
                    // Store in cache
                    if (store_performance_metrics($metrics)) {
                        $results['technicians_updated']++;
                    }
                }
                
                $results['technicians_processed']++;
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'technician_id' => $tech['user_id'],
                    'technician_name' => $tech['username'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Log aggregation
        error_log("Performance aggregation completed: " . json_encode($results));
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error in aggregate_all_technician_performance: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Aggregate performance for a specific technician
 * Recalculates their metrics for all recent periods
 * 
 * @param int $technician_id
 * @return array Aggregation results
 */
function aggregate_technician_performance($technician_id) {
    $results = [
        'technician_id' => $technician_id,
        'periods_updated' => 0,
        'errors' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Calculate metrics for current month
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        
        $metrics = calculate_artisan_performance(
            $technician_id,
            $month_start,
            $month_end,
            'monthly'
        );
        
        if (!empty($metrics)) {
            if (store_performance_metrics($metrics)) {
                $results['periods_updated']++;
            }
        }
        
        // Calculate metrics for current week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $metrics = calculate_artisan_performance(
            $technician_id,
            $week_start,
            $week_end,
            'weekly'
        );
        
        if (!empty($metrics)) {
            if (store_performance_metrics($metrics)) {
                $results['periods_updated']++;
            }
        }
        
        // Calculate metrics for today
        $today = date('Y-m-d');
        
        $metrics = calculate_artisan_performance(
            $technician_id,
            $today,
            $today,
            'daily'
        );
        
        if (!empty($metrics)) {
            if (store_performance_metrics($metrics)) {
                $results['periods_updated']++;
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error aggregating technician performance: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Manually trigger aggregation from command line
 * Usage: php performance_aggregator.php [period_type] [date]
 * Examples:
 *   php performance_aggregator.php monthly
 *   php performance_aggregator.php daily 2026-05-07
 *   php performance_aggregator.php
 */
if (php_sapi_name() === 'cli') {
    $period_type = $GLOBALS['argv'][1] ?? 'monthly';
    $date = $GLOBALS['argv'][2] ?? null;
    
    echo "Starting performance aggregation...\n";
    echo "Period: $period_type\n";
    if ($date) {
        echo "Date: $date\n";
    }
    echo "\n";
    
    $result = aggregate_all_technician_performance($period_type, $date);
    
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    echo "\nAggregation completed successfully!\n";
}
