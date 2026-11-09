/* Site scripts from the previous developer. jQuery is required. */
jQuery(document).ready(function ($) {
  // Holiday announcement injected at the top of the page after load.
  // This pushes the whole page down: a classic layout shift.
  setTimeout(function () {
    $('body').prepend(
      '<div class="hv-announcement">NOW TAKING HOLIDAY ORDERS!!! Call us or <a href="/custom-cakes/" style="color:#fff">click here</a></div>'
    );
  }, 1200);

  // Smooth scroll for every link with a hash.
  $('a[href*="#"]').on('click', function (e) {
    var target = $(this.hash);
    if (target.length) {
      e.preventDefault();
      $('html, body').animate({ scrollTop: target.offset().top }, 800);
    }
  });
});
