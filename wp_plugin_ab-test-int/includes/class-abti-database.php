<?php
/**
 * Veritabanı yardımcıları.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABTI_Database {

    public static function tests_table() {
        global $wpdb;
        return $wpdb->prefix . 'abti_tests';
    }

    public static function events_table() {
        global $wpdb;
        return $wpdb->prefix . 'abti_events';
    }

    /**
     * Eklenti aktive edildiğinde tabloları oluştur.
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tests  = self::tests_table();
        $events = self::events_table();

        $sql_tests = "CREATE TABLE {$tests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            page_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            variations LONGTEXT NULL,
            goal_type VARCHAR(20) NOT NULL DEFAULT 'click',
            goal_selector VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY page_id (page_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_events = "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            variation_key VARCHAR(20) NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            visitor_id VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY test_var (test_id, variation_key),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY visitor_id (visitor_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_tests );
        dbDelta( $sql_events );

        update_option( 'abti_db_version', ABTI_VERSION );
    }

    public static function deactivate() {
        // Tabloları silmiyoruz, kullanıcı eklentiyi tamamen kaldırırsa silelim.
    }

    public static function uninstall() {
        global $wpdb;
        $tests  = self::tests_table();
        $events = self::events_table();
        $wpdb->query( "DROP TABLE IF EXISTS {$events}" );
        $wpdb->query( "DROP TABLE IF EXISTS {$tests}" );
        delete_option( 'abti_db_version' );
    }

    /* -----------------------------------------------------------------
     * Test CRUD
     * ----------------------------------------------------------------- */

    public static function get_tests() {
        global $wpdb;
        $table = self::tests_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
    }

    public static function get_test( $id ) {
        global $wpdb;
        $table = self::tests_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
    }

    /**
     * page_id verilen sayfa için aktif testleri getirir.
     */
    public static function get_active_tests_for_page( $page_id ) {
        global $wpdb;
        $table = self::tests_table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE page_id = %d AND status = 1",
            (int) $page_id
        ) );
    }

    public static function insert_test( $data ) {
        global $wpdb;
        $table = self::tests_table();
        $now   = current_time( 'mysql' );
        $wpdb->insert(
            $table,
            array(
                'name'          => $data['name'],
                'page_id'       => (int) $data['page_id'],
                'status'        => isset( $data['status'] ) ? (int) $data['status'] : 1,
                'variations'    => wp_json_encode( $data['variations'] ),
                'goal_type'     => $data['goal_type'],
                'goal_selector' => $data['goal_selector'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        return $wpdb->insert_id;
    }

    public static function update_test( $id, $data ) {
        global $wpdb;
        $table = self::tests_table();
        return $wpdb->update(
            $table,
            array(
                'name'          => $data['name'],
                'page_id'       => (int) $data['page_id'],
                'status'        => isset( $data['status'] ) ? (int) $data['status'] : 1,
                'variations'    => wp_json_encode( $data['variations'] ),
                'goal_type'     => $data['goal_type'],
                'goal_selector' => $data['goal_selector'],
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    public static function delete_test( $id ) {
        global $wpdb;
        $tests  = self::tests_table();
        $events = self::events_table();
        $wpdb->delete( $events, array( 'test_id' => (int) $id ), array( '%d' ) );
        return $wpdb->delete( $tests, array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Bir teste ait tüm event kayıtlarını siler. Test konfigürasyonu korunur.
     *
     * @return int|false Silinen satır sayısı veya false.
     */
    public static function reset_test_events( $id ) {
        global $wpdb;
        $events = self::events_table();
        return $wpdb->delete( $events, array( 'test_id' => (int) $id ), array( '%d' ) );
    }

    /* -----------------------------------------------------------------
     * Event tracking
     * ----------------------------------------------------------------- */

    public static function record_event( $test_id, $variation_key, $event_type, $visitor_id = '' ) {
        global $wpdb;
        $table = self::events_table();
        $wpdb->insert(
            $table,
            array(
                'test_id'       => (int) $test_id,
                'variation_key' => sanitize_text_field( $variation_key ),
                'event_type'    => sanitize_text_field( $event_type ),
                'visitor_id'    => sanitize_text_field( $visitor_id ),
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Bir test için varyasyon bazında özet istatistik döndürür.
     */
    public static function get_test_stats( $test_id ) {
        global $wpdb;
        $events = self::events_table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT variation_key, event_type, COUNT(*) AS total
             FROM {$events}
             WHERE test_id = %d
             GROUP BY variation_key, event_type",
            (int) $test_id
        ) );
    }

    /**
     * Tarih bazlı zaman serisi (gün gün).
     */
    public static function get_test_timeseries( $test_id, $start = null, $end = null ) {
        global $wpdb;
        $events = self::events_table();

        $sql  = "SELECT DATE(created_at) AS day, variation_key, event_type, COUNT(*) AS total
                 FROM {$events} WHERE test_id = %d";
        $args = array( (int) $test_id );

        if ( $start ) {
            $sql   .= ' AND DATE(created_at) >= %s';
            $args[] = $start;
        }
        if ( $end ) {
            $sql   .= ' AND DATE(created_at) <= %s';
            $args[] = $end;
        }
        $sql .= ' GROUP BY day, variation_key, event_type ORDER BY day ASC';

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }
}
