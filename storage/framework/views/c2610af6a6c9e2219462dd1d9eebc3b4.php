

<?php $__env->startSection('content'); ?>
    <h2>
        <?php if($status === 'approved'): ?>
            ✅ Loan Application Approved
        <?php else: ?>
            ❌ Loan Application Status Update
        <?php endif; ?>
    </h2>

    <p>Dear <?php echo e($loan->user->personalData->first_name ?? 'Member'); ?>,</p>

    <?php if($status === 'approved'): ?>
        <div class="alert-box alert-success">
            <strong>Congratulations!</strong> Your loan application has been approved.
        </div>

        <p>
            We are pleased to inform you that your loan application <strong><?php echo e($loan->loan_number); ?></strong>
            has been approved by our loan committee.
        </p>

        <p><strong>Loan Details:</strong></p>
        <ul>
            <li><strong>Loan Number:</strong> <?php echo e($loan->loan_number); ?></li>
            <li><strong>Loan Type:</strong> <?php echo e(ucfirst(str_replace('_', ' ', $loan->loan_type))); ?></li>
            <li><strong>Approved Amount:</strong> ₱<?php echo e(number_format($loan->loan_amount, 2)); ?></li>
            <li><strong>Interest Rate:</strong> <?php echo e($loan->interest_rate); ?>%</li>
            <li><strong>Term:</strong> <?php echo e($loan->term_months); ?> months</li>
            <li><strong>Due Date:</strong> <?php echo e($loan->due_date ? $loan->due_date->format('F d, Y') : 'TBA'); ?></li>
        </ul>

        <p>
            Please visit our office to complete the final documentation and release of funds.
            Bring a valid ID and sign the loan agreement.
        </p>

        <a href="<?php echo e(config('app.url')); ?>/loans/<?php echo e($loan->id); ?>" class="button">View Loan Details</a>

        <p>
            Thank you for your trust in Settlers Credit Cooperative. We are committed to helping you
            achieve your financial goals.
        </p>
    <?php else: ?>
        <div class="alert-box alert-warning">
            We regret to inform you that your loan application could not be approved at this time.
        </div>

        <p>
            Your loan application <strong><?php echo e($loan->loan_number); ?></strong> has been carefully reviewed,
            and unfortunately, we are unable to approve it at this time.
        </p>

        <p><strong>Application Details:</strong></p>
        <ul>
            <li><strong>Loan Number:</strong> <?php echo e($loan->loan_number); ?></li>
            <li><strong>Loan Type:</strong> <?php echo e(ucfirst(str_replace('_', ' ', $loan->loan_type))); ?></li>
            <li><strong>Requested Amount:</strong> ₱<?php echo e(number_format($loan->loan_amount, 2)); ?></li>
            <li><strong>Applied On:</strong> <?php echo e($loan->applied_at->format('F d, Y')); ?></li>
        </ul>

        <?php if($reason): ?>
            <p><strong>Reason:</strong> <?php echo e($reason); ?></p>
        <?php endif; ?>

        <p>
            You may reapply after addressing the concerns or contact our office for more information
            on improving your application.
        </p>

        <a href="<?php echo e(config('app.url')); ?>/loans" class="button">View Applications</a>
    <?php endif; ?>

    <p>
        If you have any questions, please don't hesitate to contact us.
    </p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH G:\Coding\WebDevActivities\Activities\PHP\jas\source codes\Cooperative-Management-System\resources\views\emails\loan-status.blade.php ENDPATH**/ ?>