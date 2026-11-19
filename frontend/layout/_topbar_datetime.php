<?php
/**
 * Live date and time for the top school header.
 *
 * The server prints the first value in the application timezone; js/app.js
 * refreshes it every second so every page shows the current time.
 */
$now = new DateTimeImmutable('now');
?>
<span class="topbar-datetime" data-live-datetime data-timezone="<?= e(APP_TIMEZONE) ?>" role="group" aria-label="Current date and time">
  <span class="topbar-datetime-part">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <span data-datetime-date><?= e($now->format('D, j M Y')) ?></span>
  </span>
  <span class="topbar-datetime-sep" aria-hidden="true">&middot;</span>
  <span class="topbar-datetime-part">
    <i class="bi bi-clock" aria-hidden="true"></i>
    <time data-datetime-time datetime="<?= e($now->format('c')) ?>"><?= e($now->format('g:i:s A')) ?></time>
    <span class="topbar-datetime-zone"><?= e($now->format('T')) ?></span>
  </span>
</span>
