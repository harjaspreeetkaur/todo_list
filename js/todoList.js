/**
 * jQuery for creating a popup box to display citation examples.
 */
(function ($) {
  Drupal.behaviors.todoList = {
    attach: function (ctx) {
      // When clicking on the name of the display mode we need to check the
      // radio button.
      $('.new-todo').once('customBehavior').on('keydown', function (e) {
        saveData(e, this);
      });

      $('.editing input[type="text"]').once('customBehavior').on('keydown focusout', function (e) {
        saveData(e, this);
      });
    }
  }

  function saveData(event, element) {
    if (event.which === 13 || event.type == 'focusout') {
      var target = $(element).parent().siblings('.form-submit');
      $(target).trigger('mousedown');
    }
  }
})(jQuery);
