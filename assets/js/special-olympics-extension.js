jQuery(document).ready(function ($) {

   //detect if the checkboxes inside .gf_register_form_role are checked
    $(document).on('change', '.gfield--type-choice.additional_roles input[type="checkbox"]', function () {
        var isChecked = $(this).prop('checked');

       //if value is athlet_in and is checked then uncheck all other checkboxes and disable them and vice versa
        if ($(this).val() === 'athlet_in') {
            if (isChecked) {
                $('.gfield--type-choice.additional_roles input[type="checkbox"]').not(this).prop('checked', false);
                $('.gfield--type-choice.additional_roles input[type="checkbox"]').not(this).prop('disabled', true);
            } else {
                $('.gfield--type-choice.additional_roles input[type="checkbox"]').not(this).prop('disabled', false);
            }
       }
    });

});