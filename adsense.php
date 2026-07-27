<?php

add_action('init', function() {
    if (is_user_logged_in() && !isset($_COOKIE['vitaIsUser'])) {
        setcookie('vitaIsUser', '1', time() + (86400 * 30), '/');
    }
});

add_action('wp_head', function() {
    if (isset($_COOKIE['vitaIsUser']) || is_user_logged_in()) {
        return;
    }
    ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5142881822696003"
     crossorigin="anonymous"></script>
    <?php
}, 0);
