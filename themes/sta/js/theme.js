//Toggle hamburger class
$(function () {
    $("#mobile-btn").click(function () {
        $(this).toggleClass("toggled");
    });
});

//Dropdown
$(function () {
    if ($(window).width() > 991) {
        $(window).click(function () {
            $(".dropdown-menu").fadeOut(200);});
        $(".dropdown").click(function () {
            $(".dropdown-menu").stop().fadeOut(200);
            $(this).children(".dropdown-menu").stop().fadeToggle(200);
        });
    }
});

// Toggle menu change on scroll
$(function () {
  const onWindowScroll = function () {
    const scrollTop = $(window).scrollTop();
    const header = $("#navbar-top");
    const width = window.matchMedia('(max-width: 767px)').matches;
    if (scrollTop > 0 && !width) {
      header.removeClass('top');
    } else {
      header.addClass('top');
    }
  };

  $(window).scroll(onWindowScroll);
    onWindowScroll();
});
