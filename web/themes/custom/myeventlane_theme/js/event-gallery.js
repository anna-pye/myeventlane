
// /web/themes/custom/myeventlane_theme/js/event-gallery.js
document.addEventListener('DOMContentLoaded', function () {
  const carousels = document.querySelectorAll('.event-carousel');
  carousels.forEach((carousel) => {
    new Swiper(carousel, {
      slidesPerView: 1,
      loop: true,
      pagination: {
        el: carousel.querySelector('.swiper-pagination'),
        clickable: true
      },
      navigation: {
        nextEl: carousel.querySelector('.swiper-button-next'),
        prevEl: carousel.querySelector('.swiper-button-prev')
      }
    });
  });
});

