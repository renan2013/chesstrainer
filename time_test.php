<?php
echo "<h2>Timezone Test</h2>";

echo "<h3>Initial Server Settings</h3>";
echo "<p>Current default timezone: " . date_default_timezone_get() . "</p>";
echo "<p>Current date/time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>After Setting to America/Costa_Rica</h3>";
date_default_timezone_set('America/Costa_Rica');
echo "<p>New default timezone: " . date_default_timezone_get() . "</p>";
echo "<p>New date/time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>Raw Timestamps</h3>";
echo "<p>time(): " . time() . "</p>";
echo "<p>gmdate('Y-m-d H:i:s'): " . gmdate('Y-m-d H:i:s') . " (GMT/UTC)</p>";
?>