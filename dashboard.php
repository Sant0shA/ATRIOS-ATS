<?php
ob_start();
/* ============================================================
   FILE: dashboard.php - COMPLETE VERSION
   PURPOSE: Full dashboard with metrics, jobs, and activity
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

/* ============================================================
   [METRICS] - Get Dashboard Metrics
   ============================================================ */
$totalJobs = 0;
$totalCandidates = 0;
$totalApplications = 0;
$totalClients = 0;
$newApplications = 0;
$openJobs = [];
$topJobs = [];
$recentActivity = [];

try {
    // Total Jobs
    $result = $db->query("SELECT COUNT(*) as total FROM jobs")->fetch();
    $totalJobs = $result['total'];
    
    // Total Candidates  
    $result = $db->query("SELECT COUNT(*) as total FROM candidates WHERE blacklisted = 0")->fetch();
    $totalCandidates = $result['total'];
    
    // Total Applications
    $result = $db->query("SELECT COUNT(*) as total FROM applications")->fetch();
    $totalApplications = $result['total'];
    
    // New Applications (last 7 days)
    $result = $db->query("SELECT COUNT(*) as total FROM applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)")->fetch();
    $newApplications = $result['total'];
    
    // Active Clients
    if (hasRole(['admin', 'manager'])) {
        $result = $db->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'")->fetch();
        $totalClients = $result['total'];
    }
    
} catch (Exception $e) {
    // Keep zeros on error
}

/* ============================================================
   [OPEN JOBS] - Get Active Job Postings
   ============================================================ */
try {
    $stmt = $db->query("
        SELECT j.job_id, j.job_title, c.company_name,
               (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as app_count,
               j.created_at
        FROM jobs j
        LEFT JOIN clients c ON j.client_id = c.client_id
        WHERE j.status = 'active'
        ORDER BY j.created_at DESC
        LIMIT 5
    ");
    $openJobs = $stmt->fetchAll();
} catch (Exception $e) {
    $openJobs = [];
}

/* ============================================================
   [TOP JOBS] - Get Top Performing Jobs by Applications
   ============================================================ */
try {
    $stmt = $db->query("
        SELECT j.job_id, j.job_title, c.company_name,
               COUNT(a.application_id) as app_count
        FROM jobs j
        LEFT JOIN clients c ON j.client_id = c.client_id
        LEFT JOIN applications a ON j.job_id = a.job_id
        WHERE j.status IN ('active', 'on-hold', 'draft')
        GROUP BY j.job_id, j.job_title, c.company_name
        HAVING app_count > 0
        ORDER BY app_count DESC
        LIMIT 5
    ");
    $topJobs = $stmt->fetchAll();
} catch (Exception $e) {
    $topJobs = [];
}

/* ============================================================
   [RECENT ACTIVITY] - Get Recent Activity
   ============================================================ */
try {
    $stmt = $db->query("
        SELECT a.activity_id, a.action, a.entity_type, a.description, a.created_at, u.full_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recentActivity = $stmt->fetchAll();
} catch (Exception $e) {
    $recentActivity = [];
}
?>

<style>
.metric-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
}
.metric-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}
.metric-value {
    font-size: 32px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
}
.metric-label {
    color: #6b7280;
    font-size: 14px;
    margin: 4px 0 0 0;
}
.metric-change {
    font-size: 12px;
    margin-top: 8px;
}
.section-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}
.section-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-header h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
}
.section-body {
    padding: 0;
}
.job-item {
    padding: 16px 24px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s;
    text-decoration: none;
    color: inherit;
}
.job-item:last-child {
    border-bottom: none;
}
.job-item:hover {
    background: #f9fafb;
}
.job-info h6 {
    margin: 0 0 4px 0;
    font-size: 15px;
    color: #1a1a1a;
}
.job-meta {
    color: #6b7280;
    font-size: 13px;
}
.job-stats {
    text-align: right;
}
.app-count {
    font-size: 24px;
    font-weight: 600;
    color: #F16136;
    line-height: 1;
}
.app-label {
    font-size: 12px;
    color: #6b7280;
}
.activity-item {
    padding: 12px 24px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    gap: 12px;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.activity-content {
    flex: 1;
    min-width: 0;
}
.activity-content p {
    margin: 0;
    font-size: 14px;
    color: #1f2937;
}
.activity-time {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 2px;
}
</style>

<!-- Welcome Message -->
<div class="mb-4">
    <h2>Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>! 👋</h2>
    <p class="text-secondary mb-0">Here's what's happening with your recruitment today.</p>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Jobs -->
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background: #FEF3E2; color: #F16136;">
                <i class="fas fa-briefcase"></i>
            </div>
            <h3 class="metric-value"><?php echo $totalJobs; ?></h3>
            <p class="metric-label">Total Jobs</p>
        </div>
    </div>
    
    <!-- Total Candidates -->
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background: #E0F2FE; color: #0284C7;">
                <i class="fas fa-user-tie"></i>
            </div>
            <h3 class="metric-value"><?php echo $totalCandidates; ?></h3>
            <p class="metric-label">Candidates</p>
        </div>
    </div>
    
    <!-- Total Applications -->
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background: #DCFCE7; color: #16A34A;">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3 class="metric-value"><?php echo $totalApplications; ?></h3>
            <p class="metric-label">Applications</p>
            <?php if ($newApplications > 0): ?>
                <p class="metric-change text-success">
                    <i class="fas fa-arrow-up"></i> +<?php echo $newApplications; ?> this week
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Active Clients -->
    <div class="col-md-3">
        <div class="metric-card">
            <div class="metric-icon" style="background: #F3E8FF; color: #9333EA;">
                <i class="fas fa-building"></i>
            </div>
            <h3 class="metric-value"><?php echo $totalClients; ?></h3>
            <p class="metric-label">Active Clients</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">
        
        <!-- Open Jobs -->
        <div class="section-card mb-4">
            <div class="section-header">
                <h5><i class="fas fa-briefcase"></i> Open Jobs</h5>
                <a href="jobs/" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="section-body">
                <?php if (empty($openJobs)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0">No active jobs</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($openJobs as $job): ?>
                    <a href="jobs/view.php?id=<?php echo $job['job_id']; ?>" class="job-item">
                        <div class="job-info">
                            <h6><?php echo htmlspecialchars($job['job_title']); ?></h6>
                            <div class="job-meta">
                                <?php echo htmlspecialchars($job['company_name'] ?? 'No Client'); ?> • 
                                Posted <?php echo timeAgo($job['created_at']); ?>
                            </div>
                        </div>
                        <div class="job-stats">
                            <div class="app-count"><?php echo $job['app_count']; ?></div>
                            <div class="app-label">applications</div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Performing Jobs -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-fire"></i> Top Performing Jobs</h5>
            </div>
            <div class="section-body">
                <?php if (empty($topJobs)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <p class="mb-0">No applications yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topJobs as $index => $job): ?>
                    <a href="jobs/view.php?id=<?php echo $job['job_id']; ?>" class="job-item">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div style="font-size: 24px; font-weight: 700; color: #F16136; width: 30px;">
                                #<?php echo $index + 1; ?>
                            </div>
                            <div class="job-info">
                                <h6><?php echo htmlspecialchars($job['job_title']); ?></h6>
                                <div class="job-meta"><?php echo htmlspecialchars($job['company_name'] ?? 'No Client'); ?></div>
                            </div>
                        </div>
                        <div class="job-stats">
                            <div class="app-count"><?php echo $job['app_count']; ?></div>
                            <div class="app-label">applications</div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        
        <!-- Quick Actions -->
        <div class="section-card mb-4">
            <div class="section-header">
                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="section-body" style="padding: 16px 24px;">
                <a href="candidates/add.php" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-plus"></i> Add Candidate
                </a>
                <?php if (hasRole(['admin', 'manager'])): ?>
                <a href="jobs/add.php" class="btn btn-outline w-100 mb-2">
                    <i class="fas fa-plus"></i> Post New Job
                </a>
                <a href="clients/add.php" class="btn btn-outline w-100">
                    <i class="fas fa-plus"></i> Add Client
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-clock"></i> Recent Activity</h5>
            </div>
            <div class="section-body">
                <?php if (empty($recentActivity)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-history fa-2x mb-2"></i>
                        <p class="mb-0">No recent activity</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recentActivity, 0, 5) as $activity): 
                        $iconMap = [
                            'create' => ['icon' => 'fa-plus', 'bg' => '#DCFCE7', 'color' => '#16A34A'],
                            'update' => ['icon' => 'fa-edit', 'bg' => '#E0F2FE', 'color' => '#0284C7'],
                            'delete' => ['icon' => 'fa-trash', 'bg' => '#FEE2E2', 'color' => '#DC2626'],
                            'accept' => ['icon' => 'fa-check', 'bg' => '#DCFCE7', 'color' => '#16A34A'],
                            'reject' => ['icon' => 'fa-times', 'bg' => '#FEE2E2', 'color' => '#DC2626'],
                            'blacklist' => ['icon' => 'fa-ban', 'bg' => '#FEE2E2', 'color' => '#DC2626'],
                        ];
                        $style = $iconMap[$activity['action']] ?? ['icon' => 'fa-circle', 'bg' => '#F3F4F6', 'color' => '#6B7280'];
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                            <i class="fas <?php echo $style['icon']; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                            <div class="activity-time">
                                <?php echo timeAgo($activity['created_at']); ?>
                                <?php if (!empty($activity['full_name'])): ?>
                                    • by <?php echo htmlspecialchars($activity['full_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
