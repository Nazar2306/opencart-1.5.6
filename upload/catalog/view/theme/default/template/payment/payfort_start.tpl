<div class="buttons">
  <div class="right">
    <input type="button" value="<?php echo $button_confirm; ?>" id="button-confirm" class="button" />
  </div>
</div>
<script type="text/javascript"><!--
$('#button-confirm').bind('click', function () {
        $.ajax({
            url: 'index.php?route=payment/payfort_start/send',
            type: 'post',
            data: $('.start_response :input'),
            dataType: 'json',
            beforeSend: function () {
                $('#button-confirm').attr('disabled', true);
                $('#payment').before('<div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php echo $text_wait; ?></div>');
            },
            complete: function () {
                $('#button-confirm').attr('disabled', false);
                $('.attention').remove();
            },
            success: function (json) {
                if (json['error']) {
                    removePaymentToken();
                    $('input[name="payment_method"][value="payfort_start"]').parent().parent().find('td:last-child').append("<span class='error'><br/>Card declined. Please use another card<span>");
                    $('#payment-method>.checkout-heading').find('a:first-child').trigger('click');
                }

                if (json['success']) {
                    location = json['success'];
                }
            }
        });
    });
//--></script>
