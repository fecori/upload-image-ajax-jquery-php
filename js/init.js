/**
 *
 * @fileoverview Libreria con funciones de utilidad
 * @author Francisco Cordova
 * @date 13/08/15
 * @version 1.0
 */

$(document).on("ready", function () {

	var _doc = $(document);
	var formSelector = '.form-group,[class*="col-xs-"],[class*="col-sm-"],[class*="col-md-"],[class*="col-lg-"]';

	function readImage(input) {
		if (input.files && input.files[0]) {
			var FR = new FileReader();
			FR.onload = function (e) {
				$("#img").val(e.target.result);
			};
			FR.readAsDataURL(input.files[0]);
		}
	}

	$("#foto").change(function () {
		readImage(this);
	});

	// override jquery validate plugin defaults
	$.validator.setDefaults({
		highlight: function (el) {
			$(el).closest(formSelector).addClass('has-error');
		},
		unhighlight: function (el) {
			$(el).closest(formSelector).removeClass('has-error');
		},
		errorElement: 'span',
		errorClass: 'help-block',
		errorPlacement: function (error, el) {
			error.insertAfter(el);
		}
	});

	$('.contact-form').each(function () {
		$(this).validate({
			submitHandler: function (form) {
				$(form).ajaxSubmit(function (response) {

					response = $.parseJSON(response);

					$(_doc[0].createElement('div'))
						.addClass('alert')
						.toggleClass('alert-danger', !response.success)
						.toggleClass('alert-success', response.success)
						.html(response.message)
						.prepend('<button type="button" class="close" data-dismiss="alert">&times;</button>')
						.hide().prependTo(form).slideDown();

					if (response.success) {
						$(form).resetForm();
					}

				});


			}
		});
	});

});