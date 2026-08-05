<?php
/**
 * Public REST API:
 * /wp-json/abti/v1/assign
 * /wp-json/abti/v1/track
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABTI_REST {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'abti/v1', '/assign', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'assign_variation' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'test_id'    => array( 'required' => true ),
                'visitor_id' => array( 'required' => true ),
            ),
        ) );

        register_rest_route( 'abti/v1', '/track', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'track_event' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'test_id'       => array( 'required' => true ),
                'variation_key' => array( 'required' => true ),
                'event_type'    => array( 'required' => true ),
                'visitor_id'    => array( 'required' => false ),
            ),
        ) );
    }

    public function assign_variation( WP_REST_Request $request ) {
        $test_id    = (int) $request->get_param( 'test_id' );
        $visitor_id = substr( sanitize_text_field( $request->get_param( 'visitor_id' ) ), 0, 64 );

        if ( $test_id <= 0 || $visitor_id === '' ) {
            return $this->no_store_response(
                array( 'ok' => false, 'reason' => 'invalid_params' ),
                400
            );
        }

        $test = ABTI_Database::get_test( $test_id );
        if ( ! $test || (int) $test->status !== 1 ) {
            return $this->no_store_response(
                array( 'ok' => false, 'reason' => 'test_not_active' ),
                404
            );
        }

        $variation_key = ABTI_Database::assign_visitor( $test, $visitor_id );
        if ( ! $variation_key ) {
            return $this->no_store_response(
                array( 'ok' => false, 'reason' => 'assignment_failed' ),
                503
            );
        }

        return $this->no_store_response(
            array(
                'ok'            => true,
                'variation_key' => $variation_key,
            ),
            200
        );
    }

    public function track_event( WP_REST_Request $request ) {
        $test_id       = (int) $request->get_param( 'test_id' );
        $variation_key = sanitize_text_field( $request->get_param( 'variation_key' ) );
        $event_type    = sanitize_text_field( $request->get_param( 'event_type' ) );
        $visitor_id    = substr( sanitize_text_field( $request->get_param( 'visitor_id' ) ), 0, 64 );

        if ( $test_id <= 0 || ! in_array( $event_type, array( 'view', 'conversion' ), true ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_params' ), 400 );
        }

        $test = ABTI_Database::get_test( $test_id );
        if ( ! $test || (int) $test->status !== 1 ) {
            return new WP_REST_Response( array( 'ok' => false, 'reason' => 'test_not_active' ), 404 );
        }

        $variations = json_decode( $test->variations, true );
        $valid_keys = is_array( $variations ) ? wp_list_pluck( $variations, 'key' ) : array();
        if ( ! in_array( $variation_key, $valid_keys, true ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_variation' ), 400 );
        }

        ABTI_Database::record_event( $test_id, $variation_key, $event_type, $visitor_id );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    private function no_store_response( $data, $status ) {
        $response = new WP_REST_Response( $data, $status );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Expires', '0' );
        return $response;
    }
}
