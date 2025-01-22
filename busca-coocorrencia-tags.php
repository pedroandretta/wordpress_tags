<?php
/*
Plugin Name: Busca por Coocorrência de Tags
Description: Permite buscar conteúdos com base em Tags e suas coocorrências.
Version: 1.4
Author: Pedro Andretta
*/

function bct_formulario_busca() {
    ob_start();
    ?>

    <form id="bct-formulario-busca">
        <label for="tag1">Escolha o 1º termo (Tag) para consulta.</label><br>
        <input type="text" id="tag1" name="tag1" autocomplete="off"><br><br>

        <label for="tag2">Escolha o 2º termo (Tag) para filtrar a consulta.</label><br>
        <select id="tag2" name="tag2" disabled>
            <option value="">Selecione uma Tag</option>
        </select><br><br>

        <label for="tag3">Escolha o 3º termo (Tag) para filtrar a consulta.</label><br>
        <select id="tag3" name="tag3" disabled>
            <option value="">Selecione uma Tag</option>
        </select><br><br>

        <!-- Campo para exibir a quantidade de posts -->
        <div id="bct-post-count"></div><br>

        <button type="submit">Pesquisar</button>
        <button type="button" id="bct-limpar-campos">Limpar todos os campos</button>
    </form>

    <?php
    return ob_get_clean();
}
add_shortcode('busca_coocorrencia_tags', 'bct_formulario_busca');

function bct_enqueue_scripts() {
    wp_enqueue_script('jquery-ui-autocomplete');
    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_script('bct-script', plugins_url('/js/bct-script.js', __FILE__), array('jquery', 'jquery-ui-autocomplete'), null, true);

    wp_localize_script('bct-script', 'bct_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bct_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'bct_enqueue_scripts');

function bct_get_tags() {
    check_ajax_referer('bct_nonce', 'nonce');

    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    $tags = get_terms(array(
        'taxonomy' => 'post_tag',
        'name__like' => $term,
        'hide_empty' => false,
    ));

    $results = array();
    foreach ($tags as $tag) {
        $results[] = $tag->name;
    }

    wp_send_json($results);
}
add_action('wp_ajax_bct_get_tags', 'bct_get_tags');
add_action('wp_ajax_nopriv_bct_get_tags', 'bct_get_tags');

function bct_get_cooccurring_tags() {
    check_ajax_referer('bct_nonce', 'nonce');

    $selected_tags = isset($_GET['selected_tags']) ? array_map('sanitize_text_field', $_GET['selected_tags']) : array();

    if (empty($selected_tags)) {
        wp_send_json(array());
        wp_die();
    }

    $args = array(
        'tag' => implode('+', $selected_tags),
        'numberposts' => -1,
        'fields' => 'ids',
    );

    $posts = get_posts($args);

    if (empty($posts)) {
        wp_send_json(array());
        wp_die();
    }

    $tags = wp_get_object_terms($posts, 'post_tag', array('fields' => 'names'));
    $tags = array_unique($tags);

    // Remove as Tags já selecionadas
    $tags = array_filter($tags, function($tag) use ($selected_tags) {
        return !in_array($tag, $selected_tags);
    });

    wp_send_json(array_values($tags));
}
add_action('wp_ajax_bct_get_cooccurring_tags', 'bct_get_cooccurring_tags');
add_action('wp_ajax_nopriv_bct_get_cooccurring_tags', 'bct_get_cooccurring_tags');

function bct_get_post_count() {
    check_ajax_referer('bct_nonce', 'nonce');

    $tag1 = isset($_POST['tag1']) ? sanitize_text_field($_POST['tag1']) : '';
    $tag2 = isset($_POST['tag2']) ? sanitize_text_field($_POST['tag2']) : '';
    $tag3 = isset($_POST['tag3']) ? sanitize_text_field($_POST['tag3']) : '';

    if (!$tag1) {
        wp_send_json(array('count' => 0));
        wp_die();
    }

    $tags = array($tag1);

    if ($tag2) {
        $tags[] = $tag2;
    }
    if ($tag3) {
        $tags[] = $tag3;
    }

    $args = array(
        'tag' => implode('+', $tags),
        'posts_per_page' => -1,
        'fields' => 'ids',
    );

    $posts = get_posts($args);
    $count = count($posts);

    wp_send_json(array('count' => $count));
}
add_action('wp_ajax_bct_get_post_count', 'bct_get_post_count');
add_action('wp_ajax_nopriv_bct_get_post_count', 'bct_get_post_count');