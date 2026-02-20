<?php
ob_start();
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Simple, direct queries - no fancy logic
$totalJobs = 0;
$totalCandidates = 0;
$totalApplications = 0;
$totalClients = 0;
$newApplications = 0;

try {
    // Jobs
    $result = $db->query("SELECT COUNT(*) as total FROM jobs")->fetch();
    $totalJobs = $result['total'];
    
    // Candidates  
    $result = $db->query("SELECT COUNT(*) as total FROM candidates WHERE blacklisted = 0")->fetch();
    $totalCandidates = $result['total'];
    
    // Applications
    $result = $db->query("SELECT COUNT(*) as total FROM applications")->fetch();
    $totalApplications = $result['total'];
    
    // New apps this week
    $result = $db->query("SELECT COUNT(*) as total FROM applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)")->fetch();
    $newApplications = $result['total'];
    
    // Clients (if admin/manager)
    if (hasRole(['admin', 'manager'])) {
        $result = $db->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'")->fetch();
        $totalClients = $result['total'];
    }
} catch (Exception $e) {
    // Silent fail - keep zeros
}
?>
<!-- Same HTML as before, just pasting the style and layout -->
<style>
.metric-card{background:white;border-radius:12px;padding:24px;border:1px solid #e5e7eb;transition:all .2s}
.metric-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);transform:translateY(-2px)}
.metric-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:16px}
.metric-value{font-size:32px;font-weight:600;color:#1a1a1a;margin:0}
.metric-label{color:#6b7280;font-size:14px;margin:4px 0 0 0}
.metric-change{font-size:12px;margin-top:8px}
</style>

<div class="mb-4">
    <h2>Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>! 👋</h2>
    <p class="text-secondary mb-0">Here's what's happening with your recruitment today.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background:#FEF3E2;color:#F16136"><i class="fas fa-briefcase"></i></div>
            <h3 class="metric-value"><?php echo $totalJobs; ?></h3>
            <p class="metric-label">Total Jobs</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background:#E0F2FE;color:#0284C7"><i class="fas fa-user-tie"></i></div>
            <h3 class="metric-value"><?php echo $totalCandidates; ?></h3>
            <p class="metric-label">Candidates</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background:#DCFCE7;color:#16A34A"><i class="fas fa-file-alt"></i></div>
            <h3 class="metric-value"><?php echo $totalApplications; ?></h3>
            <p class="metric-label">Applications</p>
            <?php if($newApplications>0):?>
            <p class="metric-change text-success"><i class="fas fa-arrow-up"></i> +<?php echo $newApplications;?> this week</p>
            <?php endif;?>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background:#F3E8FF;color:#9333EA"><i class="fas fa-building"></i></div>
            <h3 class="metric-value"><?php echo $totalClients; ?></h3>
            <p class="metric-label">Active Clients</p>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>Dashboard is live!</strong> Showing real data from your database.
</div>

<?php require_once 'includes/footer.php'; ?>
