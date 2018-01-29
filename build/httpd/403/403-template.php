<?php

  // Template for the 403 error page.

?>
<!doctype html>
<!--[if lt IE 7]><html lang="en" class="no-js lt-ie10 lt-ie9 lt-ie8 lt-ie7"><![endif]-->
<!--[if IE 7]><html lang="en" class="no-js ie7 lt-ie10 lt-ie9 lt-ie8"><![endif]-->
<!--[if IE 8]><html lang="en" class="no-js ie8 lt-ie10 lt-ie9"><![endif]-->
<!--[if IE 9]><html lang="en" class="no-js ie9 lt-ie10"><![endif]-->
<!--[if !IE]><!--><html xmlns="http://www.w3.org/1999/xhtml" lang="en" class="no-js"><!--<![endif]-->
<head>
  <title>Access Denied</title>
  <meta charset="utf-8" />
  <meta name="description" content="" />

  <!-- include http://static.epfl.ch/latest/includes/head-links.html -->
  <!-- build:remove:release -->
    
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" data-header-version="0.26.1" />
  <link rel="shortcut icon" type="image/x-icon" href="//static.epfl.ch/v0.26.1/favicon.ico" />
  <link rel="icon" type="image/png" href="//static.epfl.ch/v0.26.1/favicon.png" />
  <link rel="stylesheet" href="//static.epfl.ch/v0.26.1/styles/epfl-built.css">
  <!-- /build -->
  
  <!-- include http://static.epfl.ch/latest/includes/head-scripts.html -->
  <!-- build:remove:release -->
        <!--[if lt IE 9]>
  <link id="respond-proxy" rel="respond-proxy" href="//static.epfl.ch/v0.26.1/includes/respond-proxy.html" />
  <script src="//static.epfl.ch/v0.26.1/scripts/ie-built.js"></script>
  <![endif]-->

  
  <!-- /build -->
  
</head>
<body>

  <div id="page" class="site site-wrapper" itemscope itemtype="http://schema.org/WebPage">

    <!-- The minimal EPFL header -->
    <header id="epfl-header" class="site-header epfl" role="banner" aria-label="Global EPFL banner" data-ajax-header="//static.epfl.ch/v0.26.1/includes/epfl-header.en.html">

      <!-- The EPFL logo -->
      <div class="logo">
        <a href="http://www.epfl.ch">
          <span class="visuallyhidden">EPFL Homepage</span>
          <object type="image/svg+xml" class="logo-object" data="//static.epfl.ch/v0.26.1/images/logo.svg">
            <img alt="EPFL Logo" width="95" height="46" src="//static.epfl.ch/v0.26.1/images/logo.png" />
          </object>
        </a>
      </div>

    </header>

    <!-- The page content -->
    <main id="content" role="main" class="site-content page page-wrapper" itemprop="mainEntityOfPage">

     <!-- The main column -->
      <div class="g-span-2_3 g-span-s-1_1">

        <!-- The page header -->
        <header class="page-header">
          <h1 class="page-title">Access Denied</h1>
        </header>

        <!-- The page content -->
        <div class="page-content">
          <?php include ("403-{$error_type}.php") ?>
        </div>

        <!-- The debug informations -->
        <div style:"padding-top:5em">
          <?php include ("403-debug.php") ?>
        </div>

      </div>

      </main>

        <!-- The site footer -->
    <footer id="footer" class="site-footer" role="contentinfo">
      <div class="g-span-1_1">
        <nav class="nav nav-inline" role="navigation" aria-label="Footer links">
          <ul class="nav-list">
            <li class="nav-item">
              <a class="nav-link" href="mailto:1234@epfl.ch" accesskey="9">Help desk</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="http://static.epfl.ch/latest/accessibility.en.html" accesskey="0"><meta itemprop="accessibilityAPI" content="ARIA"/>Accessibility</a>
            </li>
            <li class="nav-item secondary-content"> &copy; <span itemprop="copyrightHolder">EPFL</span> <span itemprop="copyrightYear">2018</span></li>
          </ul>
        </nav>
      </div>
    </footer>

  </div>

  <!-- Footer scripts -->
  <!-- include http://static.epfl.ch/latest/includes/foot-scripts.html -->
  <!-- build:remove:release -->
    
  <script src="//static.epfl.ch/v0.26.1/scripts/epfl-jquery-built.js"></script>
  <!-- /build -->
  <script>
    require(["epfl-jquery"], function($){
      "use strict";

      // Custom scripts

      $(function() {
        // Custom scripts to execute after the document has fully loaded
      });

    });
  </script>

</body>
</html>

