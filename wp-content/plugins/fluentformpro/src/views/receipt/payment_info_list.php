<ul class="ffp_payment_info_table">
    <li>
        <b><?php _e('Amount:', 'fluentformpro');?></b> <?php echo $orderTotal; ?></b>
    </li>
    <?php
    $paymentMethod = $submission->payment_method;
    if($payment_method): ?>
        <li>
            <b><?php _e('Payment Method:', 'fluentformpro');?></b> <?php
            $paymenMethod = apply_filters_deprecated(
                'fluentform_payment_method_public_name_' . $paymenMethod,
                [
                    $paymenMethod
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/payment_method_public_name_' . $paymenMethod,
                'Use fluentform/payment_method_public_name_' . $paymenMethod . ' instead of fluentform_payment_method_public_name_' . $paymenMethod
            );
            echo ucfirst(
                apply_filters(
                    'fluentform/payment_method_public_name_' . $paymenMethod,
                    $paymenMethod
                )
            ); ?></b>
        </li>
    <?php endif; ?>
    <?php
    if ($submission->payment_status):
        $allStatus = \FluentFormPro\Payments\PaymentHelper::getPaymentStatuses();
        if (isset($allStatus[$submission->payment_status])) {
            $submission->payment_status = $allStatus[$submission->payment_status];
        }
        ?>
        <li>
            <b><?php _e('Payment Status:', 'fluentformpro');?></b> <?php echo $submission->payment_status; ?></b>
        </li>
    <?php endif; ?>
</ul>
