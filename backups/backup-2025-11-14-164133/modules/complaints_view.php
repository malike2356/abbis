<div class="dashboard-card" style="margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;"><?php echo e($complaint['complaint_code']); ?> · <?php echo e($complaint['summary']); ?></h2>
            <p style="margin:4px 0 0 0; color:var(--secondary); font-size:13px;">
                Logged <?php echo date('M d, Y H:i', strtotime($complaint['created_at'])); ?>
                <?php if (!empty($complaint['created_name'])): ?> by <?php echo e($complaint['created_name']); ?><?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="complaints.php" class="btn btn-outline">← Back to register</a>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-top:20px;">
        <div class="mini-card">
            <h4>Status</h4>
            <p><?php echo e($statuses[$complaint['status']] ?? ucfirst($complaint['status'])); ?></p>
        </div>
        <div class="mini-card">
            <h4>Priority</h4>
            <p><?php echo e($priorities[$complaint['priority']] ?? ucfirst($complaint['priority'])); ?></p>
        </div>
        <div class="mini-card">
            <h4>Assigned To</h4>
            <p><?php echo e($complaint['assigned_name'] ?: 'Unassigned'); ?></p>
        </div>
        <div class="mini-card">
            <h4>Due Date</h4>
            <p><?php echo $complaint['due_date'] ? date('M d, Y', strtotime($complaint['due_date'])) : '—'; ?></p>
        </div>
    </div>

    <div style="margin-top:24px; display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px;">
        <div>
            <h3>Customer Details</h3>
            <div class="detail-stack">
                <div><span>Customer:</span> <strong><?php echo e($complaint['customer_name'] ?: 'Not provided'); ?></strong></div>
                <div><span>Email:</span> <?php echo e($complaint['customer_email'] ?: '—'); ?></div>
                <div><span>Phone:</span> <?php echo e($complaint['customer_phone'] ?: '—'); ?></div>
                <div><span>Reference:</span> <?php echo e($complaint['customer_reference'] ?: '—'); ?></div>
                <div><span>Channel:</span> <?php echo e($channels[$complaint['channel']] ?? ucfirst($complaint['channel'])); ?></div>
            </div>
        </div>
        <div>
            <h3>Classification</h3>
            <div class="detail-stack">
                <div><span>Source:</span> <?php echo e($complaint['source'] ?: '—'); ?></div>
                <div><span>Category:</span> <?php echo e($complaint['category'] ?: '—'); ?></div>
                <div><span>Subcategory:</span> <?php echo e($complaint['subcategory'] ?: '—'); ?></div>
                <div><span>Resolved:</span> <?php echo $complaint['resolved_at'] ? date('M d, Y H:i', strtotime($complaint['resolved_at'])) : '—'; ?></div>
                <div><span>Closed:</span> <?php echo $complaint['closed_at'] ? date('M d, Y H:i', strtotime($complaint['closed_at'])) : '—'; ?></div>
            </div>
        </div>
    </div>

    <div style="margin-top:24px;">
        <h3>Description</h3>
        <div class="description-box">
            <?php echo nl2br(e($complaint['description'] ?: 'No detailed description captured.')); ?>
        </div>
    </div>
</div>

<div class="dashboard-grid" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(320px,1fr)); gap:18px; margin-bottom:24px;">
    <div class="dashboard-card">
        <h3>Update Status</h3>
        <form method="post" style="display:flex; flex-direction:column; gap:12px;">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">

            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo e($key); ?>" <?php echo $complaint['status'] === $key ? 'selected' : ''; ?>>
                            <?php echo e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Comment (optional)</label>
                <textarea name="status_comment" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Status</button>
        </form>
    </div>

    <div class="dashboard-card">
        <h3>Assign Owner</h3>
        <form method="post" style="display:flex; flex-direction:column; gap:12px;">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">

            <div>
                <label class="form-label">Assign To</label>
                <select name="assigned_to" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo intval($user['id']); ?>" <?php echo ($complaint['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo e($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Assignment Note</label>
                <textarea name="assignment_note" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Assignment</button>
        </form>
    </div>
</div>

<div class="dashboard-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">Activity History</h3>
    </div>

    <form method="post" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:20px;">
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="action" value="add_note">
        <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">
        <div style="grid-column:1 / -1;">
            <label class="form-label">Add Note</label>
            <textarea name="note_text" class="form-control" rows="3" required placeholder="Provide update, summary or next steps..."></textarea>
        </div>
        <label class="form-checkbox" style="align-items:center;">
            <input type="checkbox" name="internal_only" value="1">
            <span>Internal only (hidden from customer exports)</span>
        </label>
        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary">Post Note</button>
            <button type="reset" class="btn btn-outline">Clear</button>
        </div>
    </form>

    <div class="timeline">
        <?php if (empty($updates)): ?>
            <p style="color:var(--secondary);">No updates have been captured yet.</p>
        <?php else: ?>
            <?php foreach ($updates as $update): ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <?php
                        $icon = '📝';
                        if ($update['update_type'] === 'status_change') $icon = '🔁';
                        if ($update['update_type'] === 'assignment') $icon = '👤';
                        if ($update['update_type'] === 'escalation') $icon = '🚨';
                        echo $icon;
                        ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <strong><?php echo e($update['full_name'] ?? 'System'); ?></strong>
                            <span><?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?></span>
                        </div>
                        <div class="timeline-body">
                            <?php if ($update['update_type'] === 'status_change'): ?>
                                <p>Status changed from <strong><?php echo e($statuses[$update['status_before']] ?? $update['status_before']); ?></strong> to <strong><?php echo e($statuses[$update['status_after']] ?? $update['status_after']); ?></strong>.</p>
                            <?php elseif ($update['update_type'] === 'assignment'): ?>
                                <p>Assignment updated.</p>
                            <?php endif; ?>
                            <?php if (!empty($update['update_text'])): ?>
                                <div class="timeline-note">
                                    <?php echo nl2br(e($update['update_text'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($update['internal_only'])): ?>
                                <span style="font-size:11px; color:#f97316;">Internal note</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

