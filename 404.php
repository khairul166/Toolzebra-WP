<?php get_header(); ?>

  <main id="main-content" class="tn-not-found">
    <div class="container">
      <div class="tn-not-found__content">
        <div class="tn-not-found__art" aria-hidden="true">
          <span class="tn-not-found__spark tn-not-found__spark--one">✦</span>
          <span class="tn-not-found__spark tn-not-found__spark--two">✳</span>
          <div class="tn-not-found__window">
            <div class="tn-not-found__window-bar">
              <span></span><span></span><span></span>
              <span class="tn-not-found__window-label">toolzebra.xyz</span>
            </div>
            <div class="tn-not-found__window-body">
              <span class="tn-not-found__number">4</span>
              <span class="tn-not-found__magnifier"><i class="bi bi-search"></i></span>
              <span class="tn-not-found__number">4</span>
            </div>
          </div>
          <span class="tn-not-found__orbit tn-not-found__orbit--one"></span>
          <span class="tn-not-found__orbit tn-not-found__orbit--two"></span>
        </div>

        <p class="tn-not-found__eyebrow"><span></span> Error 404</p>
        <h1 class="tn-not-found__title">Oops! This page took a wrong turn.</h1>
        <p class="tn-not-found__description">
          The page you’re looking for may have moved, or it might not exist anymore. Let’s get you back on track.
        </p>
        <div class="tn-not-found__actions">
          <a href="<?php echo esc_url(home_url('/')); ?>" class="btn tn-btn--primary">
            <i class="bi bi-house-door" aria-hidden="true"></i> Back to home
          </a>
          <a href="<?php echo esc_url(get_permalink(get_page_by_path('all-tools'))); ?>" class="btn tn-btn--outline">
            Explore all tools <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </a>
        </div>
        <p class="tn-not-found__hint">Looking for something specific? Try the search bar above.</p>
      </div>
    </div>
  </main>

<?php get_footer(); ?>