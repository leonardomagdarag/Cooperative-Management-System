

<?php $__env->startSection('content'); ?>
    <h2>
        <?php if($isOverdue): ?>
            🚨 Loan Payment Overdue
        <?php else: ?>
            ⏰ Loan Payment Due Soon
        <?php endif; ?>
    </h2>

    <p>Dear <?php echo e($loan->user->personalData->first_name ?? 'Member'); ?>,</p>

    <?php if($isOverdue): ?>
        <div class="alert-box alert-danger">
            <strong>URGENT:</strong> Your loan payment is now overdue.
        </div>

        <p>
            Your loan payment for <strong><?php echo e($loan->loan_number); ?></strong> was due on
            <strong><?php echo e($loan->due_date->format('F d, Y')); ?></strong> and is now overdue.
        </p>

        <p>
            <strong>Loan Amount:</strong> ₱<?php echo e(number_format($loan->loan_amount, 2)); ?><br>
            <strong>Days Overdue:</strong> <?php echo e(abs($loan->due_date->diffInDays(now()))); ?> day(s)
        </p>

        <p>
            Please settle your payment as soon as possible to avoid additional penalties and maintain your good standing
            with the cooperative.
        </p>
    <?php else: ?>
        <?php if($daysRemaining == 1): ?>
            <div class="alert-box alert-danger">
                <strong>URGENT:</strong> Your loan payment is due tomorrow!
            </div>
        <?php elseif($daysRemaining == 3): ?>
            <div class="alert-box alert-warning">
                <strong>REMINDER:</strong> Your loan payment is due in 3 days.
            </div>
        <?php else: ?>
            <div class="alert-box alert-info">
                <strong>REMINDER:</strong> Your loan payment is due in <?php echo e($daysRemaining); ?> days.
            </div>
        <?php endif; ?>

        <p>
            This is a friendly reminder that your loan payment for <strong><?php echo e($loan->loan_number); ?></strong>
            is due on <strong><?php echo e($loan->due_date->format('F d, Y')); ?></strong>.
        </p>

        <p>
            <strong>Loan Amount:</strong> ₱<?php echo e(number_format($loan->loan_amount, 2)); ?><br>
            <strong>Loan Type:</strong> <?php echo e(ucfirst(str_replace('_', ' ', $loan->loan_type))); ?><br>
            <strong>Due Date:</strong> <?php echo e($loan->due_date->format('F d, Y')); ?>

        </p>

        <p>Please ensure you have sufficient funds for the payment.</p>
    <?php endif; ?>

    <a href="<?php echo e(config('app.url')); ?>/loans" class="button">View Loan Details</a>

    <p>
        If you have any questions or need assistance, please contact our office during business hours.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH G:\Coding\WebDevActivities\Activities\PHP\jas\source codes\Cooperative-Management-System\resources\views\emails\loan-due.blade.php ENDPATH**/ ?>