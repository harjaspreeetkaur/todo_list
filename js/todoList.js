/**
 * jQuery for creating a handling To-do list events.
 */
(function ($) {
  Drupal.behaviors.todoList = {
    attach: function (ctx) {
      // Trigger the list save when enter button is pressed.
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
