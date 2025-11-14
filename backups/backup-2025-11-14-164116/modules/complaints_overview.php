<?php
$assignedOptions = [
    'mine' => 'Assigned to me',
    'all' => 'All',
    'unassigned' => 'Unassigned',
];
?>

<section class="dashboard-grid" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap:18px; margin-bottom:24px;">
    <div class="dashboard-card">
        <div class="card-icon" style="background:rgba(37,99,235,0.12); color:#2563eb;">📥</div>
        <div class="card-content">
            <h3>Total Complaints</h3>
            <p class="metric"><?php echo number_format($metrics['total']); ?></p>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-icon" style="background:rgba(249,115,22,0.12); color:#f97316;">⚠️</div>
        <div class="card-content">
            <h3>Open / Active</h3>
            <p class="metric"><?php echo number_format($metrics['open']); ?></p>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-icon" style="background:rgba(239,68,68,0.12); color:#ef4444;">⏰</div>
        <div class="card-content">
            <h3>Overdue</h3>
            <p class="metric"><?php echo number_format($metrics['overdue']); ?></p>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-icon" style="background:rgba(16,185,129,0.12); color:#10b981;">✅</div>
        <div class="card-content">
            <h3>Resolved This Month</h3>
            <p class="metric"><?php echo number_format($metrics['resolved_month']); ?></p>
        </div>
    </div>
</section>

<div class="dashboard-card" style="margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <h2 style="margin:0;">Log New Complaint</h2>
            <p style="margin:4px 0 0 0; color:var(--secondary);">Capture customer feedback, assign owners, and define follow-up.</p>
        </div>
        <button id="toggleComplaintForm" type="button" class="btn btn-primary">
            + New Complaint
        </button>
    </div>
    <form id="newComplaintForm" class="grid-two complaint-form hidden" method="post">
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="action" value="create">

        <div>
            <label class="form-label">Summary *</label>
            <input type="text" name="summary" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Source</label>
            <input type="text" name="source" class="form-control" placeholder="e.g. Contract ABC">
        </div>
        <div>
            <label class="form-label">Channel</label>
            <select name="channel" class="form-control">
                <?php foreach ($channels as $key => $label): ?>
                    <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control">
        </div>
        <div>
            <label class="form-label">Assign To</label>
            <select name="assigned_to" class="form-control">
                <option value="">-- Unassigned --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control">
        </div>
        <div>
            <label class="form-label">Customer Email</label>
            <input type="email" name="customer_email" class="form-control">
        </div>
        <div>
            <label class="form-label">Customer Phone</label>
            <input type="text" name="customer_phone" class="form-control">
        </div>
        <div>
            <label class="form-label">Customer Reference</label>
            <input type="text" name="customer_reference" class="form-control" placeholder="e.g. Ticket #, Account ID">
        </div>
        <div>
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g. Billing, Service Delivery">
        </div>
        <div>
            <label class="form-label">Subcategory</label>
            <input type="text" name="subcategory" class="form-control">
        </div>
        <div style="grid-column:1 / -1;">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Detailed description of the issue"></textarea>
        </div>
        <div style="grid-column:1 / -1;">
            <label class="form-label">Initial Note (optional)</label>
            <textarea name="initial_note" class="form-control" rows="2"></textarea>
        </div>
        <div style="grid-column:1 / -1; display:flex; gap:12px;">
            <button type="submit" class="btn btn-primary">Save Complaint</button>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('newComplaintForm').reset();">Clear</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
        <div>
            <h2 style="margin:0;">Complaint Register</h2>
            <p style="margin:4px 0 0 0; color:var(--secondary); font-size:13px;">Filter by status, priority, channel, and ownership.</p>
        </div>
        <form method="get" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="action" value="overview">
            <select name="status" class="form-control" style="min-width:160px;">
                <option value="all">All Statuses</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="priority" class="form-control" style="min-width:150px;">
                <option value="all">All Priorities</option>
                <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $filters['priority'] === $key ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="channel" class="form-control" style="min-width:140px;">
                <option value="all">All Channels</option>
                <?php foreach ($channels as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $filters['channel'] === $key ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="assigned" class="form-control" style="min-width:160px;">
                <?php foreach ($assignedOptions as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $filters['assigned'] === $key ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control" placeholder="Search code, customer, summary" value="<?php echo e($filters['search']); ?>" style="min-width:200px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="complaints.php" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Complaint</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Channel</th>
                    <th>Assigned</th>
                    <th>Due</th>
                    <th>Logged</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaintList)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:24px; color:var(--secondary);">
                            No complaints match your filters yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaintList as $item): ?>
                        <tr>
                            <td>
                                <strong><a href="complaints.php?action=view&id=<?php echo intval($item['id']); ?>" style="color:var(--primary); text-decoration:none;">
                                    <?php echo e($item['complaint_code']); ?>
                                </a></strong>
                                <div style="font-size:12px; color:var(--secondary);">
                                    <?php echo e($item['summary']); ?><br>
                                    <?php echo e($item['customer_name'] ?: 'No customer'); ?>
                                </div>
                            </td>
                            <td><?php echo e($statuses[$item['status']] ?? ucfirst($item['status'])); ?></td>
                            <td><?php echo e($priorities[$item['priority']] ?? ucfirst($item['priority'])); ?></td>
                            <td><?php echo e($channels[$item['channel']] ?? ucfirst($item['channel'])); ?></td>
                            <td><?php echo e($item['assigned_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo $item['due_date'] ? date('M d, Y', strtotime($item['due_date'])) : '—'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                            <td>
                                <a href="complaints.php?action=view&id=<?php echo intval($item['id']); ?>" class="btn btn-outline btn-sm">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

