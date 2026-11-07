  <script>
    window.RRMS_CSRF_TOKEN = <?php echo json_encode(rrms_csrf_token()); ?>;
    (function () {
      var originalOpen = XMLHttpRequest.prototype.open;
      var originalSend = XMLHttpRequest.prototype.send;

      XMLHttpRequest.prototype.open = function (method, url) {
        this.rrmsMethod = method;
        this.rrmsUrl = url;
        return originalOpen.apply(this, arguments);
      };

      XMLHttpRequest.prototype.send = function () {
        var method = String(this.rrmsMethod || '').toUpperCase();
        if (method === 'POST' && window.RRMS_CSRF_TOKEN) {
          this.setRequestHeader('X-CSRF-Token', window.RRMS_CSRF_TOKEN);
        }

        return originalSend.apply(this, arguments);
      };
    })();
  </script>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="index.php" class="logo d-flex align-items-center">
        <img src="../assets/img/request-logo.png" alt="Student Records and Admission Office emblem">
        <span class="d-none d-lg-block request-wordmark" aria-label="reQuest">reQu<span class="brand-accent">e</span>st</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <div class="search-bar">
      <form class="search-form d-flex align-items-center" method="POST" action="#">
        <input type="text" name="query" placeholder="Search" title="Enter search keyword">
        <button type="submit" title="Search"><i class="bi bi-search"></i></button>
      </form>
    </div><!-- End Search Bar -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        <li class="nav-item d-block d-lg-none">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li><!-- End Search Icon-->



        <li class="nav-item dropdown pe-3">

          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="../assets/img/profile.png" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block ps-2"><?php echo rrms_h('('.($_SESSION['acc_id'] ?? '').') '.ucfirst($_SESSION['username'] ?? '')) ?></span>
          </a><!-- End Profile Iamge Icon -->

        </li><!-- End Profile Nav -->

      </ul>
    </nav><!-- End Icons Navigation -->

  </header><!-- End Header -->
