<?php
/*
Plugin Name: Tag Frequency Dashboard
Description: Insere um painel em posts ou páginas para gerar gráficos de frequência de TAGs ou expressões.
Version: 1.3
Author: Pedro Andretta
*/

// Segurança: Evita acesso direto ao arquivo
if ( !defined( 'ABSPATH' ) ) exit;

// Registra o shortcode
add_shortcode('tag_frequency_dashboard', 'tfd_shortcode');

function tfd_shortcode() {
    ob_start();
    tfd_render_dashboard();
    return ob_get_clean();
}

// Renderiza o painel
function tfd_render_dashboard() {
    ?>
    <div class="tfd-dashboard">
        <form id="tfd-search-form">
            <div class="tfd-inputs">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="tfd-input-group">
                        <label for="tfd-search-input-<?php echo $i; ?>">Consulta <?php echo $i; ?>:</label>
                        <input type="text" id="tfd-search-input-<?php echo $i; ?>" name="search_terms[]" placeholder="Digite uma TAG ou expressão">
                        <label>
                            <input type="checkbox" name="group_terms[]" value="<?php echo $i - 1; ?>">
                            Agrupar esta consulta
                        </label>
                    </div>
                <?php endfor; ?>
            </div>
            <button type="submit" class="button">Pesquisar</button>
            <button type="button" id="tfd-clear-button" class="button">Limpar todos os campos</button>
        </form>
        <div id="tfd-chart-container" style="margin-top: 20px;">
            <!-- Os gráficos serão inseridos aqui -->
        </div>
    </div>
    <?php
}

// Enfileira os scripts e estilos necessários
add_action('wp_enqueue_scripts', 'tfd_enqueue_scripts');

function tfd_enqueue_scripts() {
    if ( ! is_singular() ) {
        return;
    }

    wp_enqueue_script('jquery-ui-autocomplete');
    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);

    wp_enqueue_script('tfd-script', plugin_dir_url(__FILE__) . 'tfd-script.js', array('jquery', 'jquery-ui-autocomplete', 'chart-js'), null, true);
    wp_localize_script('tfd-script', 'tfd_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

    wp_enqueue_style('tfd-style', plugin_dir_url(__FILE__) . 'tfd-style.css');
}

// Ações AJAX para obter as TAGs e os dados de frequência
add_action('wp_ajax_nopriv_tfd_get_tags', 'tfd_get_tags');
add_action('wp_ajax_tfd_get_tags', 'tfd_get_tags');

function tfd_get_tags() {
    $tags = get_tags(array('hide_empty' => false));
    $tag_names = array();
    foreach ( $tags as $tag ) {
        $tag_names[] = $tag->name;
    }
    wp_send_json_success($tag_names);
}

add_action('wp_ajax_nopriv_tfd_get_frequency_data', 'tfd_get_frequency_data');
add_action('wp_ajax_tfd_get_frequency_data', 'tfd_get_frequency_data');

function tfd_get_frequency_data() {
    $search_terms = isset($_POST['search_terms']) ? array_map('sanitize_text_field', $_POST['search_terms']) : array();
    $group_terms = isset($_POST['group_terms']) ? $_POST['group_terms'] : array();
    $search_terms = array_slice($search_terms, 0, 6); // Limita a 6 termos

    $data = array();
    $grouped_terms = array();

    // Converte índices de string para inteiros
    $group_terms = array_map('intval', $group_terms);

    // Processa os termos e grupos
    foreach ( $search_terms as $index => $term ) {
        $term = trim($term);
        if ( empty($term) ) {
            continue;
        }

        $group = in_array($index, $group_terms) ? 'grouped' : 'ungrouped_' . $index;

        if ( ! isset($grouped_terms[$group]) ) {
            $grouped_terms[$group] = array();
        }

        $grouped_terms[$group][] = $term;
    }

    foreach ( $grouped_terms as $group => $terms ) {
        $frequency_data = tfd_get_combined_term_frequency($terms);
        $label = implode(' + ', $terms);
        $data[] = array(
            'term' => $label,
            'frequency' => $frequency_data
        );
    }

    wp_send_json_success($data);
}

// Função atualizada para obter a frequência combinada de múltiplos termos
function tfd_get_combined_term_frequency($terms) {
    global $wpdb;

    $post_ids = array();

    // Obter IDs dos posts que correspondem aos termos
    foreach ( $terms as $term ) {
        $term = trim($term);
        if ( empty($term) ) {
            continue;
        }

        // Verifica se o termo é uma TAG existente
        $tag = get_term_by('name', $term, 'post_tag');

        if ( $tag ) {
            // Busca posts com a TAG
            $tag_posts = get_posts(array(
                'tag_id' => $tag->term_id,
                'fields' => 'ids',
                'post_type' => 'post',
                'post_status' => 'publish',
                'nopaging' => true,
            ));
            $post_ids = array_merge($post_ids, $tag_posts);
        } else {
            // Busca posts por expressão no título ou conteúdo
            $like_term = '%' . $wpdb->esc_like( $term ) . '%';
            $query = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts
                WHERE post_type = 'post' AND post_status = 'publish'
                AND (post_title LIKE %s OR post_content LIKE %s)",
                $like_term, $like_term
            );
            $expression_posts = $wpdb->get_col( $query );
            $post_ids = array_merge($post_ids, $expression_posts);
        }
    }

    // Remove duplicatas
    $post_ids = array_unique($post_ids);

    if ( empty($post_ids) ) {
        return array(
            'monthly' => array(),
            'yearly' => array()
        );
    }

    // Obter as datas dos posts
    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
    $query_args = $post_ids;

    array_unshift( $query_args, "SELECT post_date FROM $wpdb->posts WHERE ID IN ($placeholders)" );

    $query = call_user_func_array( array( $wpdb, 'prepare' ), $query_args );

    $post_dates = $wpdb->get_col( $query );

    $frequency_monthly = array();
    $frequency_yearly = array();

    foreach ( $post_dates as $post_date ) {
        $timestamp = strtotime( $post_date );
        $year = date( 'Y', $timestamp );
        $month = date( 'Y-m', $timestamp );

        if ( isset( $frequency_monthly[ $month ] ) ) {
            $frequency_monthly[ $month ] += 1;
        } else {
            $frequency_monthly[ $month ] = 1;
        }

        if ( isset( $frequency_yearly[ $year ] ) ) {
            $frequency_yearly[ $year ] += 1;
        } else {
            $frequency_yearly[ $year ] = 1;
        }
    }

    return array(
        'monthly' => $frequency_monthly,
        'yearly' => $frequency_yearly
    );
}