

<?php $__env->startSection('content'); ?>
    <h2>Security Verification Code</h2>

    <p>Dear <?php echo e($user->personalData->first_name ?? 'Member'); ?>,</p>

    <?php if($purpose === 'new_device_login'): ?>
        <p>We detected a login from a device that is not yet trusted. Enter the one-time code below to continue.</p>
    <?php elseif($purpose === 'withdrawal_approval'): ?>
        <p>A high-value withdrawal approval is being processed from your account. Enter the one-time code below to continue.
        </p>
    <?php else: ?>
        <p>Use the one-time code below to complete your security verification.</p>
    <?php endif; ?>

    <div class="alert-box alert-warning" style="text-align: center; font-size: 28px; letter-spacing: 6px; font-weight: bold;">
        <?php echo e($otpCode); ?>

    </div>

    <p>This code expires in <?php echo e(\App\Models\SystemSetting::get('security_otp_expiry_minutes', 10)); ?> minutes and can only be
        used once.</p>

    <p>If you did not request this code, please secure your account immediately and contact support.</p>

    <p>
        Best regards,<br>
        <strong>Settlers Credit Cooperative</strong><br>
        <em>Security Team</em>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH G:\Coding\WebDevActivities\Activities\PHP\jas\source codes\Cooperative-Management-System\resources\views\emails\otp-code.blade.php ENDPATH**/ ?>