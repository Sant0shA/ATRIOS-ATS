<?php
ob_start();
/* ============================================================
   FILE: dashboard_DEBUG.php
   PURPOSE: Debug version to diagnose why counts are showing 0
   ============================================================ */

$pageTitle = 'Dashboard Debug';
require_once 'includes/header.php';

echo "<div class='container mt-4'>";
echo "<h2>Dashboard Debug Info</h2>";
echo "<div class='alert alert-info'>This page shows what data exists and what queries return</div>";

// Show current user info
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Current User Info:</h5>";
echo "<pre>";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Username: " . $_SESSION['username'] . "\n";
echo "Role: " . $_SESSION['user_role'] . "\n";
echo "Name: " . ($currentUser['full_name'] ?? 'N/A') . "\n";
echo "</pre>";
echo "</div></div>";

// Test 1: Count all jobs
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Test 1: Total Jobs in Database</h5>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM jobs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='lead'>Total jobs in database: <strong>" . $result['total'] . "</strong></p>";
    
    // Show actual jobs
    $stmt = $db->query("SELECT job_id, job_title, status, assigned_to FROM jobs LIMIT 5");
    $jobs = $stmt->fetchAll();
    if (!empty($jobs)) {
        echo "<table class='table table-sm'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Assigned To (JSON)</th></tr>";
        foreach ($jobs as $job) {
            echo "<tr>";
            echo "<td>" . $job['job_id'] . "</td>";
            echo "<td>" . htmlspecialchars($job['job_title']) . "</td>";
            echo "<td>" . $job['status'] . "</td>";
            echo "<td><code>" . htmlspecialchars($job['assigned_to']) . "</code></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// Test 2: Count candidates
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Test 2: Total Candidates in Database</h5>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM candidates");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='lead'>Total candidates: <strong>" . $result['total'] . "</strong></p>";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM candidates WHERE blacklisted = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='lead'>Non-blacklisted candidates: <strong>" . $result['total'] . "</strong></p>";
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// Test 3: Count applications
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Test 3: Total Applications in Database</h5>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM applications");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='lead'>Total applications: <strong>" . $result['total'] . "</strong></p>";
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
echo "</div></div>";

// Test 4: Count clients (if admin/manager)
if (hasRole(['admin', 'manager'])) {
    echo "<div class='card mb-4'><div class='card-body'>";
    echo "<h5>Test 4: Total Clients in Database</h5>";
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM clients");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p class='lead'>Total clients: <strong>" . $result['total'] . "</strong></p>";
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p class='lead'>Active clients: <strong>" . $result['total'] . "</strong></p>";
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
    echo "</div></div>";
}

// Test 5: Role-based query test
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Test 5: Role-Based Query Logic</h5>";
echo "<p>Your role: <strong>" . $_SESSION['user_role'] . "</strong></p>";

if (hasRole('recruiter')) {
    echo "<div class='alert alert-warning'>You are a RECRUITER - should see filtered data</div>";
    
    // Test JSON_CONTAINS
    echo "<p>Testing JSON_CONTAINS for user ID: " . $_SESSION['user_id'] . "</p>";
    try {
        $stmt = $db->prepare("
            SELECT job_id, job_title, assigned_to
            FROM jobs 
            WHERE JSON_CONTAINS(assigned_to, ?, '$')
        ");
        $stmt->execute(['"' . $_SESSION['user_id'] . '"']);
        $assignedJobs = $stmt->fetchAll();
        
        echo "<p class='lead'>Jobs assigned to you: <strong>" . count($assignedJobs) . "</strong></p>";
        
        if (!empty($assignedJobs)) {
            echo "<table class='table table-sm'>";
            echo "<tr><th>ID</th><th>Title</th><th>Assigned To</th></tr>";
            foreach ($assignedJobs as $job) {
                echo "<tr>";
                echo "<td>" . $job['job_id'] . "</td>";
                echo "<td>" . htmlspecialchars($job['job_title']) . "</td>";
                echo "<td><code>" . htmlspecialchars($job['assigned_to']) . "</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>JSON_CONTAINS Error: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='alert alert-success'>You are ADMIN/MANAGER - should see all data</div>";
}
echo "</div></div>";

// Test 6: hasRole function test
echo "<div class='card mb-4'><div class='card-body'>";
echo "<h5>Test 6: hasRole() Function Tests</h5>";
echo "<ul>";
echo "<li>hasRole('admin'): " . (hasRole('admin') ? 'TRUE' : 'FALSE') . "</li>";
echo "<li>hasRole('manager'): " . (hasRole('manager') ? 'TRUE' : 'FALSE') . "</li>";
echo "<li>hasRole('recruiter'): " . (hasRole('recruiter') ? 'TRUE' : 'FALSE') . "</li>";
echo "<li>hasRole(['admin', 'manager']): " . (hasRole(['admin', 'manager']) ? 'TRUE' : 'FALSE') . "</li>";
echo "</ul>";
echo "</div></div>";

echo "</div>";

require_once 'includes/footer.php';
?>
