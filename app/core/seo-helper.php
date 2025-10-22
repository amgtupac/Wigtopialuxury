<?php
/**
 * SEO Helper Functions
 * Provides SEO optimization utilities for the website
 */

class SEOHelper {
    
    /**
     * Generate meta tags for a page
     */
    public static function generateMetaTags($options = []) {
        $defaults = [
            'title' => 'Wigtopia - Premium Wigs & Hair Extensions',
            'description' => 'Discover premium wigs, hair extensions, and accessories at Wigtopia. Quality human hair and synthetic options for every style.',
            'keywords' => 'wigs, hair extensions, human hair, synthetic wigs, lace front wigs, hair accessories',
            'image' => 'https://yoursite.com/assets/images/og-image.jpg',
            'url' => self::getCurrentUrl(),
            'type' => 'website',
            'site_name' => 'Wigtopia',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@wigtopia'
        ];
        
        $meta = array_merge($defaults, $options);
        
        $tags = [];
        
        // Basic meta tags
        $tags[] = '<meta charset="UTF-8">';
        $tags[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $tags[] = '<meta name="description" content="' . htmlspecialchars($meta['description']) . '">';
        $tags[] = '<meta name="keywords" content="' . htmlspecialchars($meta['keywords']) . '">';
        $tags[] = '<meta name="author" content="' . htmlspecialchars($meta['site_name']) . '">';
        
        // Open Graph tags
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($meta['title']) . '">';
        $tags[] = '<meta property="og:description" content="' . htmlspecialchars($meta['description']) . '">';
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($meta['image']) . '">';
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($meta['url']) . '">';
        $tags[] = '<meta property="og:type" content="' . htmlspecialchars($meta['type']) . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($meta['site_name']) . '">';
        
        // Twitter Card tags
        $tags[] = '<meta name="twitter:card" content="' . htmlspecialchars($meta['twitter_card']) . '">';
        $tags[] = '<meta name="twitter:site" content="' . htmlspecialchars($meta['twitter_site']) . '">';
        $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($meta['title']) . '">';
        $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($meta['description']) . '">';
        $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars($meta['image']) . '">';
        
        // Robots meta
        $tags[] = '<meta name="robots" content="index, follow">';
        
        // Canonical URL
        $tags[] = '<link rel="canonical" href="' . htmlspecialchars($meta['url']) . '">';
        
        return implode("\n    ", $tags);
    }
    
    /**
     * Generate product schema markup
     */
    public static function generateProductSchema($product) {
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['description'],
            'image' => self::getProductImage($product),
            'sku' => $product['id'],
            'offers' => [
                '@type' => 'Offer',
                'url' => self::getCurrentUrl(),
                'priceCurrency' => 'USD',
                'price' => $product['price'],
                'availability' => $product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition'
            ]
        ];
        
        if (!empty($product['category'])) {
            $schema['category'] = $product['category'];
        }
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    
    /**
     * Generate breadcrumb schema markup
     */
    public static function generateBreadcrumbSchema($items) {
        $listItems = [];
        
        foreach ($items as $index => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url']
            ];
        }
        
        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems
        ];
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    
    /**
     * Generate organization schema markup
     */
    public static function generateOrganizationSchema() {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Wigtopia',
            'url' => self::getBaseUrl(),
            'logo' => self::getBaseUrl() . '/assets/images/logo.png',
            'description' => 'Premium wigs, hair extensions, and accessories',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+1-XXX-XXX-XXXX',
                'contactType' => 'Customer Service',
                'email' => 'support@wigtopia.com'
            ],
            'sameAs' => [
                'https://www.facebook.com/wigtopia',
                'https://www.instagram.com/wigtopia',
                'https://twitter.com/wigtopia'
            ]
        ];
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
    }
    
    /**
     * Generate SEO-friendly URL slug
     */
    public static function generateSlug($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Truncate text for meta descriptions
     */
    public static function truncateDescription($text, $length = 160) {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        $text = substr($text, 0, $length);
        $lastSpace = strrpos($text, ' ');
        
        if ($lastSpace !== false) {
            $text = substr($text, 0, $lastSpace);
        }
        
        return $text . '...';
    }
    
    /**
     * Generate sitemap XML
     */
    public static function generateSitemap($pdo) {
        $urls = [];
        
        // Homepage
        $urls[] = [
            'loc' => self::getBaseUrl(),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];
        
        // Products page
        $urls[] = [
            'loc' => self::getBaseUrl() . '/products.php',
            'changefreq' => 'daily',
            'priority' => '0.9'
        ];
        
        // Get all products
        try {
            $stmt = $pdo->query("SELECT id, created_at FROM products ORDER BY created_at DESC");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as $product) {
                $urls[] = [
                    'loc' => self::getBaseUrl() . '/product-details.php?id=' . $product['id'],
                    'lastmod' => date('Y-m-d', strtotime($product['created_at'])),
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
            }
        } catch (PDOException $e) {
            // Handle error silently
        }
        
        // Generate XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            
            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }
            
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Get current URL
     */
    private static function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * Get base URL
     */
    private static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
    
    /**
     * Get product image URL
     */
    private static function getProductImage($product) {
        $images = !empty($product['images']) ? explode(',', $product['images']) : [];
        $mainIndex = $product['main_image_index'] ?? 0;
        $mainImage = $images[$mainIndex] ?? ($images[0] ?? 'default.jpg');
        
        return self::getBaseUrl() . '/uploads/images/' . trim($mainImage);
    }
}
?>
