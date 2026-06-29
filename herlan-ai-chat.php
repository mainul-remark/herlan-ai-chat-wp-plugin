<?php
/**
 * Plugin Name: Herlan AI Chat
 * Description: Local AI beauty consultant chat on product pages
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HERLAN_AI_CHAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HERLAN_AI_CHAT_URL', plugin_dir_url( __FILE__ ) );
define( 'HERLAN_AI_AGENT_URL', 'http://localhost:8000' );

require_once HERLAN_AI_CHAT_DIR . 'includes/class-rest-api.php';
require_once HERLAN_AI_CHAT_DIR . 'includes/class-admin.php';

class Herlan_AI_Chat {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',             [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_single_product_summary', [ $this, 'inject_chat_button' ], 35 );
        add_action( 'wp_footer',                      [ $this, 'inject_chat_modal' ] );
    }

    public function enqueue_assets() {
        if ( ! is_product() ) return;

        wp_enqueue_style(
            'herlan-ai-chat',
            HERLAN_AI_CHAT_URL . 'assets/css/chat.css',
            [], '1.0.0'
        );

        wp_enqueue_script(
            'herlan-ai-chat',
            HERLAN_AI_CHAT_URL . 'assets/js/chat.js',
            [ 'jquery' ], '1.0.0', true
        );

        wp_localize_script( 'herlan-ai-chat', 'HerlanAI', [
            'ajax_url'   => rest_url( 'ai-chat/v1/message' ),
            'stream_url' => rest_url( 'ai-chat/v1/stream' ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'product_id' => get_the_ID(),
        ]);
    }

    public function inject_chat_button() {
        if ( ! is_product() ) return;
        echo '<button id="herlan-ai-open" class="herlan-ai-open-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            Ask Herlan Agent — Your Beauty Consultant
        </button>';
    }

    public function inject_chat_modal() {
        if ( ! is_product() ) return;
        ?>
        <div id="herlan-ai-modal" class="herlan-ai-modal" style="display:none;">
            <div class="herlan-ai-modal-inner">
                <div class="herlan-ai-header">
                    <div class="herlan-ai-avatar">B</div>
                    <div>
                        <div class="herlan-ai-name">Herlan Agent</div>
                        <div class="herlan-ai-status">Beauty Consultant</div>
                    </div>
                    <button id="herlan-ai-close" class="herlan-ai-close">&times;</button>
                </div>
                <div id="herlan-ai-messages" class="herlan-ai-messages">
                    <div class="herlan-ai-msg ai">
                        Hi! I'm Herlan Agent, your personal beauty consultant. I know everything about this product — ask me anything! &#128149;
                    </div>
                </div>
                <div id="herlan-ai-chips" class="herlan-ai-chips">
                    <button class="herlan-chip">What are the ingredients?</button>
                    <button class="herlan-chip">Good for sensitive skin?</button>
                    <button class="herlan-chip">How do I use it?</button>
                    <button class="herlan-chip">Is it worth the price?</button>
                </div>
                <div class="herlan-ai-input-area">
                    <input type="text" id="herlan-ai-input" placeholder="Ask me about this product..." />
                    <button id="herlan-ai-send">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

new Herlan_AI_Chat();