<?php
/*
Plugin Name: Beauty Progress Tracker
Description: Client-based before/after image tracker for beauty treatments with private pages.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

class BeautyProgressTracker {
    public function __construct() {
        add_action('init', [$this, 'register_client_post_type']);
        add_action('add_meta_boxes', [$this, 'add_client_gallery_box']);
        add_action('save_post', [$this, 'save_client_gallery']);
        add_shortcode('beauty_gallery', [$this, 'render_client_gallery']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_slider_assets']);
    }

    // 1️⃣ Register the "Client" custom post type
    public function register_client_post_type() {
        register_post_type('beauty_client', [
            'label' => 'Clients',
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-admin-users',
            'supports' => ['title'],
        ]);
    }

    // 2️⃣ Add gallery meta box in admin
    public function add_client_gallery_box() {
        add_meta_box(
            'beauty_gallery_box',
            'Before / After Gallery',
            [$this, 'render_gallery_meta_box'],
            'beauty_client',
            'normal',
            'high'
        );
    }

    public function render_gallery_meta_box($post) {
        wp_nonce_field('beauty_gallery_save', 'beauty_gallery_nonce');
        $entries = get_post_meta($post->ID, '_beauty_gallery', true);
        if (!is_array($entries)) $entries = [];

        echo '<div id="beauty-gallery-entries">';
        foreach ($entries as $i => $pair) {
            echo '<div class="pair">';
            echo '<label>Before Image:</label><input type="text" name="beauty_gallery['.$i.'][before]" value="'.esc_attr($pair['before']).'" style="width:60%" />';
            echo '<button class="upload-btn button">Upload</button><br>';
            echo '<label>After Image:</label><input type="text" name="beauty_gallery['.$i.'][after]" value="'.esc_attr($pair['after']).'" style="width:60%" />';
            echo '<button class="upload-btn button">Upload</button><hr>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="add-pair">+ Add New Pair</button>';

        // Simple JS to add pairs dynamically
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const addBtn = document.getElementById('add-pair');
            const container = document.getElementById('beauty-gallery-entries');
            addBtn.addEventListener('click', () => {
                const index = container.children.length;
                const html = `
                <div class="pair">
                    <label>Before Image:</label><input type="text" name="beauty_gallery[${index}][before]" style="width:60%" />
                    <button class="upload-btn button">Upload</button><br>
                    <label>After Image:</label><input type="text" name="beauty_gallery[${index}][after]" style="width:60%" />
                    <button class="upload-btn button">Upload</button><hr>
                </div>`;
                container.insertAdjacentHTML('beforeend', html);
            });
        });
        </script>
        <?php
    }

    public function save_client_gallery($post_id) {
        if (!isset($_POST['beauty_gallery_nonce']) ||
            !wp_verify_nonce($_POST['beauty_gallery_nonce'], 'beauty_gallery_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (get_post_type($post_id) !== 'beauty_client') return;

        if (isset($_POST['beauty_gallery'])) {
            update_post_meta($post_id, '_beauty_gallery', $_POST['beauty_gallery']);
        }
    }

    // 3️⃣ Enqueue slider assets (JS + CSS)
    public function enqueue_slider_assets() {
        wp_enqueue_style('beauty-slider-css', 'https://cdn.jsdelivr.net/npm/before-after-slider/dist/before-after.min.css');
        wp_enqueue_script('beauty-slider-js', 'https://cdn.jsdelivr.net/npm/before-after-slider/dist/before-after.min.js', [], null, true);
    }

    // 4️⃣ Shortcode to show client's before/after gallery
    public function render_client_gallery($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your progress.</p>';
        }

        $user = wp_get_current_user();
        $client_page = get_page_by_title($user->display_name, OBJECT, 'beauty_client');

        if (!$client_page) return '<p>No gallery found for your account.</p>';

        $entries = get_post_meta($client_page->ID, '_beauty_gallery', true);
        if (!is_array($entries) || empty($entries)) return '<p>No treatments uploaded yet.</p>';

        ob_start();
        echo '<div class="beauty-gallery">';
        foreach ($entries as $pair) {
            if (empty($pair['before']) || empty($pair['after'])) continue;
            ?>
            <div class="beauty-pair" style="margin-bottom:40px;">
                <div class="before-after" style="max-width:600px;margin:auto;">
                    <img src="<?php echo esc_url($pair['before']); ?>" alt="Before" />
                    <img src="<?php echo esc_url($pair['after']); ?>" alt="After" />
                </div>
            </div>
            <?php
        }
        echo '</div>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.before-after').forEach(el => {
                new BeforeAfter(el);
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

new BeautyProgressTracker();