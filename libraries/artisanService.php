<?php
/**
 * Artisan Management Service
 * Handles CRUD operations, skills matching, availability checking, and performance tracking
 */

class ArtisanService {
    private $pdo;
    private $tenant_id;
    
    public function __construct($pdoParam = null, $tenant_id = 1) {
        global $pdo;
        $this->pdo = $pdoParam ?? $pdo;
        if ($this->pdo === null && isset($GLOBALS['pdo'])) {
            $this->pdo = $GLOBALS['pdo'];
        }
        $this->tenant_id = $tenant_id ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Create or update artisan record
     */
    public function save_artisan($data) {
        try {
            if (!empty($data['artisan_id'])) {
                return $this->update_artisan($data);
            } else {
                return $this->create_artisan($data);
            }
        } catch (Exception $e) {
            error_log("Error saving artisan: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create new artisan
     */
    private function create_artisan($data) {
        // Prevent duplicate artisan records by user_id or email within the same tenant
        if (!empty($data['user_id']) || !empty($data['email'])) {
            $duplicateSql = "SELECT artisan_id FROM artisans WHERE tenant_id = ? AND (user_id = ?";
            $params = [$this->tenant_id, $data['user_id'] ?? 0];

            if (!empty($data['email'])) {
                $duplicateSql .= " OR email = ?";
                $params[] = $data['email'];
            }
            $duplicateSql .= ") LIMIT 1";

            $duplicateStmt = $this->pdo->prepare($duplicateSql);
            $duplicateStmt->execute($params);
            $existing = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'An artisan profile already exists for this tenant with the same user or email.'
                ];
            }
        }

        $sql = "INSERT INTO artisans (
            user_id, tenant_id, first_name, last_name, employee_id, phone, 
            mobile_phone, email, birth_date, hire_date, vendor_id, hourly_rate,
            cost_center, sms_enabled, push_notifications_enabled, is_active,
            availability_status, available_from_date, available_to_date,
            emergency_contact_name, emergency_contact_phone, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['user_id'] ?? null,
            $this->tenant_id,
            $data['first_name'],
            $data['last_name'],
            $data['employee_id'] ?? null,
            $data['phone'] ?? null,
            $data['mobile_phone'] ?? null,
            $data['email'] ?? null,
            $data['birth_date'] ?? null,
            $data['hire_date'] ?? null,
            $data['vendor_id'] ?? null,
            $data['hourly_rate'] ?? 0,
            $data['cost_center'] ?? null,
            $data['sms_enabled'] ? 1 : 0,
            $data['push_notifications_enabled'] ? 1 : 0,
            $data['is_active'] ? 1 : 0,
            $data['availability_status'] ?? 'available',
            $data['available_from_date'] ?? null,
            $data['available_to_date'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['notes'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);
        
        $artisan_id = $this->pdo->lastInsertId();
        
        // Add skills if provided
        if (!empty($data['skills']) && is_array($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                $this->add_skill($artisan_id, $skill);
            }
        }
        
        // Add certifications if provided
        if (!empty($data['certifications']) && is_array($data['certifications'])) {
            foreach ($data['certifications'] as $cert) {
                $this->add_certification($artisan_id, $cert);
            }
        }
        
        // Add site assignments if provided
        if (!empty($data['sites']) && is_array($data['sites'])) {
            foreach ($data['sites'] as $site) {
                $this->assign_site($artisan_id, $site);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Artisan created successfully',
            'artisan_id' => $artisan_id
        ];
    }
    
    /**
     * Update artisan record
     */
    private function update_artisan($data) {
        $sql = "UPDATE artisans SET
            first_name = ?, last_name = ?, employee_id = ?, phone = ?,
            mobile_phone = ?, email = ?, birth_date = ?, hire_date = ?,
            vendor_id = ?, hourly_rate = ?, cost_center = ?,
            sms_enabled = ?, push_notifications_enabled = ?, is_active = ?,
            availability_status = ?, available_from_date = ?, available_to_date = ?,
            emergency_contact_name = ?, emergency_contact_phone = ?, notes = ?,
            updated_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE artisan_id = ? AND tenant_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['employee_id'] ?? null,
            $data['phone'] ?? null,
            $data['mobile_phone'] ?? null,
            $data['email'] ?? null,
            $data['birth_date'] ?? null,
            $data['hire_date'] ?? null,
            $data['vendor_id'] ?? null,
            $data['hourly_rate'] ?? 0,
            $data['cost_center'] ?? null,
            $data['sms_enabled'] ? 1 : 0,
            $data['push_notifications_enabled'] ? 1 : 0,
            $data['is_active'] ? 1 : 0,
            $data['availability_status'] ?? 'available',
            $data['available_from_date'] ?? null,
            $data['available_to_date'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['notes'] ?? null,
            $_SESSION['user_id'] ?? null,
            $data['artisan_id'],
            $this->tenant_id
        ]);
        
        return [
            'success' => true,
            'message' => 'Artisan updated successfully',
            'artisan_id' => $data['artisan_id']
        ];
    }
    
    /**
     * Get artisan by ID
     */
    public function get_artisan($artisan_id) {
        $sql = "SELECT a.*, u.username, u.email as user_email 
                FROM artisans a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.artisan_id = ? AND a.tenant_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all artisans
     */
    public function get_all_artisans($filters = []) {
        $sql = "SELECT a.*, u.username, COALESCE(a.performance_score, 0) AS performance_score,
                COUNT(DISTINCT s.artisan_skill_id) as skill_count,
                COUNT(DISTINCT c.certification_id) as cert_count
                FROM artisans a
                LEFT JOIN users u ON a.user_id = u.user_id
                LEFT JOIN artisan_skills s ON a.artisan_id = s.artisan_id
                LEFT JOIN artisan_certifications c ON a.artisan_id = c.artisan_id
                WHERE a.tenant_id = ?";
        
        $params = [$this->tenant_id];
        
        if (isset($filters['is_active'])) {
            $sql .= " AND a.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (isset($filters['is_available'])) {
            $sql .= " AND a.is_available_today = ?";
            $params[] = $filters['is_available'];
        }
        
        if (!empty($filters['availability_status'])) {
            $sql .= " AND a.availability_status = ?";
            $params[] = $filters['availability_status'];
        }
        
        $sql .= " GROUP BY a.artisan_id ORDER BY a.first_name, a.last_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add skill to artisan
     */
    public function add_skill($artisan_id, $skill_data) {
        $sql = "INSERT INTO artisan_skills (
            artisan_id, tenant_id, skill_name, skill_category,
            proficiency_level, years_of_experience, is_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(artisan_id, skill_name, tenant_id) DO UPDATE SET
            skill_category = ?, proficiency_level = ?,
            years_of_experience = ?, is_verified = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $artisan_id,
            $this->tenant_id,
            $skill_data['skill_name'],
            $skill_data['skill_category'] ?? null,
            $skill_data['proficiency_level'] ?? 'intermediate',
            $skill_data['years_of_experience'] ?? 0,
            $skill_data['is_verified'] ? 1 : 0,
            $skill_data['skill_category'] ?? null,
            $skill_data['proficiency_level'] ?? 'intermediate',
            $skill_data['years_of_experience'] ?? 0,
            $skill_data['is_verified'] ? 1 : 0
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get artisan skills
     */
    public function get_artisan_skills($artisan_id) {
        $sql = "SELECT * FROM artisan_skills 
                WHERE artisan_id = ? AND tenant_id = ?
                ORDER BY skill_category, skill_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add certification to artisan
     */
    public function add_certification($artisan_id, $cert_data) {
        $sql = "INSERT INTO artisan_certifications (
            artisan_id, tenant_id, certification_name, certification_number,
            issuing_body, issue_date, expiry_date, is_active,
            compliance_requirement, document_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $artisan_id,
            $this->tenant_id,
            $cert_data['certification_name'],
            $cert_data['certification_number'] ?? null,
            $cert_data['issuing_body'] ?? null,
            $cert_data['issue_date'] ?? null,
            $cert_data['expiry_date'] ?? null,
            $cert_data['is_active'] ? 1 : 0,
            $cert_data['compliance_requirement'] ? 1 : 0,
            $cert_data['document_path'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get artisan certifications
     */
    public function get_artisan_certifications($artisan_id) {
        $sql = "SELECT * FROM artisan_certifications 
                WHERE artisan_id = ? AND tenant_id = ?
                ORDER BY certification_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if artisan has required skill
     */
    public function has_skill($artisan_id, $skill_name) {
        $sql = "SELECT COUNT(*) as count FROM artisan_skills 
                WHERE artisan_id = ? AND tenant_id = ? AND skill_name LIKE ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id, "%$skill_name%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Check if artisan is available (today and future availability)
     */
    public function is_available($artisan_id, $check_date = null) {
        if (!$check_date) {
            $check_date = date('Y-m-d');
        }
        
        // Check basic availability status
        $sql = "SELECT is_active, is_available_today, availability_status,
                available_from_date, available_to_date
                FROM artisans WHERE artisan_id = ? AND tenant_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artisan) return false;
        
        // Check if active
        if (!$artisan['is_active']) return false;
        
        // Check availability dates
        if ($artisan['available_from_date'] && $check_date < $artisan['available_from_date']) {
            return false;
        }
        
        if ($artisan['available_to_date'] && $check_date > $artisan['available_to_date']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get available artisans for a work order (with skills and availability matching)
     */
    public function get_available_artisans_for_work_order($work_order_id) {
        // Get work order details including required skills
        $wo_sql = "SELECT wo.*, a.name as asset_name, a.equipment_type 
                   FROM work_orders wo
                   LEFT JOIN assets a ON wo.asset_id = a.asset_id
                   WHERE wo.work_order_id = ? AND wo.tenant_id = ?";
        
        $stmt = $this->pdo->prepare($wo_sql);
        $stmt->execute([$work_order_id, $this->tenant_id]);
        $work_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$work_order) return [];
        
        // Get all active artisans with their skills and certifications
        $sql = "SELECT DISTINCT a.*, u.username,
                GROUP_CONCAT(DISTINCT s.skill_name) as skills,
                COUNT(DISTINCT c.certification_id) as cert_count,
                GROUP_CONCAT(DISTINCT CASE WHEN c.expiry_date > DATE('now') THEN c.certification_name END) as active_certs
                FROM artisans a
                LEFT JOIN users u ON a.user_id = u.user_id
                LEFT JOIN artisan_skills s ON a.artisan_id = s.artisan_id AND s.tenant_id = ?
                LEFT JOIN artisan_certifications c ON a.artisan_id = c.artisan_id AND c.tenant_id = ? AND c.is_active = 1
                WHERE a.tenant_id = ? AND a.is_active = 1
                GROUP BY a.artisan_id
                ORDER BY a.performance_score DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->tenant_id, $this->tenant_id, $this->tenant_id]);
        $artisans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter by availability and add match score
        $available = [];
        foreach ($artisans as $artisan) {
            if ($this->is_available($artisan['artisan_id'], $work_order['assigned_at'])) {
                $artisan['match_score'] = $this->calculate_artisan_match_score(
                    $artisan,
                    $work_order
                );
                $available[] = $artisan;
            }
        }
        
        // Sort by match score (highest first)
        usort($available, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $available;
    }
    
    /**
     * Calculate how well an artisan matches a work order
     */
    private function calculate_artisan_match_score($artisan, $work_order) {
        $score = 50; // Base score
        
        // Performance score bonus (max 30 points)
        $score += min($artisan['performance_score'] / 100 * 30, 30);
        
        // Skill match bonus (max 20 points)
        if (!empty($work_order['description'])) {
            $required_keywords = ['mechanical', 'electrical', 'hydraulic', 'pneumatic', 
                                'plumbing', 'welding', 'hvac', 'refrigeration'];
            $artisan_skills = strtolower($artisan['skills']);
            
            foreach ($required_keywords as $keyword) {
                if (strpos($artisan_skills, $keyword) !== false || 
                    strpos($work_order['description'], $keyword) === false) {
                    $score += 5;
                    break;
                }
            }
        }
        
        // Certification bonus (max 10 points)
        if ($artisan['cert_count'] > 0) {
            $score += min($artisan['cert_count'] * 2, 10);
        }
        
        return min($score, 100);
    }
    
    /**
     * Assign site to artisan
     */
    public function assign_site($artisan_id, $site_data) {
        $sql = "INSERT INTO artisan_site_assignments (
            artisan_id, tenant_id, site_id, company_id, location_name,
            assignment_start_date, assignment_end_date, is_primary_site, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $artisan_id,
            $this->tenant_id,
            $site_data['site_id'] ?? null,
            $site_data['company_id'] ?? null,
            $site_data['location_name'] ?? null,
            $site_data['assignment_start_date'] ?? null,
            $site_data['assignment_end_date'] ?? null,
            $site_data['is_primary_site'] ? 1 : 0,
            $site_data['is_active'] ? 1 : 0
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get artisan site assignments
     */
    public function get_artisan_sites($artisan_id) {
        $sql = "SELECT * FROM artisan_site_assignments 
                WHERE artisan_id = ? AND tenant_id = ? AND is_active = 1
                ORDER BY is_primary_site DESC, location_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Link work order to artisan and track assignment
     */
    public function assign_work_order_to_artisan($work_order_id, $artisan_id, $estimated_hours = null) {
        $sql = "INSERT INTO artisan_work_order_assignments (
            artisan_id, work_order_id, tenant_id, assignment_date,
            estimated_hours, is_primary_assignee
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, 1)
        ON CONFLICT(artisan_id, work_order_id, tenant_id) DO UPDATE SET
            assignment_date = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $work_order_id, $this->tenant_id, $estimated_hours]);
        
        // Update last_assigned_date
        $update_sql = "UPDATE artisans SET last_assigned_date = CURRENT_TIMESTAMP 
                       WHERE artisan_id = ? AND tenant_id = ?";
        $stmt = $this->pdo->prepare($update_sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        
        return true;
    }
    
    /**
     * Get work order assignments for artisan
     */
    public function get_artisan_work_orders($artisan_id) {
        $sql = "SELECT awa.*, wo.wo_id AS work_order_number, wo.wo_status AS status, wo.priority,
                COALESCE(wo.submit_date, wo.updated, wo.created_at) AS assigned_at,
                wo.sla_due_date AS due_date,
                wo.equipment AS asset_name,
                awa.estimated_hours AS estimated_hours,
                awa.actual_hours AS actual_hours
                FROM artisan_work_order_assignments awa
                JOIN work_orders wo ON awa.work_order_id = wo.wo_id
                WHERE awa.artisan_id = ? AND awa.tenant_id = ?
                ORDER BY COALESCE(wo.submit_date, wo.updated, wo.created_at) DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artisan_id, $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update artisan performance score
     */
    public function update_performance_score($artisan_id, $performance_score) {
        $sql = "UPDATE artisans SET performance_score = ? 
                WHERE artisan_id = ? AND tenant_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$performance_score, $artisan_id, $this->tenant_id]);
    }
    
    /**
     * Delete artisan
     */
    public function delete_artisan($artisan_id) {
        try {
            // Delete related records first (cascading delete should handle this, but be explicit)
            $sql = "DELETE FROM artisan_skills WHERE artisan_id = ? AND tenant_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$artisan_id, $this->tenant_id]);
            
            $sql = "DELETE FROM artisan_certifications WHERE artisan_id = ? AND tenant_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$artisan_id, $this->tenant_id]);
            
            $sql = "DELETE FROM artisan_site_assignments WHERE artisan_id = ? AND tenant_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$artisan_id, $this->tenant_id]);
            
            // Delete artisan record
            $sql = "DELETE FROM artisans WHERE artisan_id = ? AND tenant_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$artisan_id, $this->tenant_id]);
            
            return ['success' => true, 'message' => 'Artisan deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
