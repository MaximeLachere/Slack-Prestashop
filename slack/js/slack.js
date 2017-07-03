$(document).ready(function() {

    var radio = $('input[type=radio]')

    radio.click(function() {
        var active_radio = $(this).val()
        if (active_radio == 1)
		{
            $(this).parents('.form-group').next().show()
            $(this).parents('.form-group').next().next().show()
        } else {
            $(this).parents('.form-group').next().hide()
            $(this).parents('.form-group').next().next().hide()
        }
    })

    var radorder = $('#SLACK_NEW_ORDER_off');

    if (radorder.is(':checked'))
    {
        radorder.parents('.form-group').next().hide();
    }

    var radcustomer = $('#SLACK_NEW_CUSTOMER_off');

    if (radcustomer.is(':checked'))
    {
        radcustomer.parents('.form-group').next().hide();
    }

    var radstock = $('#SLACK_OUT_OF_STOCK_off');

    if (radstock.is(':checked'))
    {
        radstock.parents('.form-group').next().hide();
        radstock.parents('.form-group').next().next().hide();       
    }
})
