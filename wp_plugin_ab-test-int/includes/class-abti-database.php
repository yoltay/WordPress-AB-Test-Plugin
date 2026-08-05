<?php
/**
 * Veritabani yardimcilari.
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

    public static function assignments_table() {
        global $wpdb;
        return $wpdb->prefix . 'abti_assignments';
    }

    /**
     * Eklenti aktive edildiginde veya surum yukseltildiginde tablolari olusturur.
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tests       = self::tests_table();
        $events      = self::events_table();
        $assignments = self::assignments_table();

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

        $sql_assignments = "CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            variation_key VARCHAR(20) NOT NULL,
            visitor_id VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY test_visitor (test_id, visitor_id),
            KEY test_var (test_id, variation_key),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_tests );
        dbDelta( $sql_events );
        dbDelta( $sql_assignments );

        update_option( 'abti_db_version', ABTI_VERSION );
    }

    /**
     * Upload ile degistirmede activation hook calismadigi icin surum kontrollu migration.
     */
    public static function maybe_upgrade() {
        $installed = (string) get_option( 'abti_db_version', '0' );
        if ( version_compare( $installed, ABTI_VERSION, '<' ) ) {
            self::activate();
        }
    }

    public static function deactivate() {
        // Tablolari silmiyoruz.
    }

    public static function uninstall() {
        global $wpdb;
        $tests       = self::tests_table();
        $events      = self::events_table();
        $assignments = self::assignments_table();
        $wpdb->query( "DROP TABLE IF EXISTS {$assignments}" );
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
        $tests       = self::tests_table();
        $events      = self::events_table();
        $assignments = self::assignments_table();
        $wpdb->delete( $assignments, array( 'test_id' => (int) $id ), array( '%d' ) );
        $wpdb->delete( $events, array( 'test_id' => (int) $id ), array( '%d' ) );
        return $wpdb->delete( $tests, array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Sadece event kayitlarini siler. Test ve kota atamalari korunur.
     */
    public static function reset_test_events( $id ) {
        global $wpdb;
        $events = self::events_table();
        return $wpdb->delete( $events, array( 'test_id' => (int) $id ), array( '%d' ) );
    }

    /* -----------------------------------------------------------------
     * Kalici varyasyon atamalari
     * ----------------------------------------------------------------- */

    public static function get_visitor_assignment( $test_id, $visitor_id ) {
        global $wpdb;
        $table = self::assignments_table();
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT variation_key FROM {$table} WHERE test_id = %d AND visitor_id = %s LIMIT 1",
            (int) $test_id,
            (string) $visitor_id
        ) );
    }

    public static function get_assignment_counts( $test_id ) {
        global $wpdb;
        $table = self::assignments_table();
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT variation_key, COUNT(*) AS total
             FROM {$table}
             WHERE test_id = %d
             GROUP BY variation_key",
            (int) $test_id
        ) );

        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row->variation_key ] = (int) $row->total;
        }
        return $counts;
    }

    /**
     * Mevcut atamayi dondurur veya hedef acigina gore yeni bir atama olusturur.
     */
    public static function assign_visitor( $test, $visitor_id ) {
        global $wpdb;

        $test_id    = isset( $test->id ) ? (int) $test->id : 0;
        $visitor_id = substr( sanitize_text_field( $visitor_id ), 0, 64 );
        $variations = isset( $test->variations ) ? json_decode( $test->variations, true ) : array();

        if ( $test_id <= 0 || $visitor_id === '' || ! is_array( $variations ) || empty( $variations ) ) {
            return false;
        }

        $valid_keys = wp_list_pluck( $variations, 'key' );
        $lock_name  = 'abti_assign_' . $test_id;
        $has_lock   = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT GET_LOCK(%s, 2)',
            $lock_name
        ) ) === 1;

        try {
            $existing = self::get_visitor_assignment( $test_id, $visitor_id );
            if ( $existing && in_array( $existing, $valid_keys, true ) ) {
                return $existing;
            }

            $table = self::assignments_table();
            if ( $existing ) {
                $wpdb->delete(
                    $table,
                    array( 'test_id' => $test_id, 'visitor_id' => $visitor_id ),
                    array( '%d', '%s' )
                );
            }

            $counts = self::get_assignment_counts( $test_id );
            $chosen = ABTI_Quota::choose_variation( $variations, $counts );
            if ( $chosen === '' ) {
                return false;
            }

            $inserted = $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$table}
                    (test_id, variation_key, visitor_id, created_at)
                 VALUES (%d, %s, %s, %s)",
                $test_id,
                $chosen,
                $visitor_id,
                current_time( 'mysql' )
            ) );

            if ( $inserted ) {
                return $chosen;
            }

            $existing = self::get_visitor_assignment( $test_id, $visitor_id );
            return $existing && in_array( $existing, $valid_keys, true ) ? $existing : false;
        } finally {
            if ( $has_lock ) {
                $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
            }
        }
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
