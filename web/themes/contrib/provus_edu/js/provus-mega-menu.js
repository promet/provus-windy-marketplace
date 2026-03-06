(function (Drupal, once) {
  "use strict";

  const BREAKPOINT = 1440;

  function update(navbarParent) {
    const isDesktop = window.innerWidth >= BREAKPOINT;

    navbarParent.classList.add("provus-mega-menu");
    navbarParent.classList.toggle("provus-mega-menu-desktop", isDesktop);
    navbarParent.classList.toggle("provus-mega-menu-mobile", !isDesktop);
  }

  Drupal.behaviors.provusMegaMenuClasses = {
    attach(context) {
      once("provusMegaMenuClasses", "body", context).forEach(() => {
        const navbarParent = document.getElementById("CollapsingNavbar");
        if (!navbarParent) return;

        const run = () => update(navbarParent);
        run();

        const debounced = Drupal.debounce(run, 120);
        window.addEventListener("resize", debounced, { passive: true });
        window.addEventListener("orientationchange", debounced, { passive: true });
      });
    },
  };

  function triggerMobileStatusChanged(isMobile) {
    // 1) Нативна подія (можеш ловити через addEventListener)
    document.dispatchEvent(new CustomEvent("mobileStatusChanged", { detail: { isMobile } }));

    if (window.jQuery) {
      window.jQuery(document).trigger("mobileStatusChanged", [isMobile]);
    }
  }

  Drupal.behaviors.provusNavResponsive = {
    attach(context) {
      once("provusNavResponsive", "body", context).forEach(() => {
        const navBar = document.querySelector("nav.menu--main ul.navbar-nav");
        if (!navBar) return;

        let currentState = null;

        const update = () => {
          const isMobile = window.innerWidth < BREAKPOINT && navBar.children.length > 0;
          const newState = isMobile ? "mobile" : "desktop";

          if (newState === currentState) return;

          navBar.classList.toggle("mobile-nav", isMobile);
          navBar.classList.toggle("desktop-nav", !isMobile);

          currentState = newState;
          triggerMobileStatusChanged(isMobile);
        };

        update();

        const debounced = Drupal.debounce(update, 120);
        window.addEventListener("resize", debounced, { passive: true });
        window.addEventListener("orientationchange", debounced, { passive: true });
      });
    },
  };
})(Drupal, once);
