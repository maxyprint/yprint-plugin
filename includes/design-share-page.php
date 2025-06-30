<?php
/**
 * YPrint Design Share Page
 * Erstellt eine schöne Share-Seite für Designs entsprechend den Corporate Design Richtlinien
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Design Share Page Handler
 */
class YPrint_Design_Share_Page {

    /**
     * Initialize the class
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'handle_design_share_page'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
    }

    /**
     * Add rewrite rules for design share URLs
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^design-share/([0-9]+)/?$',
            'index.php?design_share=1&design_id=$matches[1]',
            'top'
        );
    }

    /**
     * Add query vars
     */
    public static function add_query_vars($vars) {
        $vars[] = 'design_share';
        $vars[] = 'design_id';
        return $vars;
    }

    /**
     * Handle design share page template
     */
    public static function handle_design_share_page() {
        if (get_query_var('design_share') && get_query_var('design_id')) {
            $design_id = intval(get_query_var('design_id'));
            self::render_design_share_page($design_id);
            exit;
        }
    }

    /**
     * Get design data from database
     */
    private static function get_design_data($design_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'octo_user_designs';
        $design = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $design_id
        ));

        if (!$design) {
            return false;
        }

        // Parse design data
        $design_data = json_decode($design->design_data, true);
        $product_images = json_decode($design->product_images, true);

        return array(
            'id' => $design->id,
            'name' => $design->name ?: 'Individuelles Design',
            'created_at' => $design->created_at,
            'design_data' => $design_data,
            'product_images' => $product_images,
            'preview_url' => self::get_best_preview_url($design),
            'template_id' => $design->template_id
        );
    }

    /**
     * Get best preview URL for design
     */
    private static function get_best_preview_url($design) {
        // Try product_images first
        if (!empty($design->product_images)) {
            $product_images = json_decode($design->product_images, true);
            if (is_array($product_images) && !empty($product_images)) {
                return $product_images[0]['url'] ?? '';
            }
        }
        
        // Fallback to design_data
        if (!empty($design->design_data)) {
            $design_data = json_decode($design->design_data, true);
            if (is_array($design_data) && !empty($design_data['images'])) {
                return $design_data['images'][0]['url'] ?? '';
            }
        }
        
        return '';
    }

    /**
     * Get design template info
     */
    private static function get_template_info($template_id) {
        if (!$template_id) {
            return array('name' => 'Unbekanntes Produkt', 'category' => 'Kleidung');
        }

        $template = get_post($template_id);
        if (!$template) {
            return array('name' => 'Unbekanntes Produkt', 'category' => 'Kleidung');
        }

        $category_terms = get_the_terms($template_id, 'product_cat');
        $category_name = 'Kleidung';
        if ($category_terms && !is_wp_error($category_terms)) {
            $category_name = $category_terms[0]->name;
        }

        return array(
            'name' => $template->post_title,
            'category' => $category_name
        );
    }

    /**
     * Render the design share page
     */
    public static function render_design_share_page($design_id) {
        $design = self::get_design_data($design_id);
        
        if (!$design) {
            wp_die('Design nicht gefunden', 'Design nicht gefunden', array('response' => 404));
        }

        $template_info = self::get_template_info($design['template_id']);
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_url = home_url("/design-share/{$design_id}/");
        
        // Meta tags for social sharing
        $meta_title = "Schau dir mein Design an: {$design['name']} | {$site_name}";
        $meta_description = "Individuelles {$template_info['category']}-Design erstellt auf {$site_name}. Entdecke kreative Designs und erstelle deine eigenen!";
        $meta_image = $design['preview_url'] ?: (home_url() . '/wp-content/uploads/2025/02/120225-logo.svg');

        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($meta_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo esc_attr($meta_description); ?>">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo esc_attr($meta_title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($meta_description); ?>">
    <meta property="og:image" content="<?php echo esc_url($meta_image); ?>">
    <meta property="og:url" content="<?php echo esc_url($current_url); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($meta_title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($meta_description); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($meta_image); ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo esc_url($current_url); ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo home_url(); ?>/wp-content/uploads/2024/10/y-icon.svg" type="image/svg+xml">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* CSS entsprechend YPrint Corporate Design Richtlinien */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #F6F7FA 0%, #FFFFFF 100%);
            min-height: 100vh;
            color: #1d1d1f;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: #FFFFFF;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            border-radius: 16px;
            padding: 20px 30px;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1d1d1f;
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 600;
            color: #0079FF;
        }

        .header-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .design-preview {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e5e5;
            text-align: center;
        }

        .design-image {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .design-image-placeholder {
            width: 100%;
            max-width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .design-image-placeholder i {
            font-size: 64px;
            color: rgba(255, 255, 255, 0.7);
        }

        .design-info {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e5e5;
        }

        .design-title {
            font-size: 32px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .design-meta {
            color: #6e6e73;
            font-size: 16px;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item i {
            color: #0079FF;
            width: 16px;
        }

        .design-description {
            background: #F6F7FA;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #0079FF;
        }

        .description-title {
            font-size: 18px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 10px;
        }

        .description-text {
            font-size: 16px;
            color: #6e6e73;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn-primary {
            background: #0079FF;
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
        }

        .btn-secondary {
            background: #f5f5f7;
            color: #1d1d1f;
            border: 1px solid #e5e5e5;
        }

        .btn-secondary:hover {
            background: #e5e5e5;
            transform: translateY(-1px);
        }

        /* Share Buttons */
        .share-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e5e5;
        }

        .share-title {
            font-size: 18px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 15px;
        }

        .share-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .share-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .share-whatsapp {
            background: #25D366;
            color: white;
        }

        .share-facebook {
            background: #1877F2;
            color: white;
        }

        .share-twitter {
            background: #1DA1F2;
            color: white;
        }

        .share-telegram {
            background: #0088CC;
            color: white;
        }

        .share-copy {
            background: #f5f5f7;
            color: #1d1d1f;
            border: 1px solid #e5e5e5;
        }

        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Footer */
        .footer {
            margin-top: 60px;
            padding: 30px;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #e5e5e5;
        }

        .footer-content {
            color: #6e6e73;
            font-size: 14px;
        }

        .footer-logo {
            color: #0079FF;
            font-weight: 600;
            text-decoration: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .main-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .design-title {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .share-buttons {
                grid-template-columns: repeat(2, 1fr);
            }

            .design-preview,
            .design-info,
            .header,
            .footer {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .design-title {
                font-size: 20px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 14px;
            }

            .share-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <a href="<?php echo esc_url($site_url); ?>" class="logo">
                    <img src="<?php echo home_url(); ?>/wp-content/uploads/2025/02/120225-logo.svg" alt="<?php echo esc_attr($site_name); ?> Logo">
                    <span class="logo-text"><?php echo esc_html($site_name); ?></span>
                </a>
                <nav class="header-nav">
                    <a href="<?php echo esc_url(home_url('/designer')); ?>" class="btn btn-secondary">
                        <i class="fas fa-plus"></i>
                        Eigenes Design erstellen
                    </a>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Design Preview -->
            <div class="design-preview">
                <?php if ($design['preview_url']) : ?>
                    <img src="<?php echo esc_url($design['preview_url']); ?>" 
                         alt="<?php echo esc_attr($design['name']); ?>" 
                         class="design-image">
                <?php else : ?>
                    <div class="design-image-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Design Info -->
            <div class="design-info">
                <h1 class="design-title"><?php echo esc_html($design['name']); ?></h1>
                
                <div class="design-meta">
                    <div class="meta-item">
                        <i class="fas fa-tshirt"></i>
                        <span><?php echo esc_html($template_info['name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-tag"></i>
                        <span><?php echo esc_html($template_info['category']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span>Erstellt am <?php echo date('d.m.Y', strtotime($design['created_at'])); ?></span>
                    </div>
                </div>

                <div class="design-description">
                    <h3 class="description-title">Individuelles Design</h3>
                    <p class="description-text">
                        Dieses einzigartige Design wurde mit unserem Online-Designer erstellt. 
                        Jedes Design ist ein Unikat und spiegelt die Kreativität seines Designers wider.
                    </p>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo esc_url(home_url('/designer')); ?>" class="btn btn-primary">
                        <i class="fas fa-palette"></i>
                        Eigenes Design erstellen
                    </a>
                    <a href="<?php echo esc_url($site_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Zur Startseite
                    </a>
                </div>

                <!-- Share Section -->
                <div class="share-section">
                    <h3 class="share-title">Design teilen</h3>
                    <div class="share-buttons">
                        <a href="#" class="share-btn share-whatsapp" onclick="shareDesign('whatsapp')">
                            <i class="fab fa-whatsapp"></i>
                            <span>WhatsApp</span>
                        </a>
                        <a href="#" class="share-btn share-facebook" onclick="shareDesign('facebook')">
                            <i class="fab fa-facebook"></i>
                            <span>Facebook</span>
                        </a>
                        <a href="#" class="share-btn share-twitter" onclick="shareDesign('twitter')">
                            <i class="fab fa-twitter"></i>
                            <span>Twitter</span>
                        </a>
                        <a href="#" class="share-btn share-telegram" onclick="shareDesign('telegram')">
                            <i class="fab fa-telegram"></i>
                            <span>Telegram</span>
                        </a>
                        <a href="#" class="share-btn share-copy" onclick="copyToClipboard()">
                            <i class="fas fa-copy"></i>
                            <span>Link kopieren</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <p>Erstellt mit <a href="<?php echo esc_url($site_url); ?>" class="footer-logo"><?php echo esc_html($site_name); ?></a> - Deinem Online-Designer für individuelle Kleidung</p>
            </div>
        </footer>
    </div>

    <script>
        const designData = {
            title: <?php echo json_encode($meta_title); ?>,
            description: <?php echo json_encode($meta_description); ?>,
            url: <?php echo json_encode($current_url); ?>,
            image: <?php echo json_encode($meta_image); ?>
        };

        function shareDesign(platform) {
            const { title, description, url } = designData;
            let shareUrl = '';

            switch(platform) {
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`;
                    break;
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
                    break;
                case 'telegram':
                    shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
                    break;
            }

            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        function copyToClipboard() {
            navigator.clipboard.writeText(designData.url).then(() => {
                // Visual feedback
                const button = event.target.closest('.share-copy');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<i class="fas fa-check"></i><span>Kopiert!</span>';
                button.style.background = '#28a745';
                button.style.color = 'white';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = '#f5f5f7';
                    button.style.color = '#1d1d1f';
                }, 2000);
            }).catch(() => {
                alert('Fehler beim Kopieren des Links');
            });
        }

        // Add some hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const shareButtons = document.querySelectorAll('.share-btn');
            shareButtons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
        <?php
    }
}

// Initialize the design share page
YPrint_Design_Share_Page::init();

// Flush rewrite rules on activation (add this to your main plugin file)
register_activation_hook(__FILE__, function() {
    YPrint_Design_Share_Page::add_rewrite_rules();
    flush_rewrite_rules();
});
?>