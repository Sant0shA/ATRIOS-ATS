<?php
$pageTitle = 'Clients';
require_once '../includes/header.php';

requireRole(['admin', 'manager']);

// Handle delete
if (isset($_GET['delete']) && $_GET['delete']) {
    $client_id = intval($_GET['delete']);
    try {
        $stmt = $db->prepare("DELETE FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        setFlashMessage('success', 'Client deleted successfully');
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error deleting client: ' . $e->getMessage());
    }
}

// Get all clients
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT * FROM clients WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
    setFlashMessage('error', 'Error fetching clients: ' . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-building"></i> Clients</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Client
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search clients..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
        
        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-4x text-muted mb-3"></i>
                <h4>No Clients Found</h4>
                <p class="text-muted">Start by adding your first client</p>
                <a href="add.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Add Client
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($client['company_name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($client['contact_person']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone'] ?? '-'); ?></td>
                            <td><?php echo statusBadge($client['status']); ?></td>
                            <td><?php echo formatDate($client['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-danger confirm-delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
