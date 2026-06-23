<?php
/**
 * Yönetim paneli: menü, sayfalar (liste, ekle/düzenle, stats), kaydetme/silme aksiyonları.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABTI_Admin {

    const MENU_SLUG = 'abti-tests';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'A/B Test int', 'ab-test-int' ),
            __( 'A/B Test int', 'ab-test-int' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'route_page' ),
            'dashicons-chart-bar',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Testler', 'ab-test-int' ),
            __( 'Testler', 'ab-test-int' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'route_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Yeni Test', 'ab-test-int' ),
            __( 'Yeni Test', 'ab-test-int' ),
            'manage_options',
            self::MENU_SLUG . '-new',
            array( $this, 'route_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( (string) $hook, self::MENU_SLUG ) === false ) {
            return;
        }
        wp_enqueue_style(
            'abti-admin',
            ABTI_URL . 'admin/css/admin.css',
            array(),
            ABTI_VERSION
        );

        // Chart.js – yalnızca stats sayfasında.
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        if ( $action === 'stats' ) {
            wp_enqueue_script(
                'abti-chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );
        }

        wp_enqueue_script(
            'abti-admin',
            ABTI_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            ABTI_VERSION,
            true
        );

        wp_localize_script( 'abti-admin', 'ABTI_ADMIN', array(
            'i18n' => array(
                'confirmDelete' => __( 'Bu testi silmek istediğine emin misin?', 'ab-test-int' ),
                'confirmReset'  => __( 'Bu testin TÜM istatistik verileri (görüntülenme + dönüşüm kayıtları) silinecek. Test ayarları korunacak. Devam edilsin mi?', 'ab-test-int' ),
                'maxVariations' => __( 'En fazla 5 varyasyon ekleyebilirsiniz.', 'ab-test-int' ),
                'minVariations' => __( 'En az 2 varyasyon olmalı.', 'ab-test-int' ),
                'sumNot100'     => __( 'Yüzdelerin toplamı 100 olmalı (şu an: %d).', 'ab-test-int' ),
            ),
        ) );
    }

    /**
     * Tek admin callback'i tüm action'ları yönlendirir.
     */
    public function route_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( $page === self::MENU_SLUG . '-new' || $action === 'new' ) {
            $this->render_form_page( null );
            return;
        }
        if ( $action === 'edit' ) {
            $id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
            $test = $id ? ABTI_Database::get_test( $id ) : null;
            $this->render_form_page( $test );
            return;
        }
        if ( $action === 'stats' ) {
            $id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
            $test = $id ? ABTI_Database::get_test( $id ) : null;
            $this->render_stats_page( $test );
            return;
        }
        $this->render_list_page();
    }

    /**
     * Form gönderimleri ve silme aksiyonları.
     */
    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save (yeni / düzenle).
        if ( isset( $_POST['abti_action'] ) && $_POST['abti_action'] === 'save_test' ) {
            check_admin_referer( 'abti_save_test' );
            $id = $this->save_test_from_post();
            $redirect = add_query_arg(
                array(
                    'page'    => self::MENU_SLUG,
                    'action'  => 'edit',
                    'id'      => $id,
                    'updated' => 1,
                ),
                admin_url( 'admin.php' )
            );
            wp_safe_redirect( $redirect );
            exit;
        }

        // Delete.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
            $id = (int) $_GET['id'];
            check_admin_referer( 'abti_delete_' . $id );
            ABTI_Database::delete_test( $id );
            wp_safe_redirect( add_query_arg(
                array( 'page' => self::MENU_SLUG, 'deleted' => 1 ),
                admin_url( 'admin.php' )
            ) );
            exit;
        }

        // Reset stats (sadece event'leri sil, test korunur).
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_stats' && isset( $_GET['id'] ) ) {
            $id = (int) $_GET['id'];
            check_admin_referer( 'abti_reset_' . $id );
            ABTI_Database::reset_test_events( $id );
            wp_safe_redirect( add_query_arg(
                array(
                    'page'      => self::MENU_SLUG,
                    'action'    => 'stats',
                    'id'        => $id,
                    'reset'     => 1,
                ),
                admin_url( 'admin.php' )
            ) );
            exit;
        }
    }

    private function save_test_from_post() {
        $id   = isset( $_POST['test_id'] ) ? (int) $_POST['test_id'] : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $page_id = isset( $_POST['page_id'] ) ? (int) $_POST['page_id'] : 0;
        $status  = isset( $_POST['status'] ) ? 1 : 0;

        $goal_type = isset( $_POST['goal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_type'] ) ) : 'click';
        if ( ! in_array( $goal_type, array( 'click', 'form_submit' ), true ) ) {
            $goal_type = 'click';
        }
        $goal_selector = isset( $_POST['goal_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_selector'] ) ) : '';

        // Varyasyonlar.
        $variations_in = isset( $_POST['variations'] ) && is_array( $_POST['variations'] )
            ? wp_unslash( $_POST['variations'] )
            : array();

        $allowed_keys = array( 'a', 'b', 'c', 'd', 'e' );
        $variations   = array();
        foreach ( $variations_in as $v ) {
            $key = isset( $v['key'] ) ? strtolower( sanitize_text_field( $v['key'] ) ) : '';
            if ( ! in_array( $key, $allowed_keys, true ) ) {
                continue;
            }
            $variations[] = array(
                'key'           => $key,
                'name'          => isset( $v['name'] ) ? sanitize_text_field( $v['name'] ) : strtoupper( $key ),
                'selector'      => isset( $v['selector'] ) ? $this->sanitize_css_name( $v['selector'] ) : '',
                'selector_type' => ( isset( $v['selector_type'] ) && $v['selector_type'] === 'class' ) ? 'class' : 'id',
                'percentage'    => isset( $v['percentage'] ) ? max( 0, min( 100, (int) $v['percentage'] ) ) : 0,
            );
        }

        $data = array(
            'name'          => $name,
            'page_id'       => $page_id,
            'status'        => $status,
            'variations'    => $variations,
            'goal_type'     => $goal_type,
            'goal_selector' => $goal_selector,
        );

        if ( $id > 0 ) {
            ABTI_Database::update_test( $id, $data );
            return $id;
        }
        return ABTI_Database::insert_test( $data );
    }

    private function sanitize_css_name( $value ) {
        $value = trim( (string) $value );
        $value = ltrim( $value, '#.' );
        return preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
    }

    /* -----------------------------------------------------------------
     * Render: list
     * ----------------------------------------------------------------- */

    private function render_list_page() {
        $tests = ABTI_Database::get_tests();
        include ABTI_DIR . 'admin/views/list.php';
    }

    /* -----------------------------------------------------------------
     * Render: form (new / edit)
     * ----------------------------------------------------------------- */

    private function render_form_page( $test ) {
        $variations = array();
        if ( $test && ! empty( $test->variations ) ) {
            $decoded = json_decode( $test->variations, true );
            if ( is_array( $decoded ) ) {
                $variations = $decoded;
            }
        }
        if ( empty( $variations ) ) {
            $variations = array(
                array_merge( $this->blank_variation( 'a' ), array( 'percentage' => 50 ) ),
                array_merge( $this->blank_variation( 'b' ), array( 'percentage' => 50 ) ),
            );
        }

        $pages_dropdown = $this->get_pages_dropdown_options();

        include ABTI_DIR . 'admin/views/edit.php';
    }

    public static function blank_variation( $key ) {
        return array(
            'key'           => $key,
            'name'          => __( 'Varyasyon ', 'ab-test-int' ) . strtoupper( $key ),
            'selector'      => self::generate_selector( $key ),
            'selector_type' => 'id',
            'percentage'    => 0,
        );
    }

    public static function generate_selector( $key ) {
        $rand = strtolower( wp_generate_password( 8, false, false ) );
        // wp_generate_password sayı/harf üretir. Sadece a-z0-9 isteyelim.
        $rand = preg_replace( '/[^a-z0-9]/', '', $rand );
        if ( strlen( $rand ) < 8 ) {
            $rand = str_pad( $rand, 8, '0' );
        }
        return strtolower( $key ) . '-' . substr( $rand, 0, 8 );
    }

    private function get_pages_dropdown_options() {
        $items = array();

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $post_types['attachment'] );

        foreach ( $post_types as $pt ) {
            $posts = get_posts( array(
                'post_type'      => $pt->name,
                'post_status'    => array( 'publish', 'private', 'draft' ),
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ) );
            if ( ! $posts ) {
                continue;
            }
            $items[ $pt->labels->name ] = array();
            foreach ( $posts as $p ) {
                $items[ $pt->labels->name ][ $p->ID ] = $p->post_title !== '' ? $p->post_title : ( '#' . $p->ID );
            }
        }
        return $items;
    }

    /* -----------------------------------------------------------------
     * Render: stats
     * ----------------------------------------------------------------- */

    private function render_stats_page( $test ) {
        if ( ! $test ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Test bulunamadı', 'ab-test-int' ) . '</h1></div>';
            return;
        }
        $variations = json_decode( $test->variations, true );
        if ( ! is_array( $variations ) ) {
            $variations = array();
        }

        // Özet.
        $rows  = ABTI_Database::get_test_stats( $test->id );
        $stats = array();
        foreach ( $variations as $v ) {
            $stats[ $v['key'] ] = array(
                'key'         => $v['key'],
                'name'        => $v['name'],
                'percentage'  => isset( $v['percentage'] ) ? (int) $v['percentage'] : 0,
                'views'       => 0,
                'conversions' => 0,
            );
        }
        foreach ( $rows as $r ) {
            if ( isset( $stats[ $r->variation_key ] ) ) {
                if ( $r->event_type === 'view' ) {
                    $stats[ $r->variation_key ]['views'] = (int) $r->total;
                } elseif ( $r->event_type === 'conversion' ) {
                    $stats[ $r->variation_key ]['conversions'] = (int) $r->total;
                }
            }
        }
        foreach ( $stats as $k => $row ) {
            $stats[ $k ]['rate'] = $row['views'] > 0 ? round( ( $row['conversions'] / $row['views'] ) * 100, 1 ) : 0.0;
        }

        // Sıralama (yüksek conversion rate = #1).
        $ordered = $stats;
        uasort( $ordered, function ( $a, $b ) {
            if ( $a['rate'] === $b['rate'] ) return 0;
            return ( $a['rate'] > $b['rate'] ) ? -1 : 1;
        } );
        $rank = 1;
        foreach ( $ordered as $k => $r ) {
            $stats[ $k ]['rank'] = $rank++;
        }

        // Zaman serisi.
        $start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
        $end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
        $start = $start && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : gmdate( 'Y-m-d', strtotime( '-90 days' ) );
        $end   = $end && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ? $end : gmdate( 'Y-m-d' );

        $ts_rows = ABTI_Database::get_test_timeseries( $test->id, $start, $end );

        // Gün gün serileri hazırla.
        $days = array();
        $cur  = strtotime( $start );
        $stop = strtotime( $end );
        while ( $cur <= $stop ) {
            $days[ gmdate( 'Y-m-d', $cur ) ] = true;
            $cur = strtotime( '+1 day', $cur );
        }
        $series_views       = array();
        $series_conversions = array();
        foreach ( $variations as $v ) {
            $series_views[ $v['key'] ]       = array_fill_keys( array_keys( $days ), 0 );
            $series_conversions[ $v['key'] ] = array_fill_keys( array_keys( $days ), 0 );
        }
        foreach ( $ts_rows as $r ) {
            if ( ! isset( $series_views[ $r->variation_key ] ) ) continue;
            if ( ! isset( $days[ $r->day ] ) ) continue;
            if ( $r->event_type === 'view' ) {
                $series_views[ $r->variation_key ][ $r->day ] = (int) $r->total;
            } elseif ( $r->event_type === 'conversion' ) {
                $series_conversions[ $r->variation_key ][ $r->day ] = (int) $r->total;
            }
        }

        $chart_payload = array(
            'labels'      => array_keys( $days ),
            'variations'  => array_values( $variations ),
            'conversions' => $series_conversions,
            'views'       => $series_views,
            'stats'       => array_values( $stats ),
        );

        include ABTI_DIR . 'admin/views/stats.php';
    }
}
