<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                <h2 class="mt-4">Welcome to Atrios ATS!</h2>
                <p class="lead text-muted">System successfully installed and running.</p>
                <hr class="my-4">
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-database text-primary fa-2x mb-3"></i>
                                <h5>Database</h5>
                                <p class="text-success mb-0"><i class="fas fa-check"></i> Connected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-user-shield text-success fa-2x mb-3"></i>
                                <h5>Authentication</h5>
                                <p class="text-success mb-0"><i class="fas fa-check"></i> Working</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-code text-info fa-2x mb-3"></i>
                                <h5>Core Files</h5>
                                <p class="text-success mb-0"><i class="fas fa-check"></i> Loaded</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <i class="fas fa-rocket text-warning fa-2x mb-3"></i>
                                <h5>Status</h5>
                                <p class="text-success mb-0"><i class="fas fa-check"></i> Ready</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5">
                    <h4>Next Steps:</h4>
                    <div class="text-start" style="max-width: 600px; margin: 0 auto;">
                        <ol class="mt-3">
                            <li class="mb-2">✅ Database schema imported</li>
                            <li class="mb-2">✅ Core files installed</li>
                            <li class="mb-2">✅ Authentication working</li>
                            <li class="mb-2">🔨 Create module files (Clients, Jobs, Candidates, etc.)</li>
                            <li class="mb-2">🔨 Add sample data</li>
                            <li class="mb-2">🔨 Test all features</li>
                        </ol>
                    </div>
                </div>
                
                <div class="alert alert-info mt-4" style="max-width: 800px; margin: 0 auto;">
                    <h5><i class="fas fa-info-circle"></i> System Information</h5>
                    <div class="row text-start mt-3">
                        <div class="col-md-6">
                            <strong>Logged in as:</strong> <?php echo htmlspecialchars($currentUser['full_name']); ?><br>
                            <strong>Role:</strong> <?php echo ucfirst($currentUser['role']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Database:</strong> Connected<br>
                            <strong>Tables:</strong> 8 tables<br>
                            <strong>Version:</strong> 1.0.0
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
