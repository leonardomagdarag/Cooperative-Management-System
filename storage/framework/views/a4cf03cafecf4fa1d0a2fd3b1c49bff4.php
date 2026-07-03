

<?php $__env->startSection('content'); ?>
    <h2>📢 New Announcement</h2>

    <div class="alert-box alert-info">
        <strong><?php echo e($announcement->title); ?></strong>
    </div>

    <p>Dear Member,</p>

    <p>We have a new announcement from Settlers Credit Cooperative:</p>

    <div style="background-color: #f8fafc; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <?php echo nl2br(e($announcement->content)); ?>

    </div>

    <p>
        <strong>Posted on:</strong> <?php echo e($announcement->created_at->format('F d, Y h:i A')); ?><br>
        <?php if($announcement->creator): ?>
            <strong>Posted by:</strong> <?php echo e($announcement->creator->personalData->first_name ?? 'Admin'); ?>

            <?php echo e($announcement->creator->personalData->last_name ?? ''); ?>

        <?php endif; ?>
    </p>

    <a href="<?php echo e(config('app.url')); ?>/announcements" class="button">View All Announcements</a>

    <p>
        Stay updated with the latest news and updates from your cooperative.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH G:\Coding\WebDevActivities\Activities\PHP\jas\source codes\Cooperative-Management-System\resources\views\emails\announcement.blade.php ENDPATH**/ ?>