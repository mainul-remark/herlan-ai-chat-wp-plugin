<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Herlan_AI_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'create_logs_table' ] );
    }

    public function add_menu() {
        add_menu_page(
            'AI Chat', 'AI Chat', 'manage_options',
            'herlan-ai-chat', [ $this, 'render_page' ],
            'dashicons-format-chat', 30
        );
    }

    public function create_logs_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_logs';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table ) {
            $wpdb->query("CREATE TABLE $table (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(64),
                product_id BIGINT,
                role VARCHAR(10),
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    }

    public function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_chat_logs';

        // Agent status check
        $status_response = wp_remote_get( HERLAN_AI_AGENT_URL . '/health', [ 'timeout' => 3 ] );
        $agent_online    = ! is_wp_error( $status_response ) && wp_remote_retrieve_response_code( $status_response ) === 200;

        // Stats
        $total_sessions = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $top_products   = $wpdb->get_results("
            SELECT product_id, COUNT(DISTINCT session_id) as chat_count
            FROM $table GROUP BY product_id
            ORDER BY chat_count DESC LIMIT 5
        ");

        // Recent logs
        $logs = $wpdb->get_results("
            SELECT * FROM $table ORDER BY created_at DESC LIMIT 50
        ");
        ?>
        <div class="wrap">
            <h1>Herlan AI Chat Dashboard</h1>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;flex:1;">
                    <h3 style="margin:0 0 8px;">Agent Status</h3>
                    <span style="color:<?php echo $agent_online ? '#22c55e' : '#ef4444'; ?>;font-weight:700;font-size:16px;">
                        <?php echo $agent_online ? '● Online' : '● Offline'; ?>
                    </span>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;flex:1;">
                    <h3 style="margin:0 0 8px;">Total Sessions</h3>
                    <span style="font-size:28px;font-weight:700;color:#7c3aed;"><?php echo $total_sessions ?? 0; ?></span>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;flex:1;">
                    <h3 style="margin:0 0 8px;">Total Messages</h3>
                    <span style="font-size:28px;font-weight:700;color:#7c3aed;"><?php echo $total_messages ?? 0; ?></span>
                </div>
            </div>

            <h2>Most Chatted Products</h2>
            <table class="widefat striped">
                <thead><tr><th>Product</th><th>Chat Sessions</th></tr></thead>
                <tbody>
                <?php foreach ( $top_products as $row ) :
                    $product = get_post( $row->product_id );
                    $name    = $product ? $product->post_title : 'Product #' . $row->product_id;
                    ?>
                    <tr>
                        <td><a href="<?php echo get_edit_post_link( $row->product_id ); ?>"><?php echo esc_html( $name ); ?></a></td>
                        <td><strong><?php echo $row->chat_count; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:30px;">Recent Chat Logs</h2>
            <table class="widefat striped">
                <thead><tr><th>Time</th><th>Session</th><th>Product</th><th>Role</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo esc_html( $log->created_at ); ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?php echo esc_html( substr( $log->session_id, 0, 12 ) ); ?>...</td>
                        <td><?php echo esc_html( $log->product_id ); ?></td>
                        <td><span style="color:<?php echo $log->role === 'ai' ? '#7c3aed' : '#0ea5e9'; ?>;font-weight:700;"><?php echo esc_html( $log->role ); ?></span></td>
                        <td><?php echo esc_html( substr( $log->message, 0, 150 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new Herlan_AI_Admin();