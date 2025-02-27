<?php
/**
 * Plugin Name: Porto Bot Instagram
 * Description: Gera uma imagem personalizada automaticamente para novos posts criados via painel do WordPress ou API REST, salvando-a na pasta do plugin.
 * Version: 1.0
 * Author: Valter Pires
 * Text Domain: porto-bot-instagram
 */

defined('ABSPATH') or die('No script kiddies please!');

// Hook para gerar imagem ao criar um post via API REST
add_action('rest_after_insert_post', 'generate_instagram_image_on_rest', 10, 3);
function generate_instagram_image_on_rest($post, $request, $creating) {
    if ($creating) {
        $post_id = $post->ID;
        $post_title = $post->post_title;
        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');
        $categories = get_the_category($post_id);
        $category_name = !empty($categories) ? esc_html($categories[0]->name) : 'Categoria';

        // Construa o HTML para a imagem
        $html_content = '<div style="width: 1080px; height: 1080px; text-align: center; background-color: #fff; display: flex; justify-content: center; align-items: center; position: relative;">
            '.($thumbnail_url ? '<img src="'.esc_url($thumbnail_url).'" style="max-width: 100%; max-height: 100%; position: absolute; object-fit: cover;" />' : '').'
            <div style="background: rgba(255, 255, 255, 0.8); padding: 20px; border-radius: 8px; position: relative; z-index: 1;">
                <h1 style="font-size: 40px;">'.esc_html($post_title).'</h1>
                <p style="font-size: 20px;">- '.esc_html($category_name).' -</p>
            </div>
        </div>';

        file_put_contents(plugin_dir_path(__FILE__) . 'uploads/temp-' . $post_id . '.html', $html_content); // Este arquivo é temporário
    }
}

// Função para conversão de HTML a imagem usando PHP não é prática sem usar ferramentas como wkhtmltoimage, PhantomJS ou outras APIs, e portanto dado contexto o `temp-###.html` é só referência de aproximação local.

add_action('admin_menu', 'instagram_image_add_admin_menu');
function instagram_image_add_admin_menu() {
    add_menu_page(
        'Configurações Imagem para Instagram',
        'Imagem Instagram',
        'manage_options',
        'instagram_image_settings',
        'instagram_image_settings_page'
    );
}

function instagram_image_settings_page() {
    if (isset($_POST['template_content'])) {
        check_admin_referer('instagram_save_template_nonce');
        update_option('instagram_template_content', wp_unslash($_POST['template_content']));
        echo '<div class="notice notice-success"><p>Template atualizado com sucesso.</p></div>';
    }

    $template_content = get_option('instagram_template_content', 'Use os shortcodes: [TITULO], [IMAGEM_DESTACADA], [CATEGORIA]');
    
    $last_post = get_posts(array(
        'numberposts' => 1,
        'post_type'   => 'post',
        'post_status' => 'publish'
    ));

    $post_title = !empty($last_post) ? get_the_title($last_post[0]->ID) : 'Título do Post';
    $thumbnail_url = !empty($last_post) ? get_the_post_thumbnail_url($last_post[0]->ID, 'full') : 'URL da Imagem';
    $category_name = !empty($last_post) ? esc_html(get_the_category($last_post[0]->ID)[0]->name) : 'Categoria';

    ?>
    <div class="wrap">
        <h1>Configurações do Imagem para Instagram</h1>
        <form method="post">
            <?php wp_nonce_field('instagram_save_template_nonce'); ?>
            <textarea id="templateEditor" name="template_content" rows="10" style="width:100%; margin-bottom: 20px;"><?php echo esc_html($template_content); ?></textarea>
            <input type="submit" class="button button-primary" value="Salvar Template">
        </form>

        <h2>Visualizador</h2>
        <div id="instagram" style="width: 1080px; height: 1080px; margin: 0; padding: 0; overflow: hidden;">
            <?php
            echo str_replace(
                ['[TITULO]', '[IMAGEM_DESTACADA]', '[CATEGORIA]'],
                [esc_html($post_title), esc_url($thumbnail_url), esc_html($category_name)],
                do_shortcode($template_content)
            );
            ?>
        </div>
        <button id="previewButton" class="button" style="margin-top: 10px;">Pré-visualizar</button>
        <button id="generateImageButton" class="button button-secondary" style="margin-top: 10px;">Gerar Imagem</button>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', 'instagram_image_enqueue_scripts');

function instagram_image_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_instagram_image_settings') {
        return;
    }

    wp_enqueue_script('html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', [], null, true);
    wp_enqueue_script('instagram-image-js', plugin_dir_url(__FILE__) . 'js/instagram-image.js', ['html2canvas'], null, true);

    $last_post = get_posts(array(
        'numberposts' => 1,
        'post_type'   => 'post',
        'post_status' => 'publish'
    ));

    $post_title = !empty($last_post) ? get_the_title($last_post[0]->ID) : 'Título do Post';
    $thumbnail_url = !empty($last_post) ? get_the_post_thumbnail_url($last_post[0]->ID, 'full') : 'URL da Imagem';
    $category_name = !empty($last_post) ? esc_html(get_the_category($last_post[0]->ID)[0]->name) : 'Categoria';

    wp_localize_script('instagram-image-js', 'instagram_image_data', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('save_instagram_image_nonce'),
        'post_title' => $post_title,
        'thumbnail_url' => $thumbnail_url,
        'category_name' => $category_name
    ]);
}

add_action('wp_ajax_save_instagram_image', 'save_instagram_image_callback');
function save_instagram_image_callback() {
    if (!wp_verify_nonce($_POST['security'], 'save_instagram_image_nonce')) {
        wp_send_json_error('Falha na verificação de segurança.');
        wp_die();
    }

    $img = $_POST['image'];
    $img = str_replace('data:image/png;base64,', '', $img);
    $img = str_replace(' ', '+', $img);
    $file_data = base64_decode($img);

    if (!$file_data) {
        wp_send_json_error('Erro ao decodificar a imagem');
        wp_die();
    }

    $upload_dir = plugin_dir_path(__FILE__) . 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $last_post = get_posts(array(
        'numberposts' => 1,
        'post_type'   => 'post',
        'post_status' => 'publish'
    ));
    
    if (!empty($last_post)) {
        $post_id = $last_post[0]->ID;
        $post_slug = $last_post[0]->post_name;
    } else {
        wp_send_json_error('Nenhum post encontrado.');
        wp_die();
    }

    $file_name = $post_slug . '-' . $post_id . '-instagram.png';
    $file_path = $upload_dir . $file_name;

    if (file_put_contents($file_path, $file_data)) {
        wp_send_json_success(['url' => plugin_dir_url(__FILE__) . 'uploads/' . basename($file_path)]);
    } else {
        wp_send_json_error('Erro ao salvar a imagem no servidor.');
    }
    wp_die();
}
