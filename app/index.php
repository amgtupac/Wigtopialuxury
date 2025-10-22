<?php
// Prevent directory browsing
http_response_code(403);
echo "Access denied. Directory browsing is not allowed.";
exit();
?>
