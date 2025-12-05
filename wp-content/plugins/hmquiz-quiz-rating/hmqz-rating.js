(function ($) {
  function renderStars($container, average) {
    var rounded = Math.round(average || 0);
    $container.empty();
    for (var i = 1; i <= 5; i++) {
      var $star = $('<span/>')
        .addClass('hmqz-star')
        .attr('data-value', i)
        .text('★');
      if (i <= rounded) {
        $star.addClass('hmqz-star--active');
      }
      $container.append($star);
    }
  }

  $(function () {
    if (typeof hmqzRating === 'undefined') {
      return;
    }

    var postId = hmqzRating.postId;
    var $widget = $('.hmqz-rating');
    if (!$widget.length || !postId) {
      return;
    }

    var $starsContainer = $widget.find('.hmqz-rating-stars');
    var initialAvg = parseFloat($starsContainer.data('current-rating')) || 0;
    renderStars($starsContainer, initialAvg);

    $widget.on('click', '.hmqz-star', function () {
      var value = parseInt($(this).data('value'), 10);
      if (!value) {
        return;
      }

      // Simple client-side guard to avoid spam clicking
      if ($widget.data('hmqz-rated')) {
        $widget.find('.hmqz-rating-message').text('You already rated this quiz.');
        return;
      }

      $.ajax({
        method: 'POST',
        url: hmqzRating.restUrl,
        beforeSend: function (xhr) {
          if (hmqzRating.nonce) {
            xhr.setRequestHeader('X-WP-Nonce', hmqzRating.nonce);
          }
        },
        data: {
          post_id: postId,
          rating: value
        }
      }).done(function (resp) {
        if (resp && resp.success) {
          $widget.data('hmqz-rated', true);
          renderStars($starsContainer, resp.average);
          $widget.find('.hmqz-rating-summary').text(resp.average + ' / 5 (' + resp.count + ' votes)');
          $widget.find('.hmqz-rating-message').text(
            hmqzRating.strings.thanks + ' ' +
            hmqzRating.strings.rated + ' ' +
            value + ' ' +
            hmqzRating.strings.outOf
          );
        } else {
          $widget.find('.hmqz-rating-message').text('Sorry, something went wrong.');
        }
      }).fail(function () {
        $widget.find('.hmqz-rating-message').text('Sorry, something went wrong.');
      });
    });
  });
})(jQuery);
