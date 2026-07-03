

<?php $__env->startSection('content'); ?>
    <h2>
        <?php if($status === 'approved'): ?>
            ✅ Withdrawal Request Approved
        <?php else: ?>
            ❌ Withdrawal Request Status Update
        <?php endif; ?>
    </h2>

    <p>Dear <?php echo e($withdrawal->user->personalData->first_name ?? 'Member'); ?>,</p>

    <?php if($status === 'approved'): ?>
        <div class="alert-box alert-success">
            <strong>Good news!</strong> Your withdrawal request has been approved.
        </div>

        <p>
            We are pleased to inform you that your withdrawal request has been approved and processed.
        </p>

        <p><strong>Withdrawal Details:</strong></p>
        <ul>
            <li><strong>Transaction Number:</strong> <?php echo e($withdrawal->transaction_number ?? 'N/A'); ?></li>
            <li><strong>Amount:</strong> ₱<?php echo e(number_format($withdrawal->amount, 2)); ?></li>
            <li><strong>Requested On:</strong> <?php echo e($withdrawal->created_at->format('F d, Y h:i A')); ?></li>
            <li><strong>Approved On:</strong> <?php echo e(now()->format('F d, Y h:i A')); ?></li>
        </ul>

        <?php if($withdrawal->note): ?>
            <p><strong>Note:</strong> <?php echo e($withdrawal->note); ?></p>
        <?php endif; ?>

        <p>
            The amount has been deducted from your account. You can now proceed to our office to collect your funds
            or check your bank account if you selected online withdrawal.
        </p>

        <a href="<?php echo e(config('app.url')); ?>/member/history" class="button">View Transaction History</a>

        <p>
            Thank you for being a valued member of Settlers Credit Cooperative.
        </p>
    <?php else: ?>
        <div class="alert-box alert-warning">
            We regret to inform you that your withdrawal request could not be approved at this time.
        </div>

        <p>
            Your withdrawal request has been carefully reviewed, and unfortunately, we are unable to approve it at this
            time.
        </p>

        <p><strong>Withdrawal Details:</strong></p>
        <ul>
            <li><strong>Transaction Number:</strong> <?php echo e($withdrawal->transaction_number ?? 'N/A'); ?></li>
            <li><strong>Amount:</strong> ₱<?php echo e(number_format($withdrawal->amount, 2)); ?></li>
            <li><strong>Requested On:</strong> <?php echo e($withdrawal->created_at->format('F d, Y h:i A')); ?></li>
            <li><strong>Status:</strong> Rejected</li>
        </ul>

        <?php if($reason): ?>
            <p><strong>Reason:</strong> <?php echo e($reason); ?></p>
        <?php else: ?>
            <p><strong>Reason:</strong> Please contact our office for more information.</p>
        <?php endif; ?>

        <p>
            Your account balance remains unchanged. If you have any questions or concerns about this decision,
            please don't hesitate to contact our office.
        </p>

        <a href="<?php echo e(config('app.url')); ?>/member/services" class="button">Submit New Request</a>
    <?php endif; ?>

    <p>
        If you have any questions, please don't hesitate to contact us.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH G:\Coding\WebDevActivities\Activities\PHP\jas\source codes\Cooperative-Management-System\resources\views\emails\withdrawal-status.blade.php ENDPATH**/ ?>