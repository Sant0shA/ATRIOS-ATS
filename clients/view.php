<?php
$pageTitle = 'View Client';
require_once '../includes/header.php';

requireRole(['admin', 'manager']);

if (!isset($_GET['id'])) {
    setFlashMessage('error', 'No client specified');
    header('Location: index.php');
    exit();
}

$client_id = intval($_GET['id']);

try {
    $stmt = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        setFlashMessage('error', 'Client not found');
        header('Location: index.php');
        exit();
    }
    
    // Get jobs for this client
    $stmt = $db->prepare("SELECT * FROM jobs WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$client_id]);
    $jobs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Error fetching client data');
    header('Location: index.php');
    exit();
}
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Clients
    </a>
    <a href="edit.php?id=<?php echo $client_id; ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Edit Client
    </a>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-building"></i> Client Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Company Name</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($client['company_name']); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Contact Person</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($client['contact_person']); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Email</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                        <?php echo htmlspecialchars($client['email']); ?>
                    </a>
                </div>
                <div class="mb-3">
                    <strong>Phone</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($client['phone'] ?? '-'); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Address</strong><br>
                    <span class="text-muted"><?php echo nl2br(htmlspecialchars($client['address'] ?? '-')); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Status</strong><br>
                    <?php echo statusBadge($client['status']); ?>
                </div>
                <div>
                    <strong>Added On</strong><br>
                    <span class="text-muted"><?php echo formatDate($client['created_at']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-briefcase"></i> Jobs (<?php echo count($jobs); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No jobs posted for this client yet</p>
                        <a href="../jobs/add.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary mt-2">
                            <i class="fas fa-plus"></i> Post New Job
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Posted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($job['job_title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($job['location'] ?? '-'); ?></td>
                                    <td><?php echo ucwords(str_replace('-', ' ', $job['employment_type'])); ?></td>
                                    <td><?php echo statusBadge($job['status']); ?></td>
                                    <td><?php echo formatDate($job['created_at']); ?></td>
                                    <td>
                                        <a href="../jobs/view.php?id=<?php echo $job['job_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
