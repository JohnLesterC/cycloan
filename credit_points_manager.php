<?php
/**
 * Credit Points Management System
 * Handles all credit point operations for users
 */

require_once "CYCLOAN_db.php";

class CreditPointsManager
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Get user's current credit points
     */
    public function getUserPoints($userId)
    {
        $stmt = $this->conn->prepare("SELECT credit_points FROM users1 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['credit_points'] : 0;
    }

    /**
     * Add credit points to a user
     */
    public function addPoints($userId, $points, $reason, $loanId = null, $adminId = null, $adminRole = null)
    {
        // Get current points
        $currentPoints = $this->getUserPoints($userId);
        $newPoints = $currentPoints + $points;

        // Update user's points
        $stmt = $this->conn->prepare("UPDATE users1 SET credit_points = ?, credit_points_updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $newPoints, $userId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Log the change in history
            $this->logPointsChange($userId, $points, $currentPoints, $newPoints, $reason, $loanId, $adminId, $adminRole);
        }

        return $success;
    }

    /**
     * Deduct credit points from a user
     */
    public function deductPoints($userId, $points, $reason, $loanId = null, $adminId = null, $adminRole = null)
    {
        // Get current points
        $currentPoints = $this->getUserPoints($userId);

        // Get minimum allowed points
        $minPoints = $this->getMinimumPoints();
        $newPoints = max($minPoints, $currentPoints - $points);

        // Update user's points
        $stmt = $this->conn->prepare("UPDATE users1 SET credit_points = ?, credit_points_updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $newPoints, $userId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Log the change in history (negative points change)
            $actualDeduction = $currentPoints - $newPoints;
            $this->logPointsChange($userId, -$actualDeduction, $currentPoints, $newPoints, $reason, $loanId, $adminId, $adminRole);
        }

        return $success;
    }

    /**
     * Set exact credit points for a user (Admin override)
     */
    public function setPoints($userId, $points, $reason, $adminId, $adminRole)
    {
        $currentPoints = $this->getUserPoints($userId);
        $pointsChange = $points - $currentPoints;

        // Update user's points
        $stmt = $this->conn->prepare("UPDATE users1 SET credit_points = ?, credit_points_updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $points, $userId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Log the change
            $this->logPointsChange($userId, $pointsChange, $currentPoints, $points, $reason, null, $adminId, $adminRole);
        }

        return $success;
    }

    /**
     * Award points for loan completion
     */
    public function awardLoanCompletionPoints($userId, $loanId)
    {
        $pointsToAward = $this->getSetting('points_per_completed_loan');

        // Check if this is user's first completed loan
        $isFirstLoan = $this->isFirstCompletedLoan($userId, $loanId);
        if ($isFirstLoan) {
            $bonusPoints = $this->getSetting('bonus_points_first_loan');
            $pointsToAward += $bonusPoints;
            $reason = "Loan completion + First loan bonus ({$pointsToAward} points)";
        } else {
            $reason = "Loan completion ({$pointsToAward} points)";
        }

        return $this->addPoints($userId, $pointsToAward, $reason, $loanId);
    }

    /**
     * Award points for on-time payment
     */
    public function awardOnTimePayment($userId, $loanId)
    {
        $points = $this->getSetting('points_per_ontime_payment');
        $reason = "On-time payment (+{$points} points)";
        return $this->addPoints($userId, $points, $reason, $loanId);
    }

    /**
     * Deduct points for late payment
     */
    public function deductLatePayment($userId, $loanId)
    {
        $points = abs($this->getSetting('points_deduction_late_payment'));
        $reason = "Late payment (-{$points} points)";
        return $this->deductPoints($userId, $points, $reason, $loanId);
    }

    /**
     * Deduct points for loan default
     */
    public function deductLoanDefault($userId, $loanId)
    {
        $points = abs($this->getSetting('points_deduction_default'));
        $reason = "Loan default (-{$points} points)";
        return $this->deductPoints($userId, $points, $reason, $loanId);
    }

    /**
     * Get user's credit points history
     */
    public function getUserHistory($userId, $limit = 10)
    {
        $stmt = $this->conn->prepare("
            SELECT cph.*, 
                   CONCAT(a1.first_name, ' ', a1.last_name) as admin1_name,
                   CONCAT(a2.first_name, ' ', a2.last_name) as admin2_name,
                   CONCAT(sa.first_name, ' ', sa.last_name) as superadmin_name
            FROM credit_points_history cph
            LEFT JOIN admin1 a1 ON cph.admin_id = a1.id AND cph.admin_role = 'admin1'
            LEFT JOIN admin2 a2 ON cph.admin_id = a2.id AND cph.admin_role = 'admin2'
            LEFT JOIN superadmins sa ON cph.admin_id = sa.id AND cph.admin_role = 'superadmin'
            WHERE cph.user_id = ?
            ORDER BY cph.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $history;
    }

    /**
     * Log points change to history table
     */
    private function logPointsChange($userId, $pointsChange, $previousPoints, $newPoints, $reason, $loanId, $adminId, $adminRole)
    {
        $stmt = $this->conn->prepare("
            INSERT INTO credit_points_history 
            (user_id, points_change, previous_points, new_points, reason, loan_id, admin_id, admin_role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiisiss", $userId, $pointsChange, $previousPoints, $newPoints, $reason, $loanId, $adminId, $adminRole);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Check if this is user's first completed loan
     */
    private function isFirstCompletedLoan($userId, $currentLoanId)
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as completed_count 
            FROM loans 
            WHERE user_id = ? AND status = 'Closed' AND loan_id != ?
        ");
        $stmt->bind_param("ii", $userId, $currentLoanId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return ($row['completed_count'] == 0);
    }

    /**
     * Get credit points setting value
     */
    private function getSetting($settingName)
    {
        $stmt = $this->conn->prepare("SELECT setting_value FROM credit_points_settings WHERE setting_name = ?");
        $stmt->bind_param("s", $settingName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['setting_value'] : 0;
    }

    /**
     * Get minimum allowed credit points
     */
    private function getMinimumPoints()
    {
        return $this->getSetting('minimum_credit_points');
    }

    /**
     * Get all credit points settings
     */
    public function getAllSettings()
    {
        $result = $this->conn->query("SELECT * FROM credit_points_settings ORDER BY setting_name");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Update credit points setting
     */
    public function updateSetting($settingName, $value)
    {
        $stmt = $this->conn->prepare("UPDATE credit_points_settings SET setting_value = ? WHERE setting_name = ?");
        $stmt->bind_param("is", $value, $settingName);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Get credit points leaderboard
     */
    public function getLeaderboard($limit = 10)
    {
        $stmt = $this->conn->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
                   credit_points, profile_image
            FROM users1 
            WHERE status = 'active'
            ORDER BY credit_points DESC 
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $leaderboard = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $leaderboard;
    }
}

// Global function to get credit points manager instance
function getCreditPointsManager()
{
    global $conn;
    return new CreditPointsManager($conn);
}
?>