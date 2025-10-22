<?php
/**
 * Generate Sitemap
 * Creates an XML sitemap for search engines
 */

require_once '../app/core/db.php';
require_once '../app/core/seo-helper.php';

// Set content type to XML
header('Content-Type: application/xml; charset=utf-8');

// Generate and output sitemap
echo SEOHelper::generateSitemap($pdo);
?>
