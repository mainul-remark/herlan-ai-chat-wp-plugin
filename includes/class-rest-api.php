<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Herlan_AI_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'ai-chat/v1', '/message', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_message' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'ai-chat/v1', '/stream', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_stream' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_message( WP_REST_Request $request ) {
        $product_id = intval( $request->get_param( 'product_id' ) );
        $session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
        $message    = sanitize_textarea_field( $request->get_param( 'message' ) );

        if ( ! $product_id || ! $session_id || ! $message ) {
            return new WP_Error( 'bad_request', 'Missing parameters', [ 'status' => 400 ] );
        }

        $payload = wp_json_encode([
            'product_id' => $product_id,
            'session_id' => $session_id,
            'message'    => $message,
        ]);

        $response = wp_remote_post( HERLAN_AI_AGENT_URL . '/chat', [
            'body'    => $payload,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 60,
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'agent_error', 'AI agent unavailable. Please try again.', [ 'status' => 503 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return rest_ensure_response([
            'reply'      => $body['reply'] ?? 'Sorry, I could not generate a response.',
            'session_id' => $session_id,
        ]);
    }

    public function handle_stream( WP_REST_Request $request ) {
        $product_id = intval( $request->get_param( 'product_id' ) );
        $session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
        $message    = sanitize_textarea_field( $request->get_param( 'message' ) );

        $payload = wp_json_encode([
            'product_id' => $product_id,
            'session_id' => $session_id,
            'message'    => $message,
        ]);

        // Stream through from FastAPI
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );

        $ch = curl_init( HERLAN_AI_AGENT_URL . '/chat/stream' );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
        curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $curl, $data ) {
            echo $data;
            if ( ob_get_level() ) ob_flush();
            flush();
            return strlen( $data );
        });
        curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
        curl_exec( $ch );
        curl_close( $ch );
        exit;
    }
}

new Herlan_AI_REST_API();