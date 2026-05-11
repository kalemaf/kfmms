<?php
/**
 * Spare Parts Lifecycle Manager
 * Handles comprehensive lifecycle tracking for spare parts
 */
class SparePartsLifecycleManager {
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    /**
     * Record a spare part installation
     */
    public function recordInstallation($data) {
        $sql = "INSERT INTO spare_parts_installations (
            equipment_id, part_id, spare_id, part_number, part_name,
            serial_number, batch_lot_number, installed_by, installed_date,
            wo_id, wop_id, location_on_equipment, expected_lifespan_days,
            expected_lifespan_hours, installation_notes, supplier_id,
            manufacturer, purchase_date, warranty_expiry, unit_cost, currency
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'iiissssssiiisisssssdss',
            $data['equipment_id'],
            $data['part_id'],
            $data['spare_id'],
            $data['part_number'],
            $data['part_name'],
            $data['serial_number'],
            $data['batch_lot_number'],
            $data['installed_by'],
            $data['installed_date'],
            $data['wo_id'],
            $data['wop_id'],
            $data['location_on_equipment'],
            $data['expected_lifespan_days'],
            $data['expected_lifespan_hours'],
            $data['installation_notes'],
            $data['supplier_id'],
            $data['manufacturer'],
            $data['purchase_date'],
            $data['warranty_expiry'],
            $data['unit_cost'],
            $data['currency']
        );

        $result = mysqli_stmt_execute($stmt);
        $installation_id = mysqli_insert_id($this->connection);
        mysqli_stmt_close($stmt);

        if ($result) {
            $this->updateEquipmentReliability($data['equipment_id'], $data['location_on_equipment']);
            $this->checkWarrantyAlerts($installation_id);
        }

        return $result ? $installation_id : false;
    }

    /**
     * Record a spare part replacement
     */
    public function recordReplacement($data) {
        // First, get the installation details
        $installation = $this->getInstallation($data['installation_id']);
        if (!$installation) return false;

        // Calculate actual lifespan
        $installed_date = strtotime($installation['installed_date']);
        $replaced_date = strtotime($data['replaced_date']);
        $actual_days = round(($replaced_date - $installed_date) / (60 * 60 * 24));
        $actual_hours = isset($data['operating_hours_at_replacement']) ?
            $data['operating_hours_at_replacement'] - ($installation['expected_lifespan_hours'] ?? 0) : null;

        $sql = "INSERT INTO spare_parts_replacements (
            installation_id, equipment_id, replaced_by, replaced_date,
            wo_id, wop_id, replacement_reason, failure_mode, failure_analysis,
            actual_lifespan_days, actual_lifespan_hours, operating_hours_at_replacement,
            condition_when_removed, reuse_potential, scrap_value, replacement_notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'iisssisssidiidsss',
            $data['installation_id'],
            $installation['equipment_id'],
            $data['replaced_by'],
            $data['replaced_date'],
            $data['wo_id'],
            $data['wop_id'],
            $data['replacement_reason'],
            $data['failure_mode'],
            $data['failure_analysis'],
            $actual_days,
            $actual_hours,
            $data['operating_hours_at_replacement'],
            $data['condition_when_removed'],
            $data['reuse_potential'],
            $data['scrap_value'],
            $data['replacement_notes']
        );

        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($result) {
            // Mark installation as inactive
            $this->deactivateInstallation($data['installation_id']);

            // Update performance metrics
            $this->updateSupplierPerformance($installation['supplier_id'], $installation['supplier_name'], $actual_days, $data['replacement_reason']);
            $this->updateMaintenancePerformance($data['replaced_by'], $data['replacement_reason']);
            $this->updateEquipmentReliability($installation['equipment_id'], $installation['location_on_equipment'], $actual_days);

            // Check for performance alerts
            $this->checkPerformanceAlerts($installation, $actual_days, $data['replacement_reason']);
        }

        return $result;
    }

    /**
     * Get installation details
     */
    private function getInstallation($installation_id) {
        $sql = "SELECT * FROM spare_parts_installations WHERE id = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $installation_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $installation = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $installation;
    }

    /**
     * Deactivate an installation
     */
    private function deactivateInstallation($installation_id) {
        $sql = "UPDATE spare_parts_installations SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $installation_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Update supplier performance metrics
     */
    private function updateSupplierPerformance($supplier_id, $supplier_name, $lifespan_days, $replacement_reason) {
        if (empty($supplier_name)) return;

        // Get or create supplier record
        $sql = "SELECT id FROM supplier_performance WHERE supplier_name = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 's', $supplier_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $supplier = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$supplier) {
            $sql = "INSERT INTO supplier_performance (supplier_id, supplier_name, total_parts_supplied) VALUES (?, ?, 1)";
            $stmt = mysqli_prepare($this->connection, $sql);
            mysqli_stmt_bind_param($stmt, 'is', $supplier_id, $supplier_name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return;
        }

        // Update metrics
        $is_failure = in_array($replacement_reason, ['failure', 'other']) ? 1 : 0;
        $sql = "UPDATE supplier_performance SET
            total_parts_supplied = total_parts_supplied + 1,
            total_failures = total_failures + ?,
            average_lifespan_days = (
                (average_lifespan_days * (total_parts_supplied - 1)) + ?
            ) / total_parts_supplied,
            failure_rate_per_year = (total_failures / total_parts_supplied) * 365 / ?,
            quality_score = 10 - ((total_failures / total_parts_supplied) * 10)
            WHERE supplier_name = ?";

        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'iidss', $is_failure, $lifespan_days, $lifespan_days, $supplier_name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Update maintenance performance metrics
     */
    private function updateMaintenancePerformance($technician, $replacement_reason) {
        if (empty($technician)) return;

        // Get or create technician record
        $sql = "SELECT id FROM maintenance_performance WHERE technician_id = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 's', $technician);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $tech = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$tech) {
            $sql = "INSERT INTO maintenance_performance (technician_id, technician_name, total_replacements) VALUES (?, ?, 1)";
            $stmt = mysqli_prepare($this->connection, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $technician, $technician);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return;
        }

        // Update metrics
        $is_failure = in_array($replacement_reason, ['failure', 'other']) ? 1 : 0;
        $sql = "UPDATE maintenance_performance SET
            total_replacements = total_replacements + 1,
            failed_installations = failed_installations + ?,
            quality_score = 10 - ((failed_installations / total_replacements) * 10)
            WHERE technician_id = ?";

        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $is_failure, $technician);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Update equipment reliability metrics
     */
    private function updateEquipmentReliability($equipment_id, $location, $lifespan_days = null) {
        if (empty($location)) return;

        // Get or create equipment reliability record
        $sql = "SELECT id, total_installations, total_failures FROM equipment_reliability
                WHERE equipment_id = ? AND part_location = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $equipment_id, $location);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reliability = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$reliability) {
            $sql = "INSERT INTO equipment_reliability (equipment_id, part_location, total_installations) VALUES (?, ?, 1)";
            $stmt = mysqli_prepare($this->connection, $sql);
            mysqli_stmt_bind_param($stmt, 'is', $equipment_id, $location);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return;
        }

        // Update metrics if we have failure data
        if ($lifespan_days !== null) {
            $is_failure = $lifespan_days < 30 ? 1 : 0; // Consider early failure
            $sql = "UPDATE equipment_reliability SET
                total_installations = total_installations + 1,
                total_failures = total_failures + ?,
                average_lifespan_days = (
                    (average_lifespan_days * (total_installations - 1)) + ?
                ) / total_installations,
                mtbf_days = average_lifespan_days,
                failure_rate_per_year = (total_failures / total_installations) * 365 / ?,
                reliability_score = 10 - ((total_failures / total_installations) * 10)
                WHERE equipment_id = ? AND part_location = ?";

            $stmt = mysqli_prepare($this->connection, $sql);
            mysqli_stmt_bind_param($stmt, 'idiis', $is_failure, $lifespan_days, $lifespan_days, $equipment_id, $location);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    /**
     * Check for performance alerts
     */
    private function checkPerformanceAlerts($installation, $lifespan_days, $replacement_reason) {
        $alerts = [];

        // Early failure alert
        if ($lifespan_days < 30 && $replacement_reason == 'failure') {
            $alerts[] = [
                'type' => 'early_failure',
                'severity' => 'high',
                'title' => 'Early Part Failure',
                'message' => "Part {$installation['part_name']} failed after only {$lifespan_days} days on equipment {$installation['equipment_id']}",
                'reference_id' => $installation['id'],
                'reference_type' => 'installation'
            ];
        }

        // Supplier performance alert
        if ($installation['supplier_id']) {
            $supplier = $this->getSupplierPerformance($installation['supplier_id']);
            if ($supplier && $supplier['failure_rate_per_year'] > 0.5) { // More than 50% annual failure rate
                $alerts[] = [
                    'type' => 'supplier_performance',
                    'severity' => 'medium',
                    'title' => 'Poor Supplier Performance',
                    'message' => "Supplier {$supplier['supplier_name']} has a failure rate of " . number_format($supplier['failure_rate_per_year'] * 100, 1) . "% per year",
                    'reference_id' => $supplier['id'],
                    'reference_type' => 'supplier'
                ];
            }
        }

        // Create alerts
        foreach ($alerts as $alert) {
            $this->createAlert($alert);
        }
    }

    /**
     * Check for warranty expiry alerts
     */
    private function checkWarrantyAlerts($installation_id) {
        $installation = $this->getInstallation($installation_id);
        if ($installation && $installation['warranty_expiry']) {
            $expiry_date = strtotime($installation['warranty_expiry']);
            $now = time();
            $days_until_expiry = ($expiry_date - $now) / (60 * 60 * 24);

            if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
                $this->createAlert([
                    'type' => 'warranty_expiry',
                    'severity' => 'low',
                    'title' => 'Warranty Expiring Soon',
                    'message' => "Warranty for part {$installation['part_name']} expires in " . round($days_until_expiry) . " days",
                    'reference_id' => $installation_id,
                    'reference_type' => 'installation'
                ]);
            }
        }
    }

    /**
     * Create an alert
     */
    private function createAlert($alert_data) {
        $sql = "INSERT INTO lifecycle_alerts (alert_type, severity, reference_id, reference_type, title, message)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'ssisss',
            $alert_data['type'],
            $alert_data['severity'],
            $alert_data['reference_id'],
            $alert_data['reference_type'],
            $alert_data['title'],
            $alert_data['message']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Get supplier performance
     */
    private function getSupplierPerformance($supplier_id) {
        $sql = "SELECT * FROM supplier_performance WHERE supplier_id = ?";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $supplier = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $supplier;
    }

    /**
     * Get active installations for equipment
     */
    public function getActiveInstallations($equipment_id) {
        $sql = "SELECT * FROM spare_parts_installations
                WHERE equipment_id = ? AND is_active = 1
                ORDER BY installed_date DESC";
        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $equipment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $installations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $installations[] = $row;
        }

        mysqli_stmt_close($stmt);
        return $installations;
    }

    /**
     * Get analytics data
     */
    public function getAnalyticsData($type, $limit = 50) {
        switch ($type) {
            case 'suppliers':
                $sql = "SELECT * FROM supplier_performance ORDER BY quality_score DESC LIMIT ?";
                break;
            case 'maintenance':
                $sql = "SELECT * FROM maintenance_performance ORDER BY quality_score DESC LIMIT ?";
                break;
            case 'equipment':
                $sql = "SELECT * FROM equipment_reliability ORDER BY reliability_score DESC LIMIT ?";
                break;
            case 'alerts':
                $sql = "SELECT * FROM lifecycle_alerts WHERE is_acknowledged = 0 ORDER BY created_at DESC LIMIT ?";
                break;
            default:
                return [];
        }

        $stmt = mysqli_prepare($this->connection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        mysqli_stmt_close($stmt);
        return $data;
    }
}
?>